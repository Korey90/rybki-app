<?php

namespace App\Modules\Bookings\DTO;

use Carbon\CarbonInterface;

class BookingCancellationQuote
{
    public function __construct(
        public readonly bool $canCancel,
        public readonly ?string $reason,
        public readonly int $refundAmount,
        public readonly int $refundPercentage,
        public readonly string $currency,
        public readonly string $policyName,
        public readonly int $freeCancellationHours,
        public readonly int $lateRefundPercentage,
        public readonly ?CarbonInterface $freeCancellationUntil,
    ) {}
}
