<?php

namespace App\Jobs;

use App\Modules\Payments\Actions\ReconcilePayments;
use App\Modules\Payments\PaymentProviderSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ReconcilePaymentsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ?string $provider = null,
        public readonly int $staleMinutes = 30,
    ) {}

    public function handle(ReconcilePayments $reconcilePayments, PaymentProviderSettings $paymentProviderSettings): void
    {
        $provider = $this->provider ?: $paymentProviderSettings->activeProvider()->value;

        $reconcilePayments->handle($provider, $this->staleMinutes);
    }
}
