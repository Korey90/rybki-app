<?php

namespace App\Modules\Payments\Enums;

enum PaymentProvider: string
{
    case Test = 'test';
    case StripeConnect = 'stripe_connect';
    case PayUMarketplace = 'payu_marketplace';

    public function label(): string
    {
        return match ($this) {
            self::Test => 'Testowy',
            self::StripeConnect => 'Stripe Connect',
            self::PayUMarketplace => 'PayU Marketplace',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Test => 'Lokalny provider do developmentu i testow automatycznych.',
            self::StripeConnect => 'Sandbox Stripe Checkout z destination charges dla connected accounts.',
            self::PayUMarketplace => 'Sandbox PayU Europe REST API z rozdzialem srodkow do submerchantow.',
        };
    }
}
