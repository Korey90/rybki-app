<?php

namespace App\Modules\Payments\Exceptions;

use RuntimeException;

class PaymentWebhookSignatureInvalid extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("Invalid payment webhook signature: {$reason}");
    }
}
