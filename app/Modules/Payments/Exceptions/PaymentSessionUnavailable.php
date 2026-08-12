<?php

namespace App\Modules\Payments\Exceptions;

use RuntimeException;

class PaymentSessionUnavailable extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("Payment session unavailable: {$reason}");
    }
}
