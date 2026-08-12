<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditLog;
use App\Modules\Payments\Enums\PaymentProvider;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\PaymentProviderSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PaymentProviderController extends Controller
{
    /**
     * Show global payment provider settings.
     */
    public function index(PaymentProviderSettings $paymentProviderSettings): Response
    {
        Gate::authorize('viewAny', Payment::class);

        return Inertia::render('admin/payment-providers/index', [
            'active_provider' => $paymentProviderSettings->activeProvider()->value,
            'providers' => $paymentProviderSettings->providerOptions(),
            'webhooks' => [
                'stripe_connect' => route('api.webhooks.stripe-connect'),
                'payu_marketplace' => route('api.webhooks.payu-marketplace'),
            ],
        ]);
    }

    /**
     * Update global active payment provider.
     */
    public function update(
        Request $request,
        PaymentProviderSettings $paymentProviderSettings,
        RecordAuditLog $recordAuditLog
    ): RedirectResponse {
        Gate::authorize('reconcile', Payment::class);

        $validated = $request->validate([
            'active_provider' => ['required', 'string', Rule::enum(PaymentProvider::class)],
        ]);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $before = $paymentProviderSettings->activeProvider()->value;
        $provider = PaymentProvider::from((string) $validated['active_provider']);
        $setting = $paymentProviderSettings->setActiveProvider($provider, $user);

        $recordAuditLog->handle(
            action: 'payments.provider_changed',
            subject: $setting,
            actor: $user,
            before: ['active_provider' => $before],
            after: ['active_provider' => $setting->active_provider->value],
            request: $request,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Aktywny provider płatności został zapisany.',
        ]);
    }
}
