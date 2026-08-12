<?php

namespace App\Modules\GovernmentData\Models;

use App\Models\User;
use App\Modules\Fisheries\Enums\FishingVenueType;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\GovernmentData\Enums\GovernmentVenueRecordStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $data_source_id
 * @property string $external_id
 * @property string $name
 * @property string|null $description
 * @property FishingVenueType $venue_type
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string|null $address
 * @property string|null $city
 * @property string|null $postal_code
 * @property string|null $voivodeship
 * @property string $country
 * @property array<string, mixed> $source_payload
 * @property array<string, mixed>|null $normalized_payload
 * @property list<string>|null $quality_flags
 * @property int $quality_score
 * @property list<array<string, mixed>>|null $match_candidates
 * @property int $match_confidence
 * @property Carbon|null $source_updated_at
 * @property GovernmentVenueRecordStatus $status
 * @property int|null $matched_fishing_venue_id
 * @property int|null $imported_fishing_venue_id
 * @property int|null $reviewed_by_user_id
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $imported_at
 * @property Carbon|null $skipped_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'data_source_id',
    'external_id',
    'name',
    'description',
    'venue_type',
    'latitude',
    'longitude',
    'address',
    'city',
    'postal_code',
    'voivodeship',
    'country',
    'source_payload',
    'normalized_payload',
    'quality_flags',
    'quality_score',
    'match_candidates',
    'match_confidence',
    'source_updated_at',
    'status',
    'matched_fishing_venue_id',
    'imported_fishing_venue_id',
    'reviewed_by_user_id',
    'reviewed_at',
    'imported_at',
    'skipped_at',
])]
class GovernmentVenueRecord extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'venue_type' => FishingVenueType::class,
            'source_payload' => 'array',
            'normalized_payload' => 'array',
            'quality_flags' => 'array',
            'match_candidates' => 'array',
            'source_updated_at' => 'datetime',
            'status' => GovernmentVenueRecordStatus::class,
            'reviewed_at' => 'datetime',
            'imported_at' => 'datetime',
            'skipped_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<DataSource, $this>
     */
    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class);
    }

    /**
     * @return BelongsTo<FishingVenue, $this>
     */
    public function matchedVenue(): BelongsTo
    {
        return $this->belongsTo(FishingVenue::class, 'matched_fishing_venue_id');
    }

    /**
     * @return BelongsTo<FishingVenue, $this>
     */
    public function importedVenue(): BelongsTo
    {
        return $this->belongsTo(FishingVenue::class, 'imported_fishing_venue_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
