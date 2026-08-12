<?php

namespace App\Modules\Settlements\Actions;

use App\Modules\Bookings\Models\Booking;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Payments\Enums\PaymentProvider;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Enums\RefundStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\ProviderAccount;
use App\Modules\Payments\Models\Refund;
use App\Modules\Settlements\Enums\OperatorPayoutStatus;
use App\Modules\Settlements\Enums\SettlementItemType;
use App\Modules\Settlements\Enums\SettlementPeriodStatus;
use App\Modules\Settlements\Models\OperatorPayout;
use App\Modules\Settlements\Models\SettlementPeriod;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GenerateSettlementPeriod
{
    public function handle(
        Organization $organization,
        CarbonInterface|string $periodStart,
        CarbonInterface|string $periodEnd,
        string $currency = 'PLN'
    ): SettlementPeriod {
        $startDate = $this->date($periodStart)->startOfDay();
        $endDate = $this->date($periodEnd)->endOfDay();

        return DB::transaction(function () use ($currency, $endDate, $organization, $startDate): SettlementPeriod {
            /** @var SettlementPeriod|null $existing */
            $existing = SettlementPeriod::query()
                ->where('organization_id', $organization->id)
                ->whereDate('period_start', $startDate->toDateString())
                ->whereDate('period_end', $endDate->toDateString())
                ->where('currency', $currency)
                ->lockForUpdate()
                ->first();

            if ($existing !== null && $existing->status !== SettlementPeriodStatus::Draft) {
                return $existing->load(['organization', 'providerAccount', 'payout', 'items.booking', 'items.payment', 'items.refund']);
            }

            /** @var Collection<int, Payment> $payments */
            $payments = $this->paymentsForPeriod($organization, $startDate, $endDate, $currency);

            /** @var Collection<int, Refund> $refunds */
            $refunds = $this->refundsForPeriod($organization, $startDate, $endDate, $currency);

            $providerAccount = $this->resolveProviderAccount($organization, $payments);
            $provider = $providerAccount?->provider;
            $firstPayment = $payments->first();

            if ($provider === null && $firstPayment instanceof Payment) {
                $provider = $firstPayment->provider;
            }

            $grossAmount = (int) $payments->sum('amount');
            $platformFeeAmount = (int) $payments->sum(fn (Payment $payment): int => $payment->booking->platform_fee_amount);
            $operatorAmount = (int) $payments->sum(fn (Payment $payment): int => $payment->booking->operator_amount);
            $refundAmount = (int) $refunds->sum('amount');
            $netPayoutAmount = $operatorAmount - $refundAmount;

            /** @var SettlementPeriod $settlementPeriod */
            $settlementPeriod = $existing ?? new SettlementPeriod([
                'organization_id' => $organization->id,
                'period_start' => $startDate->toDateString(),
                'period_end' => $endDate->toDateString(),
                'currency' => $currency,
            ]);

            $settlementPeriod->fill([
                'provider_account_id' => $providerAccount?->id,
                'provider' => $provider?->value,
                'status' => SettlementPeriodStatus::Draft,
                'gross_amount' => $grossAmount,
                'platform_fee_amount' => $platformFeeAmount,
                'refund_amount' => $refundAmount,
                'net_payout_amount' => $netPayoutAmount,
                'bookings_count' => $payments->pluck('booking_id')->unique()->count(),
                'refunds_count' => $refunds->count(),
                'generated_at' => now(),
            ]);
            $settlementPeriod->save();

            $settlementPeriod->items()->delete();

            $payments->each(function (Payment $payment) use ($settlementPeriod): void {
                $booking = $payment->booking;
                assert($booking instanceof Booking);

                $settlementPeriod->items()->create([
                    'booking_id' => $booking->id,
                    'payment_id' => $payment->id,
                    'type' => SettlementItemType::Payment,
                    'gross_amount' => $payment->amount,
                    'platform_fee_amount' => $booking->platform_fee_amount,
                    'refund_amount' => 0,
                    'operator_amount' => $booking->operator_amount,
                    'currency' => $payment->currency,
                    'occurred_at' => $payment->paid_at ?? $payment->created_at ?? now(),
                    'metadata' => [
                        'booking_number' => $booking->booking_number,
                        'payment_status' => $payment->status->value,
                        'commission_bps' => $booking->commission_bps,
                    ],
                ]);
            });

            $refunds->each(function (Refund $refund) use ($settlementPeriod): void {
                $booking = $refund->booking;
                assert($booking instanceof Booking);

                $settlementPeriod->items()->create([
                    'booking_id' => $booking->id,
                    'payment_id' => $refund->payment_id,
                    'refund_id' => $refund->id,
                    'type' => SettlementItemType::Refund,
                    'gross_amount' => 0,
                    'platform_fee_amount' => 0,
                    'refund_amount' => $refund->amount,
                    'operator_amount' => -1 * $refund->amount,
                    'currency' => $refund->currency,
                    'occurred_at' => $refund->created_at ?? now(),
                    'metadata' => [
                        'booking_number' => $booking->booking_number,
                        'provider_refund_id' => $refund->provider_refund_id,
                        'reason' => $refund->reason,
                    ],
                ]);
            });

            $payout = $settlementPeriod->payout ?? new OperatorPayout([
                'settlement_period_id' => $settlementPeriod->id,
            ]);

            $payout->fill([
                'provider_account_id' => $providerAccount?->id,
                'provider' => $provider?->value,
                'status' => OperatorPayoutStatus::Pending,
                'amount' => max(0, $netPayoutAmount),
                'currency' => $currency,
                'metadata' => [
                    'generated_from' => 'settlement_period',
                    'bookings_count' => $payments->pluck('booking_id')->unique()->count(),
                    'refunds_count' => $refunds->count(),
                ],
            ]);
            $payout->save();

            return $settlementPeriod->load(['organization', 'providerAccount', 'payout', 'items.booking', 'items.payment', 'items.refund']);
        });
    }

    /**
     * @return Collection<int, Payment>
     */
    private function paymentsForPeriod(
        Organization $organization,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        string $currency
    ): Collection {
        /** @var EloquentCollection<int, Payment> $payments */
        $payments = Payment::query()
            ->with('booking')
            ->where('currency', $currency)
            ->whereIn('status', $this->settledPaymentStatuses())
            ->whereHas('booking', fn (Builder $query) => $query->where('organization_id', $organization->id))
            ->where(function (Builder $query) use ($endDate, $startDate): void {
                $query->whereBetween('paid_at', [$startDate, $endDate])
                    ->orWhere(function (Builder $fallback) use ($endDate, $startDate): void {
                        $fallback->whereNull('paid_at')
                            ->whereBetween('created_at', [$startDate, $endDate]);
                    });
            })
            ->orderBy('paid_at')
            ->orderBy('created_at')
            ->get();

        return $payments->toBase();
    }

    /**
     * @return Collection<int, Refund>
     */
    private function refundsForPeriod(
        Organization $organization,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        string $currency
    ): Collection {
        /** @var EloquentCollection<int, Refund> $refunds */
        $refunds = Refund::query()
            ->with(['booking', 'payment'])
            ->where('currency', $currency)
            ->where('status', RefundStatus::Succeeded->value)
            ->whereHas('booking', fn (Builder $query) => $query->where('organization_id', $organization->id))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at')
            ->get();

        return $refunds->toBase();
    }

    /**
     * @param  Collection<int, Payment>  $payments
     */
    private function resolveProviderAccount(Organization $organization, Collection $payments): ?ProviderAccount
    {
        $provider = $payments->first()?->provider;

        if ($provider instanceof PaymentProvider) {
            /** @var ProviderAccount|null $account */
            $account = ProviderAccount::query()
                ->where('organization_id', $organization->id)
                ->where('provider', $provider->value)
                ->first();

            if ($account !== null) {
                return $account;
            }
        }

        /** @var ProviderAccount|null $fallback */
        $fallback = ProviderAccount::query()
            ->where('organization_id', $organization->id)
            ->orderByDesc('payouts_enabled')
            ->latest()
            ->first();

        return $fallback;
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

    private function date(CarbonInterface|string $value): CarbonImmutable
    {
        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value);
        }

        return CarbonImmutable::parse($value);
    }
}
