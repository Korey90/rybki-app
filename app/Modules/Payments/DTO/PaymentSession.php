<?php

namespace App\Modules\Payments\DTO;

final readonly class PaymentSession
{
    public function __construct(
        public string $provider,
        public string $providerPaymentId,
        public string $checkoutUrl,
        public int $amount,
        public string $currency,
    ) {}

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'provider_payment_id' => $this->providerPaymentId,
            'checkout_url' => $this->checkoutUrl,
            'amount' => $this->amount,
            'currency' => $this->currency,
        ];
    }
}
