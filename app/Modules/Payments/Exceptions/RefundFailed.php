<?php

namespace App\Modules\Payments\Exceptions;

use RuntimeException;

class RefundFailed extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("Refund failed: {$reason}");
    }
}
