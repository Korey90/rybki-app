<?php

namespace App\Modules\Payments\Actions;

use App\Modules\Bookings\Enums\BookingStatus;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Payments\DTO\ProviderPaymentState;
use App\Modules\Payments\Enums\PaymentProvider;
use App\Modules\Payments\Enums\PaymentReconciliationItemStatus;
use App\Modules\Payments\Enums\PaymentReconciliationRunStatus;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentReconciliationRun;
use App\Modules\Payments\PaymentGatewayManager;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ReconcilePayments
{
    public function __construct(private readonly PaymentGatewayManager $paymentGatewayManager) {}

    public function handle(string $provider = PaymentProvider::Test->value, int $staleMinutes = 30): PaymentReconciliationRun
    {
        $startedAt = now();
        $cutoff = CarbonImmutable::instance($startedAt)->subMinutes($staleMinutes);

        return DB::transaction(function () use ($cutoff, $provider, $startedAt): PaymentReconciliationRun {
            $run = PaymentReconciliationRun::create([
                'provider' => $provider,
                'status' => PaymentReconciliationRunStatus::Running,
                'started_at' => $startedAt,
                'checked_count' => 0,
                'mismatch_count' => 0,
            ]);

            $payments = Payment::query()
                ->with('booking')
                ->where('provider', $provider)
                ->where(function ($query) use ($cutoff): void {
                    $query
                        ->where('status', PaymentStatus::Succeeded->value)
                        ->orWhere(function ($query) use ($cutoff): void {
                            $query
                                ->whereIn('status', [
                                    PaymentStatus::Created->value,
                                    PaymentStatus::Processing->value,
                                ])
                                ->where('created_at', '<=', $cutoff);
                        });
                })
                ->get();

            $mismatchCount = 0;
            $gateway = $this->paymentGatewayManager->for($provider);

            foreach ($payments as $payment) {
                $booking = $payment->booking;
                assert($booking instanceof Booking);

                try {
                    $providerState = $gateway->retrievePayment($payment);
                } catch (Throwable $exception) {
                    $providerState = new ProviderPaymentState(
                        providerPaymentId: (string) $payment->provider_payment_id,
                        status: 'UNAVAILABLE',
                        payload: ['error' => $exception->getMessage()],
                    );
                }

                $item = $this->classify($payment, $booking, $providerState);

                if ($item['status'] !== PaymentReconciliationItemStatus::Ok) {
                    $mismatchCount++;
                }

                $run->items()->create([
                    'payment_id' => $payment->id,
                    'status' => $item['status'],
                    'reason' => $item['reason'],
                    'details' => $item['details'],
                ]);
            }

            $run->update([
                'status' => PaymentReconciliationRunStatus::Completed,
                'checked_count' => $payments->count(),
                'mismatch_count' => $mismatchCount,
                'finished_at' => now(),
                'summary' => [
                    'stale_cutoff' => $cutoff->toIso8601String(),
                    'provider' => $provider,
                ],
            ]);

            return $run->refresh()->load('items.payment');
        });
    }

    /**
     * @return array{
     *     status:PaymentReconciliationItemStatus,
     *     reason:string,
     *     details:array<string, mixed>
     * }
     */
    private function classify(Payment $payment, Booking $booking, ProviderPaymentState $providerState): array
    {
        if ($providerState->status === 'UNAVAILABLE') {
            return [
                'status' => PaymentReconciliationItemStatus::NeedsReview,
                'reason' => 'provider_status_unavailable',
                'details' => [
                    'payment_status' => $payment->status->value,
                    'booking_status' => $booking->status->value,
                    'provider_status' => $providerState->status,
                    'provider_payload' => $providerState->payload,
                ],
            ];
        }

        if ($providerState->status !== $payment->status->value) {
            return [
                'status' => $providerState->status === PaymentStatus::Succeeded->value
                    ? PaymentReconciliationItemStatus::Mismatch
                    : PaymentReconciliationItemStatus::NeedsReview,
                'reason' => 'provider_status_differs_from_local_payment',
                'details' => [
                    'payment_status' => $payment->status->value,
                    'booking_status' => $booking->status->value,
                    'provider_status' => $providerState->status,
                    'provider_payment_id' => $providerState->providerPaymentId,
                    'provider_payload' => $providerState->payload,
                ],
            ];
        }

        if (in_array($payment->status, [PaymentStatus::Created, PaymentStatus::Processing], true)) {
            return [
                'status' => PaymentReconciliationItemStatus::NeedsReview,
                'reason' => 'stale_unfinished_payment',
                'details' => [
                    'payment_status' => $payment->status->value,
                    'booking_status' => $booking->status->value,
                    'provider_status' => $providerState->status,
                ],
            ];
        }

        if ($payment->status === PaymentStatus::Succeeded && $booking->status !== BookingStatus::Confirmed) {
            return [
                'status' => PaymentReconciliationItemStatus::Mismatch,
                'reason' => 'succeeded_payment_without_confirmed_booking',
                'details' => [
                    'payment_status' => $payment->status->value,
                    'booking_status' => $booking->status->value,
                    'booking_uuid' => $booking->uuid,
                    'provider_status' => $providerState->status,
                ],
            ];
        }

        if ($payment->status === PaymentStatus::Succeeded && $payment->paid_at === null) {
            return [
                'status' => PaymentReconciliationItemStatus::Mismatch,
                'reason' => 'succeeded_payment_without_paid_at',
                'details' => [
                    'payment_status' => $payment->status->value,
                    'booking_status' => $booking->status->value,
                    'provider_status' => $providerState->status,
                ],
            ];
        }

        return [
            'status' => PaymentReconciliationItemStatus::Ok,
            'reason' => 'local_state_consistent',
            'details' => [
                'payment_status' => $payment->status->value,
                'booking_status' => $booking->status->value,
                'provider_status' => $providerState->status,
            ],
        ];
    }
}
