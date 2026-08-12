<?php

namespace App\Modules\Payments\DTO;

final readonly class ProviderRefundResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public ?string $providerRefundId,
        public string $status,
        public array $payload = [],
    ) {}
}
