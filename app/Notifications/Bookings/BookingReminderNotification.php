<?php

namespace App\Notifications\Bookings;

use App\Notifications\Bookings\Concerns\FormatsBookingNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingReminderNotification extends Notification implements ShouldQueue
{
    use FormatsBookingNotification, Queueable;

    public function __construct(public readonly int $bookingId) {}

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking();

        return (new MailMessage)
            ->subject('Przypomnienie o rezerwacji')
            ->greeting('Twoja rezerwacja jest już blisko')
            ->line('Rezerwacja: '.$booking->booking_number)
            ->line('Łowisko: '.$this->venueName($booking))
            ->line('Termin: '.$this->reservationTime($booking))
            ->line('Sprawdź szczegóły i zasady przyjazdu przed wyjazdem.')
            ->action('Zobacz rezerwację', $this->bookingUrl($booking));
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(object $notifiable): array
    {
        $booking = $this->booking();

        return $this->databasePayload(
            $booking,
            'Przypomnienie o rezerwacji',
            'Rezerwacja '.$booking->booking_number.' rozpocznie się '.$this->reservationTime($booking).'.',
        );
    }
}
