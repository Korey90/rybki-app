<?php

namespace App\Modules\Pricing\Services;

use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Pricing\DTO\PriceQuote;
use App\Modules\Pricing\Models\PricingRule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class PricingService
{
    public function quote(FishingSpot $spot, CarbonInterface $startsAt, CarbonInterface $endsAt): PriceQuote
    {
        $startsAt = CarbonImmutable::instance($startsAt);
        $endsAt = CarbonImmutable::instance($endsAt);
        $durationMinutes = max(1, (int) ceil(($endsAt->getTimestamp() - $startsAt->getTimestamp()) / 60));
        $chargedDays = max(1, (int) ceil($durationMinutes / 1440));
        $rule = $this->matchingRule($spot, $startsAt, $endsAt, $durationMinutes);

        if ($rule instanceof PricingRule) {
            $unitAmount = $rule->price_amount;
            $currency = $rule->currency;
            $pricingRuleId = $rule->id;
        } else {
            $unitAmount = $spot->base_price_amount;
            $currency = $spot->currency;
            $pricingRuleId = null;
        }

        return new PriceQuote(
            currency: $currency,
            unitAmount: $unitAmount,
            totalAmount: $unitAmount * $chargedDays,
            durationMinutes: $durationMinutes,
            chargedDays: $chargedDays,
            pricingRuleId: $pricingRuleId,
        );
    }

    private function matchingRule(
        FishingSpot $spot,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        int $durationMinutes
    ): ?PricingRule {
        return $spot->pricingRules()
            ->where(function ($query) use ($startsAt, $endsAt): void {
                $query
                    ->where(function ($query) use ($endsAt): void {
                        $query
                            ->whereNull('starts_at')
                            ->orWhere('starts_at', '<', $endsAt->toDateTimeString());
                    })
                    ->where(function ($query) use ($startsAt): void {
                        $query
                            ->whereNull('ends_at')
                            ->orWhere('ends_at', '>', $startsAt->toDateTimeString());
                    });
            })
            ->where(function ($query) use ($startsAt): void {
                $query
                    ->whereNull('day_of_week')
                    ->orWhere('day_of_week', $startsAt->isoWeekday());
            })
            ->where(function ($query) use ($durationMinutes): void {
                $query
                    ->whereNull('minimum_duration_minutes')
                    ->orWhere('minimum_duration_minutes', '<=', $durationMinutes);
            })
            ->orderByDesc('priority')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first();
    }
}
