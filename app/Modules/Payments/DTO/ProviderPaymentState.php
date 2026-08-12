<?php

namespace App\Modules\Payments\DTO;

final readonly class ProviderPaymentState
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $providerPaymentId,
        public string $status,
        public array $payload = [],
        public ?string $checkoutUrl = null,
    ) {}
}
