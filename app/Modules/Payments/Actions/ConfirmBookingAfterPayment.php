<?php

namespace App\Modules\Payments\Actions;

use App\Modules\Audit\Actions\RecordAuditLog;
use App\Modules\Bookings\Actions\DispatchBookingNotification;
use App\Modules\Bookings\Enums\BookingStatus;
use App\Modules\Bookings\Enums\ReservationStatus;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Bookings\Models\SpotReservation;
use App\Modules\Payments\Exceptions\PaymentConfirmationFailed;
use Illuminate\Support\Facades\DB;

class ConfirmBookingAfterPayment
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
        private readonly DispatchBookingNotification $dispatchBookingNotification,
    ) {}

    public function handle(Booking $booking): Booking
    {
        $holdExpired = false;

        $confirmedBooking = DB::transaction(function () use ($booking, &$holdExpired): ?Booking {
            $lockedBooking = Booking::query()
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedBooking->status === BookingStatus::Confirmed) {
                return $lockedBooking;
            }

            if ($lockedBooking->status !== BookingStatus::PaymentPending) {
                throw new PaymentConfirmationFailed('booking_not_payment_pending');
            }

            $reservation = SpotReservation::query()
                ->where('booking_id', $lockedBooking->id)
                ->lockForUpdate()
                ->first();

            if (! $reservation instanceof SpotReservation) {
                throw new PaymentConfirmationFailed('reservation_not_found');
            }

            if ($reservation->status !== ReservationStatus::Hold) {
                throw new PaymentConfirmationFailed('reservation_not_on_hold');
            }

            if ($reservation->hold_expires_at !== null && $reservation->hold_expires_at->lessThanOrEqualTo(now())) {
                $reservation->update(['status' => ReservationStatus::Expired]);
                $holdExpired = true;

                return null;
            }

            $hasConfirmedOverlap = SpotReservation::query()
                ->where('id', '!=', $reservation->id)
                ->where('fishing_spot_id', $reservation->fishing_spot_id)
                ->where('starts_at', '<', $reservation->ends_at->toDateTimeString())
                ->where('ends_at', '>', $reservation->starts_at->toDateTimeString())
                ->where('status', ReservationStatus::Confirmed->value)
                ->exists();

            if ($hasConfirmedOverlap) {
                throw new PaymentConfirmationFailed('spot_already_confirmed');
            }

            $reservation->update([
                'status' => ReservationStatus::Confirmed,
                'hold_expires_at' => null,
            ]);

            $lockedBooking->update([
                'status' => BookingStatus::Confirmed,
                'confirmed_at' => now(),
            ]);

            $lockedBooking->refresh();

            $this->recordAuditLog->handle(
                action: 'booking.confirmed',
                subject: $lockedBooking,
                organization: $lockedBooking->organization,
                before: [
                    'booking_status' => BookingStatus::PaymentPending->value,
                    'reservation_status' => ReservationStatus::Hold->value,
                ],
                after: [
                    'booking_status' => $lockedBooking->status->value,
                    'reservation_status' => $reservation->status->value,
                    'booking_uuid' => $lockedBooking->uuid,
                    'reservation_uuid' => $reservation->uuid,
                ],
            );

            return $lockedBooking;
        });

        if ($holdExpired) {
            throw new PaymentConfirmationFailed('hold_expired');
        }

        assert($confirmedBooking instanceof Booking);

        $this->dispatchBookingNotification->confirmed($confirmedBooking);

        return $confirmedBooking;
    }
}
