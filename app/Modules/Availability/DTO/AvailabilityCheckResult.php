<?php

namespace App\Modules\Availability\DTO;

final readonly class AvailabilityCheckResult
{
    public function __construct(
        public bool $available,
        public ?string $reason = null,
    ) {}
}
