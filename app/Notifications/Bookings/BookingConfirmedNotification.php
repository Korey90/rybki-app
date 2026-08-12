<?php

namespace App\Notifications\Bookings;

use App\Notifications\Bookings\Concerns\FormatsBookingNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingConfirmedNotification extends Notification implements ShouldQueue
{
    use FormatsBookingNotification, Queueable;

    public function __construct(public readonly int $bookingId) {}

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking();

        return (new MailMessage)
            ->subject('Rezerwacja została potwierdzona')
            ->greeting('Płatność przyjęta')
            ->line('Rezerwacja '.$booking->booking_number.' jest potwierdzona.')
            ->line('Łowisko: '.$this->venueName($booking))
            ->line('Termin: '.$this->reservationTime($booking))
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
            'Rezerwacja potwierdzona',
            'Twoja rezerwacja '.$booking->booking_number.' została potwierdzona.',
        );
    }
}
