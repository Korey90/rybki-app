<?php

namespace App\Notifications\Bookings;

use App\Notifications\Bookings\Concerns\FormatsBookingNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingCancelledNotification extends Notification implements ShouldQueue
{
    use FormatsBookingNotification, Queueable;

    public function __construct(public readonly int $bookingId) {}

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking();

        return (new MailMessage)
            ->subject('Rezerwacja została anulowana')
            ->greeting('Rezerwacja anulowana')
            ->line('Rezerwacja '.$booking->booking_number.' została anulowana.')
            ->line('Łowisko: '.$this->venueName($booking))
            ->line('Termin: '.$this->reservationTime($booking))
            ->action('Zobacz szczegóły', $this->bookingUrl($booking));
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(object $notifiable): array
    {
        $booking = $this->booking();

        return $this->databasePayload(
            $booking,
            'Rezerwacja anulowana',
            'Rezerwacja '.$booking->booking_number.' została anulowana.',
        );
    }
}
