<?php

namespace App\Modules\Availability\Models;

use App\Modules\Fisheries\Models\FishingSpot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $fishing_spot_id
 * @property int $day_of_week
 * @property string $opens_at
 * @property string $closes_at
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_until
 * @property int $minimum_booking_minutes
 * @property int|null $maximum_booking_minutes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'fishing_spot_id',
    'day_of_week',
    'opens_at',
    'closes_at',
    'valid_from',
    'valid_until',
    'minimum_booking_minutes',
    'maximum_booking_minutes',
])]
class AvailabilityRule extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_until' => 'date',
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
