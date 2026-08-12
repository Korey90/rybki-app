<?php

namespace App\Modules\Bookings\Actions;

use App\Modules\Bookings\DTO\BookingCancellationQuote;
use App\Modules\Bookings\Enums\BookingStatus;
use App\Modules\Bookings\Enums\ReservationStatus;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Bookings\Models\SpotReservation;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Enums\RefundStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\Refund;
use Carbon\CarbonInterface;

class ResolveBookingCancellationQuote
{
    public function handle(Booking $booking): BookingCancellationQuote
    {
        $booking->loadMissing(['spotReservations', 'payments', 'refunds']);

        $reservation = $booking->spotReservations
            ->filter(fn (SpotReservation $reservation): bool => in_array($reservation->status, [
                ReservationStatus::Hold,
                ReservationStatus::Confirmed,
            ], true))
            ->sortBy('starts_at')
            ->first()
            ?? $booking->spotReservations->sortBy('starts_at')->first();

        $policy = $this->policySnapshot($booking);
        $freeCancellationUntil = $reservation instanceof SpotReservation
            ? $reservation->starts_at->copy()->subHours($policy['free_cancellation_hours'])
            : null;

        if (! $reservation instanceof SpotReservation) {
            return $this->unavailable($booking, 'missing_reservation', $policy, null);
        }

        if ($booking->status === BookingStatus::Cancelled
            || $booking->status === BookingStatus::Refunded
            || $booking->status === BookingStatus::PartiallyRefunded) {
            return $this->unavailable($booking, 'already_cancelled', $policy, $freeCancellationUntil);
        }

        if ($booking->status === BookingStatus::PaymentFailed) {
            return $this->unavailable($booking, 'payment_failed', $policy, $freeCancellationUntil);
        }

        if ($reservation->starts_at->lessThanOrEqualTo(now())) {
            return $this->unavailable($booking, 'after_start', $policy, $freeCancellationUntil);
        }

        if ($booking->status === BookingStatus::PaymentPending) {
            return new BookingCancellationQuote(
                canCancel: true,
                reason: null,
                refundAmount: 0,
                refundPercentage: 0,
                currency: $booking->currency,
                policyName: $policy['name'],
                freeCancellationHours: $policy['free_cancellation_hours'],
                lateRefundPercentage: $policy['late_refund_percentage'],
                freeCancellationUntil: $freeCancellationUntil,
            );
        }

        $refundPercentage = $freeCancellationUntil !== null && now()->lessThanOrEqualTo($freeCancellationUntil)
            ? 100
            : $policy['late_refund_percentage'];
        $refundableAmount = $this->refundableAmount($booking);

        return new BookingCancellationQuote(
            canCancel: true,
            reason: null,
            refundAmount: (int) floor($refundableAmount * $refundPercentage / 100),
            refundPercentage: $refundPercentage,
            currency: $booking->currency,
            policyName: $policy['name'],
            freeCancellationHours: $policy['free_cancellation_hours'],
            lateRefundPercentage: $policy['late_refund_percentage'],
            freeCancellationUntil: $freeCancellationUntil,
        );
    }

    /**
     * @return array{name:string, free_cancellation_hours:int, late_refund_percentage:int}
     */
    private function policySnapshot(Booking $booking): array
    {
        $snapshot = $booking->cancellation_policy_snapshot ?? [];

        return [
            'name' => (string) ($snapshot['name'] ?? 'Standard MVP'),
            'free_cancellation_hours' => (int) ($snapshot['free_cancellation_hours'] ?? 24),
            'late_refund_percentage' => (int) ($snapshot['late_refund_percentage'] ?? 0),
        ];
    }

    /**
     * @param  array{name:string, free_cancellation_hours:int, late_refund_percentage:int}  $policy
     */
    private function unavailable(
        Booking $booking,
        string $reason,
        array $policy,
        ?CarbonInterface $freeCancellationUntil,
    ): BookingCancellationQuote {
        return new BookingCancellationQuote(
            canCancel: false,
            reason: $reason,
            refundAmount: 0,
            refundPercentage: 0,
            currency: $booking->currency,
            policyName: $policy['name'],
            freeCancellationHours: $policy['free_cancellation_hours'],
            lateRefundPercentage: $policy['late_refund_percentage'],
            freeCancellationUntil: $freeCancellationUntil,
        );
    }

    private function refundableAmount(Booking $booking): int
    {
        $paidAmount = (int) $booking->payments
            ->filter(fn (Payment $payment): bool => $payment->status === PaymentStatus::Succeeded)
            ->sum('amount');

        $alreadyRefundedAmount = (int) $booking->refunds
            ->filter(fn (Refund $refund): bool => $refund->status === RefundStatus::Succeeded)
            ->sum('amount');

        return max(0, min($booking->total_amount, $paidAmount) - $alreadyRefundedAmount);
    }
}
