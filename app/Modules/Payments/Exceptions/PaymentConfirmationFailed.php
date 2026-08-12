<?php

namespace App\Modules\Payments\Exceptions;

use RuntimeException;

class PaymentConfirmationFailed extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("Payment confirmation failed: {$reason}");
    }
}
