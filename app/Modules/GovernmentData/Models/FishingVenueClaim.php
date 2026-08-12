<?php

namespace App\Modules\GovernmentData\Models;

use App\Models\User;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\GovernmentData\Enums\FishingVenueClaimStatus;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $fishing_venue_id
 * @property int $organization_id
 * @property FishingVenueClaimStatus $status
 * @property string|null $evidence
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'fishing_venue_id',
    'organization_id',
    'status',
    'evidence',
    'reviewed_by',
    'reviewed_at',
])]
class FishingVenueClaim extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FishingVenueClaimStatus::class,
            'reviewed_at' => 'datetime',
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
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
