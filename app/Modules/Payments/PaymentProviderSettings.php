<?php

namespace App\Modules\Payments;

use App\Models\User;
use App\Modules\Payments\Enums\PaymentProvider;
use App\Modules\Payments\Models\PaymentProviderSetting;

class PaymentProviderSettings
{
    public function __construct(private readonly PaymentGatewayManager $paymentGatewayManager) {}

    public function activeProvider(): PaymentProvider
    {
        return $this->global()->active_provider;
    }

    public function setActiveProvider(PaymentProvider $provider, ?User $user = null): PaymentProviderSetting
    {
        $setting = $this->global();

        $setting->update([
            'active_provider' => $provider,
            'updated_by_user_id' => $user?->id,
        ]);

        return $setting->refresh();
    }

    /**
     * @return list<array{
     *     value:string,
     *     label:string,
     *     description:string,
     *     configured:bool,
     *     active:bool
     * }>
     */
    public function providerOptions(): array
    {
        $activeProvider = $this->activeProvider();

        return array_map(function (PaymentProvider $provider) use ($activeProvider): array {
            $gateway = $this->paymentGatewayManager->for($provider->value);

            return [
                'value' => $provider->value,
                'label' => $provider->label(),
                'description' => $provider->description(),
                'configured' => $gateway->isConfigured(),
                'active' => $provider === $activeProvider,
            ];
        }, PaymentProvider::cases());
    }

    private function global(): PaymentProviderSetting
    {
        /** @var PaymentProviderSetting $setting */
        $setting = PaymentProviderSetting::query()->firstOrCreate(
            ['scope' => PaymentProviderSetting::GLOBAL_SCOPE],
            ['active_provider' => config('services.payments.default_provider', PaymentProvider::Test->value)],
        );

        return $setting;
    }
}
