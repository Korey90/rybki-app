<?php

namespace App\Modules\Payments\Gateways;

use App\Modules\Bookings\Models\Booking;
use App\Modules\Payments\Contracts\MarketplacePaymentGateway;
use App\Modules\Payments\Contracts\PayUMarketplaceClient;
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
use Illuminate\Support\Str;

class PayUMarketplaceGateway implements MarketplacePaymentGateway
{
    public function __construct(private readonly PayUMarketplaceClient $client) {}

    public function provider(): string
    {
        return PaymentProvider::PayUMarketplace->value;
    }

    public function isConfigured(): bool
    {
        return $this->client->isConfigured() && filled($this->secondKey()) && filled($this->merchantPosId());
    }

    public function createPayment(Booking $booking, Payment $payment): PaymentSession
    {
        $providerAccount = $this->providerAccount($booking);
        $order = $this->client->createOrder(
            $this->orderPayload($booking, $payment, $providerAccount),
            $payment->idempotency_key,
        );

        $providerPaymentId = Arr::get($order, 'orderId');
        $checkoutUrl = Arr::get($order, 'redirectUri');

        if (! is_string($providerPaymentId) || ! is_string($checkoutUrl)) {
            throw new PaymentProviderRequestFailed('payu_order_response_invalid');
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

        $order = Arr::get($payload, 'order');

        if (! is_array($order)) {
            throw new PaymentProviderRequestFailed('payu_webhook_order_missing');
        }

        $orderId = Arr::get($order, 'orderId');
        $status = Arr::get($order, 'status');

        if (! is_string($orderId) || ! is_string($status)) {
            throw new PaymentProviderRequestFailed('payu_webhook_order_invalid');
        }

        return new PaymentEvent(
            provider: $this->provider(),
            providerEventId: $this->providerEventId($payload, $orderId, $status),
            eventType: 'order.'.Str::lower($status),
            providerPaymentId: $orderId,
            status: $this->orderStatus($status),
            payload: $payload,
        );
    }

    public function refund(Payment $payment, Refund $refund): ProviderRefundResult
    {
        if (! is_string($payment->provider_payment_id) || $payment->provider_payment_id === '') {
            throw new PaymentProviderRequestFailed('payu_order_id_missing');
        }

        $providerAccount = $this->providerAccount($payment->booking);
        $response = $this->client->createRefund(
            $payment->provider_payment_id,
            $this->refundPayload($payment, $refund, $providerAccount),
            'refund-'.$refund->id,
        );
        $providerRefundId = Arr::get($response, 'refund.refundId');
        $status = Arr::get($response, 'refund.status');

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

        $order = $this->client->retrieveOrder($payment->provider_payment_id);
        $orderData = Arr::get($order, 'orders.0');

        if (! is_array($orderData)) {
            $orderData = Arr::get($order, 'order');
        }

        $status = is_array($orderData) ? Arr::get($orderData, 'status') : null;

        return new ProviderPaymentState(
            providerPaymentId: $payment->provider_payment_id,
            status: $this->orderStatus(is_string($status) ? $status : 'PENDING'),
            payload: $order,
            checkoutUrl: $payment->checkout_url,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function orderPayload(Booking $booking, Payment $payment, ProviderAccount $providerAccount): array
    {
        $customerName = trim($booking->customer_name);
        $nameParts = preg_split('/\s+/', $customerName) ?: [];
        $firstName = (string) ($nameParts[0] ?? $booking->customer_name);
        $lastName = count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : '-';

        return [
            'merchantPosId' => $this->merchantPosId(),
            'customerIp' => request()->ip() ?? '127.0.0.1',
            'continueUrl' => route('checkout.show', $booking).'?payment=payu_return',
            'notifyUrl' => route('api.webhooks.payu-marketplace'),
            'currencyCode' => $booking->currency,
            'totalAmount' => (string) $booking->total_amount,
            'extOrderId' => $payment->uuid,
            'description' => 'Rezerwacja '.$booking->booking_number,
            'buyer' => [
                'email' => $booking->customer_email,
                'phone' => $booking->customer_phone,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'language' => 'pl',
                'extCustomerId' => 'payer-'.$booking->uuid,
            ],
            'products' => [
                [
                    'name' => 'Rezerwacja '.$booking->booking_number,
                    'unitPrice' => (string) $booking->total_amount,
                    'quantity' => '1',
                ],
            ],
            'shoppingCarts' => [
                [
                    'extCustomerId' => $providerAccount->external_account_id,
                    'amount' => (string) $booking->total_amount,
                    'fee' => (string) $booking->platform_fee_amount,
                    'products' => [
                        [
                            'name' => 'Rezerwacja '.$booking->booking_number,
                            'unitPrice' => (string) $booking->total_amount,
                            'quantity' => '1',
                            'virtual' => true,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function refundPayload(Payment $payment, Refund $refund, ProviderAccount $providerAccount): array
    {
        return [
            'refund' => [
                'description' => $refund->reason ?: 'Zwrot rezerwacji',
                'amount' => (string) $refund->amount,
                'extRefundId' => 'refund-'.$refund->id,
                'currencyCode' => $refund->currency,
                'bankDescription' => 'Zwrot '.$payment->booking->booking_number,
                'type' => 'REFUND_PAYMENT_STANDARD',
                'extCustomerId' => $providerAccount->external_account_id,
            ],
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
        $secondKey = $this->secondKey();

        if (! filled($secondKey)) {
            throw new PaymentWebhookSignatureInvalid('payu_second_key_missing');
        }

        if (! is_string($rawPayload) || $rawPayload === '') {
            throw new PaymentWebhookSignatureInvalid('payu_raw_payload_missing');
        }

        $header = $this->header($headers, 'openpayu-signature');

        if (! is_string($header) || $header === '') {
            throw new PaymentWebhookSignatureInvalid('payu_signature_header_missing');
        }

        $signature = $this->signaturePart($header, 'signature');
        $algorithm = Str::lower((string) $this->signaturePart($header, 'algorithm'));

        if (! is_string($signature) || $signature === '' || $algorithm !== 'md5') {
            throw new PaymentWebhookSignatureInvalid('payu_signature_malformed');
        }

        $expected = md5($rawPayload.$secondKey);

        if (! hash_equals($expected, $signature)) {
            throw new PaymentWebhookSignatureInvalid('payu_signature_mismatch');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function providerEventId(array $payload, string $orderId, string $status): string
    {
        $paymentId = null;
        $properties = Arr::get($payload, 'properties', []);

        if (is_array($properties)) {
            foreach ($properties as $property) {
                if (! is_array($property) || Arr::get($property, 'name') !== 'PAYMENT_ID') {
                    continue;
                }

                $paymentId = Arr::get($property, 'value');
                break;
            }
        }

        return implode(':', array_filter([
            $orderId,
            $status,
            is_string($paymentId) ? $paymentId : null,
        ]));
    }

    private function orderStatus(string $status): string
    {
        return match ($status) {
            'COMPLETED' => PaymentStatus::Succeeded->value,
            'CANCELED' => PaymentStatus::Failed->value,
            'PENDING', 'WAITING_FOR_CONFIRMATION' => PaymentStatus::Processing->value,
            default => PaymentStatus::Processing->value,
        };
    }

    private function refundStatus(?string $status): string
    {
        return match ($status) {
            'FINALIZED', 'COMPLETED' => RefundStatus::Succeeded->value,
            'CANCELED', 'ERROR' => RefundStatus::Failed->value,
            default => RefundStatus::Processing->value,
        };
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

    private function signaturePart(string $header, string $part): ?string
    {
        foreach (explode(';', $header) as $value) {
            [$key, $signatureValue] = array_pad(explode('=', trim($value), 2), 2, null);

            if ($key === $part && is_string($signatureValue)) {
                return $signatureValue;
            }
        }

        return null;
    }

    private function merchantPosId(): ?string
    {
        $value = config('services.payu_marketplace.pos_id');

        return is_string($value) ? $value : null;
    }

    private function secondKey(): ?string
    {
        $value = config('services.payu_marketplace.second_key');

        return is_string($value) ? $value : null;
    }
}
