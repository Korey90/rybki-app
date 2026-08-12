<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Organizations\Queries\ManageableOrganizations;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Enums\RefundStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\ProviderAccount;
use App\Modules\Payments\Models\Refund;
use App\Modules\Settlements\Actions\ExportSettlementPeriod;
use App\Modules\Settlements\Actions\GenerateSettlementPeriod;
use App\Modules\Settlements\Models\SettlementPeriod;
use App\Modules\Settlements\PlatformCommissionSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettlementController extends Controller
{
    public function __construct(private readonly ManageableOrganizations $manageableOrganizations) {}

    /**
     * Show basic operator payment settlement data.
     */
    public function index(Request $request, PlatformCommissionSettings $platformCommissionSettings): InertiaResponse
    {
        $user = $request->user();
        assert($user instanceof User);
        Gate::authorize('viewAny', Payment::class);

        $memberships = $this->manageableOrganizations->membershipsFor($user);
        $organizationIds = $memberships->pluck('organization_id')->map(fn (mixed $id): int => (int) $id)->all();
        $defaultOrganizationUuid = $memberships->first()?->organization?->uuid;

        $settledBookingsQuery = Booking::query()
            ->whereIn('organization_id', $organizationIds)
            ->whereHas('payments', fn (Builder $query) => $query->whereIn('status', $this->settledPaymentStatuses()));

        $refundsAmount = (int) Refund::query()
            ->whereHas('booking', fn (Builder $query) => $query->whereIn('organization_id', $organizationIds))
            ->where('status', RefundStatus::Succeeded->value)
            ->sum('amount');

        $operatorAmount = (int) (clone $settledBookingsQuery)->sum('operator_amount');

        $payments = Payment::query()
            ->with('booking.venue')
            ->whereHas('booking', fn (Builder $query) => $query->whereIn('organization_id', $organizationIds))
            ->latest()
            ->limit(20)
            ->get();

        $providerAccounts = ProviderAccount::query()
            ->whereIn('organization_id', $organizationIds)
            ->latest()
            ->get();

        $settlementPeriods = SettlementPeriod::query()
            ->with(['organization', 'providerAccount', 'payout'])
            ->whereIn('organization_id', $organizationIds)
            ->latest('period_end')
            ->latest()
            ->limit(24)
            ->get();

        return Inertia::render('operator/settlements', [
            'summary' => [
                'paid_gross_amount' => (int) (clone $settledBookingsQuery)->sum('total_amount'),
                'platform_fee_amount' => (int) (clone $settledBookingsQuery)->sum('platform_fee_amount'),
                'operator_amount' => $operatorAmount,
                'refunds_amount' => $refundsAmount,
                'estimated_payout_amount' => max(0, $operatorAmount - $refundsAmount),
                'succeeded_payments_count' => Payment::query()
                    ->whereHas('booking', fn (Builder $query) => $query->whereIn('organization_id', $organizationIds))
                    ->whereIn('status', $this->settledPaymentStatuses())
                    ->count(),
                'pending_payments_count' => Payment::query()
                    ->whereHas('booking', fn (Builder $query) => $query->whereIn('organization_id', $organizationIds))
                    ->whereIn('status', [
                        PaymentStatus::Created->value,
                        PaymentStatus::Processing->value,
                    ])
                    ->count(),
                'failed_payments_count' => Payment::query()
                    ->whereHas('booking', fn (Builder $query) => $query->whereIn('organization_id', $organizationIds))
                    ->where('status', PaymentStatus::Failed->value)
                    ->count(),
            ],
            'filters' => [
                'organization_uuid' => $defaultOrganizationUuid,
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end' => now()->endOfMonth()->toDateString(),
            ],
            'commission_settings' => [
                'default_commission_bps' => $platformCommissionSettings->defaultCommissionBps(),
            ],
            'organizations' => $memberships->map(fn (OrganizationMember $membership): array => $this->organizationSettlementPayload($membership))->all(),
            'settlement_periods' => $settlementPeriods->map(fn (SettlementPeriod $settlementPeriod): array => $this->settlementPeriodPayload($settlementPeriod, 'operator.settlements.export'))->all(),
            'payments' => $payments->map(fn (Payment $payment): array => $this->paymentPayload($payment))->all(),
            'provider_accounts' => $providerAccounts->map(fn (ProviderAccount $providerAccount): array => [
                'provider' => $providerAccount->provider->value,
                'external_account_id' => $providerAccount->external_account_id,
                'onboarding_status' => $providerAccount->onboarding_status->value,
                'payments_enabled' => $providerAccount->payments_enabled,
                'payouts_enabled' => $providerAccount->payouts_enabled,
                'last_synced_at' => $providerAccount->last_synced_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    /**
     * Generate a settlement period for one of the operator organizations.
     */
    public function store(Request $request, GenerateSettlementPeriod $generateSettlementPeriod): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);
        Gate::authorize('viewAny', Payment::class);

        $validated = $request->validate([
            'organization_uuid' => ['required', 'string', 'exists:organizations,uuid'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ]);

        /** @var Organization $organization */
        $organization = Organization::query()
            ->where('uuid', $validated['organization_uuid'])
            ->firstOrFail();

        abort_unless($this->manageableOrganizations->canManageOrganization($user, $organization->id), 403);

        $generateSettlementPeriod->handle(
            organization: $organization,
            periodStart: (string) $validated['period_start'],
            periodEnd: (string) $validated['period_end'],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Okres rozliczeniowy został wygenerowany.',
        ]);
    }

    public function export(
        Request $request,
        SettlementPeriod $settlementPeriod,
        ExportSettlementPeriod $exportSettlementPeriod
    ): StreamedResponse|SymfonyResponse {
        $user = $request->user();
        assert($user instanceof User);
        Gate::authorize('viewAny', Payment::class);

        abort_unless($this->manageableOrganizations->canManageOrganization($user, $settlementPeriod->organization_id), 403);

        $format = (string) $request->query('format', 'csv');

        return match ($format) {
            'pdf' => $exportSettlementPeriod->pdf($settlementPeriod),
            default => $exportSettlementPeriod->csv($settlementPeriod),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function organizationSettlementPayload(OrganizationMember $membership): array
    {
        $organization = $membership->organization;
        assert($organization instanceof Organization);

        $settledBookingsQuery = Booking::query()
            ->where('organization_id', $organization->id)
            ->whereHas('payments', fn (Builder $query) => $query->whereIn('status', $this->settledPaymentStatuses()));

        $refundsAmount = (int) Refund::query()
            ->whereHas('booking', fn (Builder $query) => $query->where('organization_id', $organization->id))
            ->where('status', RefundStatus::Succeeded->value)
            ->sum('amount');
        $operatorAmount = (int) (clone $settledBookingsQuery)->sum('operator_amount');

        return [
            'uuid' => $organization->uuid,
            'name' => $organization->name,
            'paid_gross_amount' => (int) (clone $settledBookingsQuery)->sum('total_amount'),
            'platform_fee_amount' => (int) (clone $settledBookingsQuery)->sum('platform_fee_amount'),
            'operator_amount' => $operatorAmount,
            'refunds_amount' => $refundsAmount,
            'estimated_payout_amount' => max(0, $operatorAmount - $refundsAmount),
            'succeeded_payments_count' => Payment::query()
                ->whereHas('booking', fn (Builder $query) => $query->where('organization_id', $organization->id))
                ->whereIn('status', $this->settledPaymentStatuses())
                ->count(),
        ];
    }

    /**
     * @return list<string>
     */
    private function settledPaymentStatuses(): array
    {
        return [
            PaymentStatus::Succeeded->value,
            PaymentStatus::PartiallyRefunded->value,
            PaymentStatus::Refunded->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentPayload(Payment $payment): array
    {
        $booking = $payment->booking;
        assert($booking instanceof Booking);

        return [
            'uuid' => $payment->uuid,
            'provider' => $payment->provider->value,
            'provider_payment_id' => $payment->provider_payment_id,
            'status' => $payment->status->value,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'failed_at' => $payment->failed_at?->toIso8601String(),
            'created_at' => $payment->created_at?->toIso8601String(),
            'booking' => [
                'uuid' => $booking->uuid,
                'booking_number' => $booking->booking_number,
                'customer_name' => $booking->customer_name,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function settlementPeriodPayload(SettlementPeriod $settlementPeriod, string $exportRouteName): array
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
                'csv' => route($exportRouteName, ['settlementPeriod' => $settlementPeriod, 'format' => 'csv']),
                'pdf' => route($exportRouteName, ['settlementPeriod' => $settlementPeriod, 'format' => 'pdf']),
            ],
        ];
    }
}
