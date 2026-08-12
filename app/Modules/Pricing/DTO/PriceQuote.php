<?php

namespace App\Modules\Pricing\DTO;

final readonly class PriceQuote
{
    public function __construct(
        public string $currency,
        public int $unitAmount,
        public int $totalAmount,
        public int $durationMinutes,
        public int $chargedDays,
        public ?int $pricingRuleId,
    ) {}

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'currency' => $this->currency,
            'unit_amount' => $this->unitAmount,
            'total_amount' => $this->totalAmount,
            'duration_minutes' => $this->durationMinutes,
            'charged_days' => $this->chargedDays,
            'pricing_rule_id' => $this->pricingRuleId,
        ];
    }
}
