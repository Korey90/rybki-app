<?php

namespace App\Modules\Payments\DTO;

final readonly class PaymentEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $provider,
        public string $providerEventId,
        public string $eventType,
        public string $providerPaymentId,
        public string $status,
        public array $payload,
    ) {}
}
