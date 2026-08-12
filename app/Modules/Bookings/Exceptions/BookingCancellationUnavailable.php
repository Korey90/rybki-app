<?php

namespace App\Modules\Bookings\Exceptions;

use RuntimeException;

class BookingCancellationUnavailable extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("Booking cancellation unavailable: {$reason}");
    }
}
