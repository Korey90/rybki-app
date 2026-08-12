<?php

namespace App\Modules\Payments\Gateways;

use App\Modules\Bookings\Models\Booking;
use App\Modules\Payments\Contracts\MarketplacePaymentGateway;
use App\Modules\Payments\Contracts\StripeConnectClient;
use App\Modules\Payments\DTO\PaymentEvent;
use App\Modules\Payments\DTO\PaymentSession;
use App\Modules\Payments\DTO\ProviderPaymentState;
use App\Modules\Payments\DTO\ProviderRefundResult;
use App\Modules\Payments\Enums\PaymentProvider;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Enums\ProviderAccountOnboardingStatus;
use App\Modules\Payments\Enums\RefundStatus;
use App\Modules\Payments\Exceptions\PaymentProviderRequestFailed;
use App\Modules\Payments\Exceptions\PaymentSessionUnavailable;
use App\Modules\Payments\Exceptions\PaymentWebhookSignatureInvalid;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\ProviderAccount;
use App\Modules\Payments\Models\Refund;
use Illuminate\Support\Arr;

class StripeConnectGateway implements MarketplacePaymentGateway
{
    public function __construct(private readonly StripeConnectClient $client) {}

    public function provider(): string
    {
        return PaymentProvider::StripeConnect->value;
    }

    public function isConfigured(): bool
    {
        return $this->client->isConfigured() && filled($this->webhookSecret());
    }

    public function createPayment(Booking $booking, Payment $payment): PaymentSession
    {
        $providerAccount = $this->providerAccount($booking);
        $session = $this->client->createCheckoutSession(
            $this->checkoutPayload($booking, $payment, $providerAccount),
            $payment->idempotency_key,
        );

        $providerPaymentId = Arr::get($session, 'id');
        $checkoutUrl = Arr::get($session, 'url');

        if (! is_string($providerPaymentId) || ! is_string($checkoutUrl)) {
            throw new PaymentProviderRequestFailed('stripe_checkout_session_invalid');
        }

        return new PaymentSession(
            provider: $this->provider(),
            providerPaymentId: $providerPaymentId,
            checkoutUrl: $checkoutUrl,
            amount: $booking->total_amount,
            currency: $booking->currency,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string|null>  $headers
     */
    public function parseWebhook(array $payload, array $headers = [], ?string $rawPayload = null): PaymentEvent
    {
        $this->verifySignature($headers, $rawPayload);

        $eventId = Arr::get($payload, 'id');
        $eventType = Arr::get($payload, 'type');
        $object = Arr::get($payload, 'data.object');

        if (! is_string($eventId) || ! is_string($eventType) || ! is_array($object)) {
            throw new PaymentProviderRequestFailed('stripe_webhook_payload_invalid');
        }

        $providerPaymentId = Arr::get($object, 'id');

        if (! is_string($providerPaymentId)) {
            throw new PaymentProviderRequestFailed('stripe_checkout_session_missing');
        }

        return new PaymentEvent(
            provider: $this->provider(),
            providerEventId: $eventId,
            eventType: $eventType,
            providerPaymentId: $providerPaymentId,
            status: $this->eventStatus($eventType, $object),
            payload: $payload,
        );
    }

    public function refund(Payment $payment, Refund $refund): ProviderRefundResult
    {
        $paymentIntentId = $this->paymentIntentId($payment);
        $payload = [
            'payment_intent' => $paymentIntentId,
            'amount' => $refund->amount,
            'metadata' => [
                'booking_id' => (string) $payment->booking_id,
                'payment_uuid' => $payment->uuid,
                'refund_id' => (string) $refund->id,
            ],
            'reverse_transfer' => 'true',
            'refund_application_fee' => 'true',
        ];

        $response = $this->client->createRefund($payload, 'refund-'.$refund->id);
        $providerRefundId = Arr::get($response, 'id');
        $status = Arr::get($response, 'status');

        return new ProviderRefundResult(
            providerRefundId: is_string($providerRefundId) ? $providerRefundId : null,
            status: $this->refundStatus(is_string($status) ? $status : null),
            payload: $response,
        );
    }

    public function retrievePayment(Payment $payment): ProviderPaymentState
    {
        if (! is_string($payment->provider_payment_id) || $payment->provider_payment_id === '') {
            return new ProviderPaymentState(
                providerPaymentId: '',
                status: PaymentStatus::Created->value,
                payload: ['reason' => 'provider_payment_id_missing'],
            );
        }

        $session = $this->client->retrieveCheckoutSession($payment->provider_payment_id);

        return new ProviderPaymentState(
            providerPaymentId: $payment->provider_payment_id,
            status: $this->remoteSessionStatus($session),
            payload: $session,
            checkoutUrl: is_string(Arr::get($session, 'url')) ? Arr::get($session, 'url') : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function checkoutPayload(Booking $booking, Payment $payment, ProviderAccount $providerAccount): array
    {
        $metadata = [
            'booking_uuid' => $booking->uuid,
            'booking_number' => $booking->booking_number,
            'payment_uuid' => $payment->uuid,
        ];

        return [
            'mode' => 'payment',
            'client_reference_id' => $booking->uuid,
            'success_url' => route('checkout.show', $booking).'?payment=stripe_success&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('checkout.show', $booking).'?payment=stripe_cancelled',
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => strtolower($booking->currency),
                        'product_data' => [
                            'name' => 'Rezerwacja '.$booking->booking_number,
                            'metadata' => $metadata,
                        ],
                        'unit_amount' => $booking->total_amount,
                    ],
                    'quantity' => 1,
                ],
            ],
            'payment_intent_data' => [
                'application_fee_amount' => $booking->platform_fee_amount,
                'transfer_data' => [
                    'destination' => $providerAccount->external_account_id,
                ],
                'metadata' => $metadata,
            ],
            'metadata' => $metadata,
        ];
    }

    private function providerAccount(Booking $booking): ProviderAccount
    {
        $providerAccount = ProviderAccount::query()
            ->where('organization_id', $booking->organization_id)
            ->where('provider', $this->provider())
            ->where('onboarding_status', ProviderAccountOnboardingStatus::Enabled->value)
            ->where('payments_enabled', true)
            ->first();

        if (! $providerAccount instanceof ProviderAccount || blank($providerAccount->external_account_id)) {
            throw new PaymentSessionUnavailable('provider_account_not_ready');
        }

        return $providerAccount;
    }

    /**
     * @param  array<string, string|null>  $headers
     */
    private function verifySignature(array $headers, ?string $rawPayload): void
    {
        $secret = $this->webhookSecret();

        if (! filled($secret)) {
            throw new PaymentWebhookSignatureInvalid('stripe_webhook_secret_missing');
        }

        if (! is_string($rawPayload) || $rawPayload === '') {
            throw new PaymentWebhookSignatureInvalid('stripe_raw_payload_missing');
        }

        $header = $this->header($headers, 'stripe-signature');

        if (! is_string($header) || $header === '') {
            throw new PaymentWebhookSignatureInvalid('stripe_signature_header_missing');
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

            if ($key === 't') {
                $timestamp = $value;
            }

            if ($key === 'v1' && is_string($value)) {
                $signatures[] = $value;
            }
        }

        if (! is_string($timestamp) || $signatures === []) {
            throw new PaymentWebhookSignatureInvalid('stripe_signature_malformed');
        }

        $currentTimestamp = now()->getTimestamp();

        if (abs($currentTimestamp - (int) $timestamp) > 300) {
            throw new PaymentWebhookSignatureInvalid('stripe_signature_expired');
        }

        $expectedSignature = hash_hmac('sha256', $timestamp.'.'.$rawPayload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expectedSignature, $signature)) {
                return;
            }
        }

