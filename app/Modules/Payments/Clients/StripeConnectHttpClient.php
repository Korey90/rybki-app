<?php

namespace App\Modules\Payments\Clients;

use App\Modules\Payments\Contracts\StripeConnectClient;
use App\Modules\Payments\Exceptions\PaymentProviderRequestFailed;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Throwable;

class StripeConnectHttpClient implements StripeConnectClient
{
    public function isConfigured(): bool
    {
        return filled($this->secretKey());
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createCheckoutSession(array $payload, string $idempotencyKey): array
    {
        try {
            $response = $this->request()
                ->asForm()
                ->withHeaders(['Idempotency-Key' => $idempotencyKey])
                ->post($this->apiBaseUrl().'/v1/checkout/sessions', $payload)
                ->throw()
                ->json();
        } catch (Throwable $exception) {
            throw $this->providerException($exception, 'stripe_checkout_request_failed');
        }

        return $this->arrayResponse($response);
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveCheckoutSession(string $sessionId): array
    {
        try {
            $response = $this->request()
                ->get($this->apiBaseUrl().'/v1/checkout/sessions/'.$sessionId, [
                    'expand' => ['payment_intent'],
                ])
                ->throw()
                ->json();
        } catch (Throwable $exception) {
            throw $this->providerException($exception, 'stripe_checkout_retrieve_failed');
        }

        return $this->arrayResponse($response);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createRefund(array $payload, string $idempotencyKey): array
    {
        try {
            $response = $this->request()
                ->asForm()
                ->withHeaders(['Idempotency-Key' => $idempotencyKey])
                ->post($this->apiBaseUrl().'/v1/refunds', $payload)
                ->throw()
                ->json();
        } catch (Throwable $exception) {
            throw $this->providerException($exception, 'stripe_refund_request_failed');
        }

        return $this->arrayResponse($response);
    }

    private function request(): PendingRequest
    {
        $secretKey = $this->secretKey();

        if (! filled($secretKey)) {
            throw new PaymentProviderRequestFailed('stripe_not_configured');
        }

        return Http::withToken($secretKey)
            ->acceptJson()
            ->timeout(20);
    }

    private function apiBaseUrl(): string
    {
        return rtrim((string) config('services.stripe_connect.api_base_url', 'https://api.stripe.com'), '/');
    }

    private function secretKey(): ?string
    {
        $secretKey = Arr::get(config('services.stripe_connect'), 'secret_key');

        return is_string($secretKey) ? $secretKey : null;
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
            throw new PaymentProviderRequestFailed('stripe_response_invalid');
        }

        return $response;
    }
}
