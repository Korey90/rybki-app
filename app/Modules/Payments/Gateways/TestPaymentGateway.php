<?php

namespace App\Modules\Payments\Gateways;

use App\Modules\Bookings\Models\Booking;
use App\Modules\Payments\Contracts\MarketplacePaymentGateway;
use App\Modules\Payments\DTO\PaymentEvent;
use App\Modules\Payments\DTO\PaymentSession;
use App\Modules\Payments\DTO\ProviderPaymentState;
use App\Modules\Payments\DTO\ProviderRefundResult;
use App\Modules\Payments\Enums\PaymentProvider;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Enums\RefundStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\Refund;

class TestPaymentGateway implements MarketplacePaymentGateway
{
    public function provider(): string
    {
        return PaymentProvider::Test->value;
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function createPayment(Booking $booking, Payment $payment): PaymentSession
    {
        return new PaymentSession(
            provider: $this->provider(),
            providerPaymentId: (string) $payment->provider_payment_id,
            checkoutUrl: url("/test-payments/{$payment->provider_payment_id}"),
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
        return new PaymentEvent(
            provider: $this->provider(),
            providerEventId: (string) $payload['provider_event_id'],
            eventType: (string) $payload['event_type'],
            providerPaymentId: (string) $payload['provider_payment_id'],
            status: (string) ($payload['status'] ?? PaymentStatus::Succeeded->value),
            payload: $payload,
        );
    }

    public function refund(Payment $payment, Refund $refund): ProviderRefundResult
    {
        return new ProviderRefundResult(
            providerRefundId: null,
            status: RefundStatus::Succeeded->value,
            payload: [
                'provider' => $this->provider(),
                'payment_id' => $payment->provider_payment_id,
                'refund_id' => $refund->id,
            ],
        );
    }

    public function retrievePayment(Payment $payment): ProviderPaymentState
    {
        return new ProviderPaymentState(
            providerPaymentId: (string) $payment->provider_payment_id,
            status: $payment->status->value,
            payload: [
                'provider' => $this->provider(),
                'local_status' => $payment->status->value,
            ],
            checkoutUrl: $payment->checkout_url,
        );
    }
}
