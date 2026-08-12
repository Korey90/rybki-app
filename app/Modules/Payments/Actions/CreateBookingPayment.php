<?php

namespace App\Modules\Payments\Actions;

use App\Modules\Audit\Actions\RecordAuditLog;
use App\Modules\Bookings\Actions\DispatchBookingNotification;
use App\Modules\Bookings\Enums\BookingStatus;
use App\Modules\Bookings\Enums\ReservationStatus;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Bookings\Models\SpotReservation;
use App\Modules\Payments\DTO\PaymentSession;
use App\Modules\Payments\Enums\PaymentProvider;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Exceptions\PaymentProviderRequestFailed;
use App\Modules\Payments\Exceptions\PaymentSessionUnavailable;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\PaymentGatewayManager;
use App\Modules\Payments\PaymentProviderSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateBookingPayment
{
    public function __construct(
        private readonly PaymentGatewayManager $paymentGatewayManager,
        private readonly PaymentProviderSettings $paymentProviderSettings,
        private readonly RecordAuditLog $recordAuditLog,
        private readonly DispatchBookingNotification $dispatchBookingNotification,
    ) {}

    public function handle(
        Booking $booking,
        ?string $provider = null,
        ?string $idempotencyKey = null
    ): PaymentSession {
        $provider = $provider ?: $this->paymentProviderSettings->activeProvider()->value;

        $payment = DB::transaction(function () use ($booking, $idempotencyKey, $provider): ?Payment {
            $lockedBooking = Booking::query()
                ->whereKey($booking->id)
                ->with('spotReservations')
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedBooking->status !== BookingStatus::PaymentPending) {
                throw new PaymentSessionUnavailable('booking_not_payment_pending');
            }

            $reservation = $lockedBooking->spotReservations->first();

            if (! $reservation instanceof SpotReservation || $reservation->status !== ReservationStatus::Hold) {
                throw new PaymentSessionUnavailable('active_hold_not_found');
            }

            if ($reservation->hold_expires_at !== null && $reservation->hold_expires_at->lessThanOrEqualTo(now())) {
                $reservation->update(['status' => ReservationStatus::Expired]);

                return null;
            }

            $existingPayment = $lockedBooking->payments()
                ->where('provider', $provider)
                ->whereIn('status', [
                    PaymentStatus::Created->value,
                    PaymentStatus::Processing->value,
                ])
                ->first();

            if ($existingPayment instanceof Payment) {
                return $existingPayment;
            }

            $payment = $lockedBooking->payments()->create([
                'provider' => $provider,
                'provider_payment_id' => $provider === PaymentProvider::Test->value ? 'test_pay_'.Str::uuid() : null,
                'status' => PaymentStatus::Created,
                'amount' => $lockedBooking->total_amount,
                'currency' => $lockedBooking->currency,
                'idempotency_key' => $idempotencyKey ?: 'booking-payment-'.$provider.'-'.$lockedBooking->uuid,
            ]);

            $this->recordAuditLog->handle(
                action: 'payment.session_created',
                subject: $payment,
                organization: $lockedBooking->organization,
                after: [
                    'booking_uuid' => $lockedBooking->uuid,
                    'payment_uuid' => $payment->uuid,
                    'provider' => $payment->provider->value,
                    'status' => $payment->status->value,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                ],
            );

            return $payment->load('booking');
        });

        if (! $payment instanceof Payment) {
            throw new PaymentSessionUnavailable('hold_expired');
        }

        $booking = $payment->booking;
        assert($booking instanceof Booking);

        if ($payment->provider_payment_id !== null && $payment->checkout_url !== null) {
            $this->dispatchBookingNotification->paymentPending($booking);

            return new PaymentSession(
                provider: $payment->provider->value,
                providerPaymentId: $payment->provider_payment_id,
                checkoutUrl: $payment->checkout_url,
                amount: $payment->amount,
                currency: $payment->currency,
            );
        }

        try {
            $paymentSession = $this->paymentGatewayManager
                ->for($payment->provider->value)
                ->createPayment($booking, $payment);
        } catch (PaymentProviderRequestFailed $exception) {
            throw new PaymentSessionUnavailable($exception->reason);
        }

        $payment->update([
            'provider_payment_id' => $paymentSession->providerPaymentId,
            'checkout_url' => $paymentSession->checkoutUrl,
            'provider_payload' => [
                'session_created_at' => now()->toIso8601String(),
                'provider_payment_id' => $paymentSession->providerPaymentId,
            ],
            'status' => $payment->provider === PaymentProvider::Test
                ? $payment->status
                : PaymentStatus::Processing,
        ]);

        $this->dispatchBookingNotification->paymentPending($booking);

        return $paymentSession;
    }
}
