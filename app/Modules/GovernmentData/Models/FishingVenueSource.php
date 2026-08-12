<?php

namespace App\Modules\GovernmentData\Models;

use App\Modules\Fisheries\Models\FishingVenue;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $fishing_venue_id
 * @property int $data_source_id
 * @property string $external_id
 * @property array<string, mixed> $source_payload
 * @property Carbon|null $source_updated_at
 * @property Carbon|null $last_synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'fishing_venue_id',
    'data_source_id',
    'external_id',
    'source_payload',
    'source_updated_at',
    'last_synced_at',
])]
class FishingVenueSource extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_payload' => 'array',
            'source_updated_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<FishingVenue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(FishingVenue::class, 'fishing_venue_id');
    }

    /**
     * @return BelongsTo<DataSource, $this>
     */
    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class);
    }
}
