<?php

namespace App\Modules\GovernmentData\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $provider
 * @property string $dataset_name
 * @property string|null $dataset_url
 * @property string|null $external_dataset_id
 * @property string|null $default_format
 * @property bool $sync_enabled
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $last_successful_sync_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'provider',
    'dataset_name',
    'dataset_url',
    'external_dataset_id',
    'default_format',
    'sync_enabled',
    'metadata',
    'last_successful_sync_at',
])]
class DataSource extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sync_enabled' => 'bool',
            'metadata' => 'array',
            'last_successful_sync_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<GovernmentVenueRecord, $this>
     */
    public function governmentVenueRecords(): HasMany
    {
        return $this->hasMany(GovernmentVenueRecord::class);
    }

    /**
     * @return HasMany<FishingVenueSource, $this>
     */
    public function venueSources(): HasMany
    {
        return $this->hasMany(FishingVenueSource::class);
    }

    /**
     * @return HasMany<GovernmentDataSyncRun, $this>
     */
    public function syncRuns(): HasMany
    {
        return $this->hasMany(GovernmentDataSyncRun::class);
    }
}
