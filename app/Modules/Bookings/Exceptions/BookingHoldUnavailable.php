<?php

namespace App\Modules\Bookings\Exceptions;

use RuntimeException;

class BookingHoldUnavailable extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("Booking hold unavailable: {$reason}");
    }
}
