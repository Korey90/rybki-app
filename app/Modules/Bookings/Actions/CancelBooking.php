<?php

namespace App\Modules\Bookings\Actions;

use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditLog;
use App\Modules\Bookings\Enums\BookingStatus;
use App\Modules\Bookings\Enums\ReservationStatus;
use App\Modules\Bookings\Exceptions\BookingCancellationUnavailable;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Bookings\Models\SpotReservation;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Payments\Actions\RefundPayment;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Enums\RefundStatus;
use App\Modules\Payments\Models\Payment;
use Illuminate\Support\Facades\DB;

class CancelBooking
{
    public const ACTOR_ANGLER = 'angler';

    public const ACTOR_OPERATOR = 'operator';

    public function __construct(
        private readonly ResolveBookingCancellationQuote $resolveBookingCancellationQuote,
        private readonly RecordAuditLog $recordAuditLog,
        private readonly DispatchBookingNotification $dispatchBookingNotification,
        private readonly RefundPayment $refundPayment,
    ) {}

    public function handle(Booking $booking, User $actor, string $actorType, ?string $reason = null): Booking
    {
        $cancelledBooking = DB::transaction(function () use ($actor, $actorType, $booking, $reason): Booking {
            $lockedBooking = Booking::query()
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedBooking->load('organization');
            $lockedBooking->setRelation('spotReservations', $lockedBooking->spotReservations()->lockForUpdate()->get());
            $lockedBooking->setRelation('payments', $lockedBooking->payments()->lockForUpdate()->get());
            $lockedBooking->setRelation('refunds', $lockedBooking->refunds()->lockForUpdate()->get());

            $quote = $this->resolveBookingCancellationQuote->handle($lockedBooking);

            if (! $quote->canCancel) {
                throw new BookingCancellationUnavailable($quote->reason ?? 'unavailable');
            }

            $before = [
                'booking_status' => $lockedBooking->status->value,
                'cancelled_at' => $lockedBooking->cancelled_at?->toIso8601String(),
                'reservation_statuses' => $lockedBooking->spotReservations
                    ->map(fn (SpotReservation $reservation): string => $reservation->status->value)
                    ->values()
                    ->all(),
            ];

            $paymentToRefund = $lockedBooking->payments
                ->filter(fn (Payment $payment): bool => $payment->status === PaymentStatus::Succeeded)
                ->sortByDesc('id')
                ->first();

            $refund = null;
            if ($quote->refundAmount > 0 && $paymentToRefund instanceof Payment) {
                $refund = $lockedBooking->refunds()->create([
                    'payment_id' => $paymentToRefund->id,
                    'provider_refund_id' => null,
                    'amount' => $quote->refundAmount,
                    'currency' => $quote->currency,
                    'reason' => $reason ?: $this->defaultRefundReason($actorType),
                    'status' => RefundStatus::Created,
                    'initiated_by_user_id' => $actor->id,
                ]);

                $refund = $this->refundPayment->handle($paymentToRefund, $refund);
            }

            $lockedBooking->payments
                ->filter(fn (Payment $payment): bool => in_array($payment->status, [
                    PaymentStatus::Created,
                    PaymentStatus::Processing,
                ], true))
                ->each(fn (Payment $payment): bool => $payment->update(['status' => PaymentStatus::Cancelled]));

            $lockedBooking->spotReservations
                ->filter(fn (SpotReservation $reservation): bool => in_array($reservation->status, [
                    ReservationStatus::Hold,
                    ReservationStatus::Confirmed,
                ], true))
                ->each(fn (SpotReservation $reservation): bool => $reservation->update([
                    'status' => ReservationStatus::Cancelled,
                    'hold_expires_at' => null,
                ]));

            $lockedBooking->update([
                'status' => $this->bookingStatusAfterCancellation($quote->refundAmount, $lockedBooking->total_amount),
                'cancelled_at' => now(),
            ]);

            $lockedBooking->refresh();

            $organization = $lockedBooking->organization;
            assert($organization instanceof Organization);

            $this->recordAuditLog->handle(
                action: 'booking.cancelled',
                subject: $lockedBooking,
                actor: $actor,
                organization: $organization,
                before: $before,
                after: [
                    'booking_status' => $lockedBooking->status->value,
                    'cancelled_at' => $lockedBooking->cancelled_at?->toIso8601String(),
                    'actor_type' => $actorType,
                    'reason' => $reason,
                    'refund_amount' => $quote->refundAmount,
                    'refund_percentage' => $quote->refundPercentage,
                    'refund_id' => $refund?->id,
                    'policy_name' => $quote->policyName,
                    'free_cancellation_hours' => $quote->freeCancellationHours,
                    'late_refund_percentage' => $quote->lateRefundPercentage,
                ],
            );

            return $lockedBooking->load(['venue', 'spotReservations.spot', 'priceLines', 'payments', 'refunds']);
        });

        $this->dispatchBookingNotification->cancelled($cancelledBooking);

        return $cancelledBooking;
    }

    private function bookingStatusAfterCancellation(int $refundAmount, int $totalAmount): BookingStatus
    {
        if ($refundAmount <= 0) {
            return BookingStatus::Cancelled;
        }

        return $refundAmount >= $totalAmount
            ? BookingStatus::Refunded
            : BookingStatus::PartiallyRefunded;
    }

    private function defaultRefundReason(string $actorType): string
    {
        return match ($actorType) {
            self::ACTOR_ANGLER => 'Anulowanie przez wedkarza',
            self::ACTOR_OPERATOR => 'Anulowanie przez operatora',
            default => 'Anulowanie rezerwacji',
        };
    }
}
