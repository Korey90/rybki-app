<?php

namespace App\Modules\Bookings\Enums;

enum BookingStatus: string
{
    case PaymentPending = 'PAYMENT_PENDING';
    case Confirmed = 'CONFIRMED';
    case Cancelled = 'CANCELLED';
    case PaymentFailed = 'PAYMENT_FAILED';
    case PartiallyRefunded = 'PARTIALLY_REFUNDED';
    case Refunded = 'REFUNDED';
}
