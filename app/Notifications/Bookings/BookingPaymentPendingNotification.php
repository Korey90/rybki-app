<?php

namespace App\Notifications\Bookings;

use App\Notifications\Bookings\Concerns\FormatsBookingNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingPaymentPendingNotification extends Notification implements ShouldQueue
{
    use FormatsBookingNotification, Queueable;

    public function __construct(public readonly int $bookingId) {}

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking();

        return (new MailMessage)
            ->subject('Rezerwacja oczekuje na płatność')
            ->greeting('Rezerwacja została utworzona')
            ->line('Łowisko: '.$this->venueName($booking))
            ->line('Termin: '.$this->reservationTime($booking))
            ->line('Kwota: '.$this->money($booking->total_amount, $booking->currency))
            ->line('Dokończ płatność, aby potwierdzić stanowisko.')
            ->action('Przejdź do rezerwacji', $this->bookingUrl($booking));
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(object $notifiable): array
    {
        $booking = $this->booking();

        return $this->databasePayload(
            $booking,
            'Rezerwacja oczekuje na płatność',
            'Dokończ płatność za rezerwację '.$booking->booking_number.'.',
        );
    }
}
