<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditLog;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Payments\Models\Payment;
use App\Modules\Settlements\Actions\ExportSettlementPeriod;
use App\Modules\Settlements\Actions\GenerateSettlementPeriod;
use App\Modules\Settlements\Enums\OperatorPayoutStatus;
use App\Modules\Settlements\Enums\SettlementPeriodStatus;
use App\Modules\Settlements\Models\SettlementPeriod;
use App\Modules\Settlements\PlatformCommissionSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettlementController extends Controller
{
    public function index(PlatformCommissionSettings $platformCommissionSettings): InertiaResponse
    {
        Gate::authorize('reconcile', Payment::class);

        $periods = SettlementPeriod::query()
            ->with(['organization', 'providerAccount', 'payout'])
            ->latest('period_end')
            ->latest()
            ->limit(100)
            ->get();

        $organizations = Organization::query()
            ->orderBy('name')
            ->get(['id', 'uuid', 'name']);

        return Inertia::render('admin/settlements/index', [
            'commission_settings' => [
                'default_commission_bps' => $platformCommissionSettings->defaultCommissionBps(),
            ],
            'filters' => [
                'organization_uuid' => $organizations->first()?->uuid,
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end' => now()->endOfMonth()->toDateString(),
            ],
            'summary' => [
                'gross_amount' => (int) $periods->sum('gross_amount'),
                'platform_fee_amount' => (int) $periods->sum('platform_fee_amount'),
                'refund_amount' => (int) $periods->sum('refund_amount'),
                'net_payout_amount' => (int) $periods->sum('net_payout_amount'),
                'pending_payouts_count' => $periods->filter(fn (SettlementPeriod $period): bool => $period->payout?->status === OperatorPayoutStatus::Pending)->count(),
            ],
            'organizations' => $organizations->map(fn (Organization $organization): array => [
                'uuid' => $organization->uuid,
                'name' => $organization->name,
            ])->values()->all(),
            'settlement_periods' => $periods->map(fn (SettlementPeriod $settlementPeriod): array => $this->settlementPeriodPayload($settlementPeriod))->values()->all(),
            'payout_statuses' => array_map(fn (OperatorPayoutStatus $status): string => $status->value, OperatorPayoutStatus::cases()),
        ]);
    }

    public function updateCommission(
        Request $request,
        PlatformCommissionSettings $platformCommissionSettings,
        RecordAuditLog $recordAuditLog
    ): RedirectResponse {
        Gate::authorize('reconcile', Payment::class);

        $validated = $request->validate([
            'default_commission_bps' => ['required', 'integer', 'min:0', 'max:10000'],
        ]);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $before = $platformCommissionSettings->defaultCommissionBps();
        $setting = $platformCommissionSettings->setDefaultCommissionBps((int) $validated['default_commission_bps'], $user);

        $recordAuditLog->handle(
            action: 'settlements.commission_changed',
            subject: $setting,
            actor: $user,
            before: ['default_commission_bps' => $before],
            after: ['default_commission_bps' => $setting->default_commission_bps],
            request: $request,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Prowizja platformy została zapisana.',
        ]);
    }

    public function store(
        Request $request,
        GenerateSettlementPeriod $generateSettlementPeriod,
        RecordAuditLog $recordAuditLog
    ): RedirectResponse {
        Gate::authorize('reconcile', Payment::class);

        $validated = $request->validate([
            'organization_uuid' => ['required', 'string', 'exists:organizations,uuid'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ]);

        /** @var Organization $organization */
        $organization = Organization::query()
            ->where('uuid', $validated['organization_uuid'])
            ->firstOrFail();

        $settlementPeriod = $generateSettlementPeriod->handle(
            organization: $organization,
            periodStart: (string) $validated['period_start'],
            periodEnd: (string) $validated['period_end'],
        );

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $recordAuditLog->handle(
            action: 'settlements.period_generated',
            subject: $settlementPeriod,
            actor: $user,
            organization: $organization,
            after: [
                'period_start' => $settlementPeriod->period_start->toDateString(),
                'period_end' => $settlementPeriod->period_end->toDateString(),
                'net_payout_amount' => $settlementPeriod->net_payout_amount,
            ],
            request: $request,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Okres rozliczeniowy został wygenerowany.',
        ]);
    }

    public function updatePayout(
        Request $request,
        SettlementPeriod $settlementPeriod,
        RecordAuditLog $recordAuditLog
    ): RedirectResponse {
        Gate::authorize('reconcile', Payment::class);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::enum(OperatorPayoutStatus::class)],
            'provider_payout_id' => ['nullable', 'string', 'max:255'],
        ]);

        $status = OperatorPayoutStatus::from((string) $validated['status']);
        $payout = $settlementPeriod->payout()->firstOrCreate(
            ['settlement_period_id' => $settlementPeriod->id],
            [
                'provider_account_id' => $settlementPeriod->provider_account_id,
                'provider' => $settlementPeriod->provider?->value,
                'status' => OperatorPayoutStatus::Pending,
                'amount' => max(0, $settlementPeriod->net_payout_amount),
                'currency' => $settlementPeriod->currency,
            ],
        );

        $before = [
            'settlement_status' => $settlementPeriod->status->value,
            'payout_status' => $payout->status->value,
            'provider_payout_id' => $payout->provider_payout_id,
        ];

        $payout->fill([
            'status' => $status,
            'provider_payout_id' => $validated['provider_payout_id'] ?? $payout->provider_payout_id,
            'approved_at' => $status === OperatorPayoutStatus::Approved ? now() : $payout->approved_at,
            'paid_at' => $status === OperatorPayoutStatus::Paid ? now() : $payout->paid_at,
            'failed_at' => $status === OperatorPayoutStatus::Failed ? now() : $payout->failed_at,
        ]);
        $payout->save();

        $settlementPeriod->fill([
            'status' => $this->periodStatusForPayout($status),
            'approved_at' => in_array($status, [OperatorPayoutStatus::Approved, OperatorPayoutStatus::Processing, OperatorPayoutStatus::Paid], true)
                ? ($settlementPeriod->approved_at ?? now())
                : $settlementPeriod->approved_at,
            'paid_at' => $status === OperatorPayoutStatus::Paid ? now() : $settlementPeriod->paid_at,
            'provider_payout_id' => $payout->provider_payout_id,
        ]);
        $settlementPeriod->save();

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $recordAuditLog->handle(
            action: 'settlements.payout_status_changed',
            subject: $payout,
            actor: $user,
            organization: $settlementPeriod->organization,
            before: $before,
            after: [
                'settlement_status' => $settlementPeriod->status->value,
                'payout_status' => $payout->status->value,
                'provider_payout_id' => $payout->provider_payout_id,
            ],
            request: $request,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Status wypłaty został zapisany.',
        ]);
    }

    public function export(
        Request $request,
        SettlementPeriod $settlementPeriod,
        ExportSettlementPeriod $exportSettlementPeriod
    ): StreamedResponse|SymfonyResponse {
        Gate::authorize('reconcile', Payment::class);

        $format = (string) $request->query('format', 'csv');

        return match ($format) {
            'pdf' => $exportSettlementPeriod->pdf($settlementPeriod),
            default => $exportSettlementPeriod->csv($settlementPeriod),
        };
    }

    private function periodStatusForPayout(OperatorPayoutStatus $status): SettlementPeriodStatus
    {
        return match ($status) {
            OperatorPayoutStatus::Pending => SettlementPeriodStatus::Draft,
            OperatorPayoutStatus::Approved => SettlementPeriodStatus::Approved,
            OperatorPayoutStatus::Processing => SettlementPeriodStatus::Processing,
            OperatorPayoutStatus::Paid => SettlementPeriodStatus::Paid,
            OperatorPayoutStatus::Failed => SettlementPeriodStatus::Failed,
            OperatorPayoutStatus::Cancelled => SettlementPeriodStatus::Cancelled,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function settlementPeriodPayload(SettlementPeriod $settlementPeriod): array
    {
        $hasProviderAccount = $settlementPeriod->provider_account_id !== null;

        return [
            'uuid' => $settlementPeriod->uuid,
            'organization' => [
                'uuid' => $settlementPeriod->organization->uuid,
                'name' => $settlementPeriod->organization->name,
            ],
            'provider' => $settlementPeriod->provider?->value,
            'provider_account' => [
                'external_account_id' => $hasProviderAccount ? $settlementPeriod->providerAccount->external_account_id : null,
                'payouts_enabled' => $hasProviderAccount && $settlementPeriod->providerAccount->payouts_enabled,
            ],
            'period_start' => $settlementPeriod->period_start->toDateString(),
            'period_end' => $settlementPeriod->period_end->toDateString(),
            'status' => $settlementPeriod->status->value,
            'currency' => $settlementPeriod->currency,
            'gross_amount' => $settlementPeriod->gross_amount,
            'platform_fee_amount' => $settlementPeriod->platform_fee_amount,
            'refund_amount' => $settlementPeriod->refund_amount,
            'net_payout_amount' => $settlementPeriod->net_payout_amount,
            'bookings_count' => $settlementPeriod->bookings_count,
            'refunds_count' => $settlementPeriod->refunds_count,
            'generated_at' => $settlementPeriod->generated_at?->toIso8601String(),
            'payout' => [
                'uuid' => $settlementPeriod->payout?->uuid,
                'status' => $settlementPeriod->payout?->status->value,
                'amount' => $settlementPeriod->payout?->amount,
                'provider_payout_id' => $settlementPeriod->payout?->provider_payout_id,
                'paid_at' => $settlementPeriod->payout?->paid_at?->toIso8601String(),
            ],
            'exports' => [
                'csv' => route('admin.settlements.export', ['settlementPeriod' => $settlementPeriod, 'format' => 'csv']),
                'pdf' => route('admin.settlements.export', ['settlementPeriod' => $settlementPeriod, 'format' => 'pdf']),
            ],
        ];
    }
}
