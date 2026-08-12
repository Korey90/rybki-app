<?php

namespace App\Modules\Payments\Exceptions;

use RuntimeException;

class PaymentProviderRequestFailed extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("Payment provider request failed: {$reason}");
    }
}