        throw new PaymentWebhookSignatureInvalid('stripe_signature_mismatch');
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function eventStatus(string $eventType, array $object): string
    {
        return match ($eventType) {
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded' => PaymentStatus::Succeeded->value,
            'checkout.session.async_payment_failed',
            'checkout.session.expired' => PaymentStatus::Failed->value,
            default => $this->remoteSessionStatus($object),
        };
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function remoteSessionStatus(array $session): string
    {
        $paymentStatus = Arr::get($session, 'payment_status');
        $status = Arr::get($session, 'status');

        if ($paymentStatus === 'paid') {
            return PaymentStatus::Succeeded->value;
        }

        if ($status === 'expired') {
            return PaymentStatus::Failed->value;
        }

        if ($status === 'complete') {
            return PaymentStatus::Succeeded->value;
        }

        return PaymentStatus::Processing->value;
    }

    private function refundStatus(?string $status): string
    {
        return match ($status) {
            'succeeded' => RefundStatus::Succeeded->value,
            'failed', 'canceled' => RefundStatus::Failed->value,
            default => RefundStatus::Processing->value,
        };
    }

    private function paymentIntentId(Payment $payment): string
    {
        $paymentIntentId = Arr::get($payment->provider_payload ?? [], 'payment_intent');

        if (is_array($paymentIntentId)) {
            $paymentIntentId = Arr::get($paymentIntentId, 'id');
        }

        if (is_string($paymentIntentId) && $paymentIntentId !== '') {
            return $paymentIntentId;
        }

        if (is_string($payment->provider_payment_id) && str_starts_with($payment->provider_payment_id, 'pi_')) {
            return $payment->provider_payment_id;
        }

        if (! is_string($payment->provider_payment_id) || $payment->provider_payment_id === '') {
            throw new PaymentProviderRequestFailed('stripe_payment_id_missing');
        }

        $session = $this->client->retrieveCheckoutSession($payment->provider_payment_id);
        $paymentIntentId = Arr::get($session, 'payment_intent');

        if (is_array($paymentIntentId)) {
            $paymentIntentId = Arr::get($paymentIntentId, 'id');
        }

        if (! is_string($paymentIntentId) || $paymentIntentId === '') {
            throw new PaymentProviderRequestFailed('stripe_payment_intent_missing');
        }

        return $paymentIntentId;
    }

    /**
     * @param  array<string, string|null>  $headers
     */
    private function header(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (strtolower($key) === $name) {
                return $value;
            }
        }

        return null;
    }

    private function webhookSecret(): ?string
    {
        $secret = config('services.stripe_connect.webhook_secret');

        return is_string($secret) ? $secret : null;
    }
}
