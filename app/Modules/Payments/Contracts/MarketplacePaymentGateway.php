<?php

namespace App\Modules\Payments\Contracts;

use App\Modules\Bookings\Models\Booking;
use App\Modules\Payments\DTO\PaymentEvent;
use App\Modules\Payments\DTO\PaymentSession;
use App\Modules\Payments\DTO\ProviderPaymentState;
use App\Modules\Payments\DTO\ProviderRefundResult;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\Refund;

interface MarketplacePaymentGateway
{
    public function provider(): string;

    public function isConfigured(): bool;

    public function createPayment(Booking $booking, Payment $payment): PaymentSession;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string|null>  $headers
     */
    public function parseWebhook(array $payload, array $headers = [], ?string $rawPayload = null): PaymentEvent;

    public function refund(Payment $payment, Refund $refund): ProviderRefundResult;

    public function retrievePayment(Payment $payment): ProviderPaymentState;
}
