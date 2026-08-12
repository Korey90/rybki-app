<?php

namespace App\Notifications\Bookings;

use App\Notifications\Bookings\Concerns\FormatsBookingNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use FormatsBookingNotification, Queueable;

    public function __construct(public readonly int $bookingId) {}

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking();

        return (new MailMessage)
            ->subject('Płatność nie powiodła się')
            ->greeting('Nie udało się potwierdzić płatności')
            ->line('Rezerwacja: '.$booking->booking_number)
            ->line('Łowisko: '.$this->venueName($booking))
            ->line('Termin: '.$this->reservationTime($booking))
            ->line('Jeśli hold nadal jest aktywny, możesz ponowić płatność z poziomu rezerwacji.')
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
            'Płatność nieudana',
            'Płatność dla rezerwacji '.$booking->booking_number.' nie powiodła się.',
        );
    }
}
