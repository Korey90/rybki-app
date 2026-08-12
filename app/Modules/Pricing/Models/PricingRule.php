<?php

namespace App\Modules\Pricing\Models;

use App\Modules\Fisheries\Models\FishingSpot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $fishing_spot_id
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property int|null $day_of_week
 * @property int $price_amount
 * @property string $currency
 * @property int|null $minimum_duration_minutes
 * @property int $priority
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'fishing_spot_id',
    'starts_at',
    'ends_at',
    'day_of_week',
    'price_amount',
    'currency',
    'minimum_duration_minutes',
    'priority',
])]
class PricingRule extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<FishingSpot, $this>
     */
    public function spot(): BelongsTo
    {
        return $this->belongsTo(FishingSpot::class, 'fishing_spot_id');
    }
}
