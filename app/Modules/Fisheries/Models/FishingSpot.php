<?php

namespace App\Modules\Fisheries\Models;

use App\Modules\Availability\Models\AvailabilityBlock;
use App\Modules\Availability\Models\AvailabilityRule;
use App\Modules\Bookings\Models\SpotReservation;
use App\Modules\Fisheries\Enums\FishingSpotStatus;
use App\Modules\Pricing\Models\PricingRule;
use Database\Factories\FishingSpotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $fishing_venue_id
 * @property string $name
 * @property string|null $description
 * @property int $capacity
 * @property int $base_price_amount
 * @property string $currency
 * @property FishingSpotStatus $status
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'fishing_venue_id',
    'name',
    'description',
    'capacity',
    'base_price_amount',
    'currency',
    'status',
    'sort_order',
])]
class FishingSpot extends Model
{
    /** @use HasFactory<FishingSpotFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FishingSpotStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (FishingSpot $fishingSpot): void {
            $fishingSpot->uuid ??= (string) Str::uuid();
        });
    }

    protected static function newFactory(): FishingSpotFactory
    {
        return FishingSpotFactory::new();
    }

    /**
     * @return BelongsTo<FishingVenue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(FishingVenue::class, 'fishing_venue_id');
    }

    /**
     * @return HasMany<FishingSpotMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(FishingSpotMedia::class);
    }

    /**
     * @return HasMany<AvailabilityRule, $this>
     */
    public function availabilityRules(): HasMany
    {
        return $this->hasMany(AvailabilityRule::class);
    }

    /**
     * @return HasMany<AvailabilityBlock, $this>
     */
    public function availabilityBlocks(): HasMany
    {
        return $this->hasMany(AvailabilityBlock::class);
    }

    /**
     * @return HasMany<PricingRule, $this>
     */
    public function pricingRules(): HasMany
    {
        return $this->hasMany(PricingRule::class);
    }

    /**
     * @return HasMany<SpotReservation, $this>
     */
    public function spotReservations(): HasMany
    {
        return $this->hasMany(SpotReservation::class);
    }
}
