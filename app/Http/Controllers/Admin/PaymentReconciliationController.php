<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditLog;
use App\Modules\Payments\Actions\ReconcilePayments;
use App\Modules\Payments\Enums\PaymentProvider;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentReconciliationItem;
use App\Modules\Payments\Models\PaymentReconciliationRun;
use App\Modules\Payments\PaymentProviderSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PaymentReconciliationController extends Controller
{
    /**
     * Show latest reconciliation runs.
     */
    public function index(PaymentProviderSettings $paymentProviderSettings): Response
    {
        Gate::authorize('viewAny', Payment::class);

        $runs = PaymentReconciliationRun::query()
            ->withCount('items')
            ->latest()
            ->limit(25)
            ->get();

        $items = PaymentReconciliationItem::query()
            ->with(['run', 'payment.booking'])
            ->latest()
            ->limit(100)
            ->get();

        return Inertia::render('admin/payment-reconciliation/index', [
            'runs' => $runs->map(fn (PaymentReconciliationRun $run): array => [
                'id' => $run->id,
                'provider' => $run->provider->value,
                'status' => $run->status->value,
                'checked_count' => $run->checked_count,
                'mismatch_count' => $run->mismatch_count,
                'items_count' => $run->items_count,
                'started_at' => $run->started_at->toIso8601String(),
                'finished_at' => $run->finished_at?->toIso8601String(),
            ])->values()->all(),
            'items' => $items->map(fn (PaymentReconciliationItem $item): array => [
                'id' => $item->id,
                'status' => $item->status->value,
                'reason' => $item->reason,
                'details' => $item->details,
                'created_at' => $item->created_at?->toIso8601String(),
                'run' => [
                    'id' => $item->run->id,
                    'provider' => $item->run->provider->value,
                ],
                'payment' => [
                    'uuid' => $item->payment->uuid,
                    'provider_payment_id' => $item->payment->provider_payment_id,
                    'status' => $item->payment->status->value,
                    'amount' => $item->payment->amount,
                    'currency' => $item->payment->currency,
                    'booking_uuid' => $item->payment->booking->uuid,
                    'booking_status' => $item->payment->booking->status->value,
                ],
            ])->values()->all(),
            'providers' => [
                ...array_map(fn (PaymentProvider $provider): string => $provider->value, PaymentProvider::cases()),
            ],
            'active_provider' => $paymentProviderSettings->activeProvider()->value,
        ]);
    }

    /**
     * Run local payment reconciliation.
     */
    public function store(
        Request $request,
        ReconcilePayments $reconcilePayments,
        RecordAuditLog $recordAuditLog
    ): RedirectResponse {
        $validated = $request->validate([
            'provider' => ['required', 'string', Rule::enum(PaymentProvider::class)],
            'stale_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ]);

        Gate::authorize('reconcile', Payment::class);

        $run = $reconcilePayments->handle(
            provider: (string) $validated['provider'],
            staleMinutes: (int) ($validated['stale_minutes'] ?? 30),
        );

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $recordAuditLog->handle(
            action: 'payments.reconciliation_run',
            subject: $run,
            actor: $user,
            after: [
                'provider' => $run->provider->value,
                'checked_count' => $run->checked_count,
                'mismatch_count' => $run->mismatch_count,
            ],
            request: $request,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Reconciliation płatności zakończone.',
        ]);
    }
}
