<?php

namespace App\Modules\Bookings\Enums;

enum BookingNotificationType: string
{
    case PaymentPending = 'booking.payment_pending';
    case Confirmed = 'booking.confirmed';
    case PaymentFailed = 'payment.failed';
    case Cancelled = 'booking.cancelled';
    case Reminder = 'booking.reminder';
}
