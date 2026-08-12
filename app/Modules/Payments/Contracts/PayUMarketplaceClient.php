<?php

namespace App\Modules\Payments\Contracts;

interface PayUMarketplaceClient
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createOrder(array $payload, string $idempotencyKey): array;

    /**
     * @return array<string, mixed>
     */
    public function retrieveOrder(string $orderId): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createRefund(string $orderId, array $payload, string $idempotencyKey): array;

    public function isConfigured(): bool;
}
