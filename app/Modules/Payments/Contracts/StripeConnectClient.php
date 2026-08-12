<?php

namespace App\Modules\Payments\Contracts;

interface StripeConnectClient
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createCheckoutSession(array $payload, string $idempotencyKey): array;

    /**
     * @return array<string, mixed>
     */
    public function retrieveCheckoutSession(string $sessionId): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createRefund(array $payload, string $idempotencyKey): array;

    public function isConfigured(): bool;
}
