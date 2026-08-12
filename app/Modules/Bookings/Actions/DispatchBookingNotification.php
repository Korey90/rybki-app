<?php

namespace App\Modules\Bookings\Actions;

use App\Models\User;
use App\Modules\Bookings\Enums\BookingNotificationType;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Bookings\Models\BookingNotificationLog;
use App\Notifications\Bookings\BookingCancelledNotification;
use App\Notifications\Bookings\BookingConfirmedNotification;
use App\Notifications\Bookings\BookingPaymentPendingNotification;
use App\Notifications\Bookings\BookingReminderNotification;
use App\Notifications\Bookings\PaymentFailedNotification;
use Illuminate\Database\QueryException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class DispatchBookingNotification
{
    public function paymentPending(Booking $booking): bool
    {
        return $this->dispatch(
            $booking,
            BookingNotificationType::PaymentPending,
            new BookingPaymentPendingNotification($booking->id),
        );
    }

    public function confirmed(Booking $booking): bool
    {
        return $this->dispatch(
            $booking,
            BookingNotificationType::Confirmed,
            new BookingConfirmedNotification($booking->id),
        );
    }

    public function paymentFailed(Booking $booking): bool
    {
        return $this->dispatch(
            $booking,
            BookingNotificationType::PaymentFailed,
            new PaymentFailedNotification($booking->id),
        );
    }

    public function cancelled(Booking $booking): bool
    {
        return $this->dispatch(
            $booking,
            BookingNotificationType::Cancelled,
            new BookingCancelledNotification($booking->id),
        );
    }

    public function reminder(Booking $booking): bool
    {
        return $this->dispatch(
            $booking,
            BookingNotificationType::Reminder,
            new BookingReminderNotification($booking->id),
        );
    }

    private function dispatch(Booking $booking, BookingNotificationType $type, Notification $notification): bool
    {
        $booking->loadMissing('user');
        $recipientEmail = trim($booking->customer_email);

        if ($recipientEmail === '') {
            return false;
        }

        $channels = $booking->user instanceof User ? ['mail', 'database'] : ['mail'];

        try {
            BookingNotificationLog::create([
                'booking_id' => $booking->id,
                'type' => $type,
                'recipient_email' => $recipientEmail,
                'channels' => $channels,
                'sent_at' => now(),
            ]);
        } catch (QueryException $exception) {
            if ($this->isDuplicateLog($exception)) {
                return false;
            }

            throw $exception;
        }

        if ($booking->user instanceof User) {
            $booking->user->notify($notification);

            return true;
        }

        NotificationFacade::route('mail', $recipientEmail)->notify($notification);

        return true;
    }

    private function isDuplicateLog(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $message = $exception->getMessage();

        return in_array($sqlState, ['23000', '23505'], true)
            || str_contains($message, 'booking_notification_unique');
    }
}
