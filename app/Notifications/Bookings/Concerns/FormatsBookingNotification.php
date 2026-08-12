<?php

namespace App\Notifications\Bookings\Concerns;

use App\Models\User;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Bookings\Models\SpotReservation;
use App\Modules\Fisheries\Models\FishingVenue;

trait FormatsBookingNotification
{
    protected function booking(): Booking
    {
        return Booking::query()
            ->with(['venue', 'spotReservations.spot'])
            ->findOrFail($this->bookingId);
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? ['mail', 'database'] : ['mail'];
    }

    protected function bookingUrl(Booking $booking): string
    {
        return $booking->user_id === null
            ? route('checkout.show', $booking)
            : route('account.bookings.show', $booking);
    }

    protected function venueName(Booking $booking): string
    {
        $venue = $booking->venue;

        return $venue instanceof FishingVenue ? $venue->name : 'łowisko';
    }

    protected function reservationTime(Booking $booking): string
    {
        $reservation = $booking->spotReservations->sortBy('starts_at')->first();

        if (! $reservation instanceof SpotReservation) {
            return 'termin rezerwacji';
        }

        return $reservation->starts_at->format('d.m.Y H:i').' - '.$reservation->ends_at->format('d.m.Y H:i');
    }

    protected function money(int $amount, string $currency): string
    {
        return number_format($amount / 100, 2, ',', ' ').' '.$currency;
    }

    /**
     * @return array<string, int|string|null>
     */
    protected function databasePayload(Booking $booking, string $title, string $message): array
    {
        return [
            'booking_uuid' => $booking->uuid,
            'booking_number' => $booking->booking_number,
            'booking_status' => $booking->status->value,
            'title' => $title,
            'message' => $message,
            'url' => $this->bookingUrl($booking),
        ];
    }
}
