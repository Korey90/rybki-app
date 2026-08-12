<?php

namespace App\Modules\Payments\Clients;

use App\Modules\Payments\Contracts\PayUMarketplaceClient;
use App\Modules\Payments\Exceptions\PaymentProviderRequestFailed;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class PayUMarketplaceHttpClient implements PayUMarketplaceClient
{
    public function isConfigured(): bool
    {
        return filled($this->clientId())
            && filled($this->clientSecret())
            && filled($this->merchantPosId());
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createOrder(array $payload, string $idempotencyKey): array
    {
        try {
            $response = $this->request()
                ->withHeaders(['Idempotency-Key' => $idempotencyKey])
                ->post($this->baseUrl().'/api/v2_1/orders', $payload)
                ->throw()
                ->json();
        } catch (Throwable $exception) {
            throw $this->providerException($exception, 'payu_order_request_failed');
        }

        return $this->arrayResponse($response);
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveOrder(string $orderId): array
    {
        try {
            $response = $this->request()
                ->get($this->baseUrl().'/api/v2_1/orders/'.$orderId)
                ->throw()
                ->json();
        } catch (Throwable $exception) {
            throw $this->providerException($exception, 'payu_order_retrieve_failed');
        }

        return $this->arrayResponse($response);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createRefund(string $orderId, array $payload, string $idempotencyKey): array
    {
        try {
            $response = $this->request()
                ->withHeaders(['Idempotency-Key' => $idempotencyKey])
                ->post($this->baseUrl().'/api/v2_1/orders/'.$orderId.'/refunds', $payload)
                ->throw()
                ->json();
        } catch (Throwable $exception) {
            throw $this->providerException($exception, 'payu_refund_request_failed');
        }

        return $this->arrayResponse($response);
    }

    private function request(): PendingRequest
    {
        return Http::withToken($this->accessToken())
            ->acceptJson()
            ->asJson()
            ->timeout(20);
    }

    private function accessToken(): string
    {
        if (! $this->isConfigured()) {
            throw new PaymentProviderRequestFailed('payu_not_configured');
        }

        $cacheKey = 'payu_marketplace_access_token';

        return Cache::remember($cacheKey, now()->addHours(10), function (): string {
            try {
                $response = Http::asForm()
                    ->acceptJson()
                    ->timeout(20)
                    ->post($this->baseUrl().'/pl/standard/user/oauth/authorize', [
                        'grant_type' => 'client_credentials',
                        'client_id' => $this->clientId(),
                        'client_secret' => $this->clientSecret(),
                    ])
                    ->throw()
                    ->json();
            } catch (Throwable $exception) {
                throw $this->providerException($exception, 'payu_access_token_request_failed');
            }

            $token = Arr::get($this->arrayResponse($response), 'access_token');

            if (! is_string($token) || $token === '') {
                throw new PaymentProviderRequestFailed('payu_access_token_missing');
            }

            return $token;
        });
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.payu_marketplace.base_url', 'https://secure.snd.payu.com'), '/');
    }

    private function clientId(): ?string
    {
        $value = Arr::get(config('services.payu_marketplace'), 'client_id');

        return is_string($value) ? $value : null;
    }

    private function clientSecret(): ?string
    {
        $value = Arr::get(config('services.payu_marketplace'), 'client_secret');

        return is_string($value) ? $value : null;
    }

    private function merchantPosId(): ?string
    {
        $value = Arr::get(config('services.payu_marketplace'), 'pos_id');

        return is_string($value) ? $value : null;
    }

    private function providerException(Throwable $exception, string $fallbackReason): PaymentProviderRequestFailed
    {
        if ($exception instanceof PaymentProviderRequestFailed) {
            return $exception;
        }

        return new PaymentProviderRequestFailed($fallbackReason);
    }

    /**
     * @return array<string, mixed>
     */
    private function arrayResponse(mixed $response): array
    {
        if (! is_array($response)) {
            throw new PaymentProviderRequestFailed('payu_response_invalid');
        }

        return $response;
    }
}
