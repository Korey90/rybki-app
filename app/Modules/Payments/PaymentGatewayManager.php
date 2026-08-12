<?php

namespace App\Modules\Payments;

use App\Modules\Payments\Contracts\MarketplacePaymentGateway;
use App\Modules\Payments\Enums\PaymentProvider;
use App\Modules\Payments\Gateways\PayUMarketplaceGateway;
use App\Modules\Payments\Gateways\StripeConnectGateway;
use App\Modules\Payments\Gateways\TestPaymentGateway;
use InvalidArgumentException;

class PaymentGatewayManager
{
    public function for(string $provider): MarketplacePaymentGateway
    {
        return match ($provider) {
            PaymentProvider::Test->value => app(TestPaymentGateway::class),
            PaymentProvider::StripeConnect->value => app(StripeConnectGateway::class),
            PaymentProvider::PayUMarketplace->value => app(PayUMarketplaceGateway::class),
            default => throw new InvalidArgumentException("Unsupported payment provider [{$provider}]."),
        };
    }

    /**
     * @return list<string>
     */
    public function providers(): array
    {
        return array_map(
            fn (PaymentProvider $provider): string => $provider->value,
            PaymentProvider::cases(),
        );
    }
}
