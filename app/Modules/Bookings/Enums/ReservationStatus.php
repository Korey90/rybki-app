<?php

namespace App\Modules\Bookings\Enums;

enum ReservationStatus: string
{
    case Hold = 'HOLD';
    case Confirmed = 'CONFIRMED';
    case Cancelled = 'CANCELLED';
    case Expired = 'EXPIRED';
}
