<?php

namespace App\Modules\Fisheries\Models;

use App\Models\User;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Fisheries\Enums\FishingVenueCommercialStatus;
use App\Modules\Fisheries\Enums\FishingVenueStatus;
use App\Modules\Fisheries\Enums\FishingVenueType;
use App\Modules\GovernmentData\Models\FishingVenueClaim;
use App\Modules\GovernmentData\Models\FishingVenueSource;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Reviews\Enums\FishingVenueReviewStatus;
use App\Modules\Reviews\Models\FishingVenueFavorite;
use App\Modules\Reviews\Models\FishingVenueReview;
use Database\Factories\FishingVenueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int|null $organization_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property FishingVenueType $venue_type
 * @property FishingVenueCommercialStatus $commercial_status
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string|null $address
 * @property string|null $city
 * @property string|null $postal_code
 * @property string|null $voivodeship
 * @property string $country
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $website
 * @property string|null $rules
 * @property string|null $check_in_instructions
 * @property FishingVenueStatus $status
 * @property Carbon|null $published_at
 * @property string|null $moderation_reason
 * @property int|null $moderated_by_user_id
 * @property Carbon|null $moderated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'organization_id',
    'name',
    'slug',
    'description',
    'venue_type',
    'commercial_status',
    'latitude',
    'longitude',
    'address',
    'city',
    'postal_code',
    'voivodeship',
    'country',
    'phone',
    'email',
    'website',
    'rules',
    'check_in_instructions',
    'status',
    'published_at',
    'moderation_reason',
    'moderated_by_user_id',
    'moderated_at',
])]
class FishingVenue extends Model
{
    /** @use HasFactory<FishingVenueFactory> */
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
            'venue_type' => FishingVenueType::class,
            'commercial_status' => FishingVenueCommercialStatus::class,
            'status' => FishingVenueStatus::class,
            'published_at' => 'datetime',
            'moderated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (FishingVenue $fishingVenue): void {
            $fishingVenue->uuid ??= (string) Str::uuid();
        });

        static::saved(function (FishingVenue $fishingVenue): void {
            if (! $fishingVenue->wasRecentlyCreated && ! $fishingVenue->wasChanged(['latitude', 'longitude'])) {
                return;
            }

            $fishingVenue->syncPostgisLocation();
        });
    }

    protected static function newFactory(): FishingVenueFactory
    {
        return FishingVenueFactory::new();
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
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by_user_id');
    }

    /**
     * @return HasMany<FishingSpot, $this>
     */
    public function spots(): HasMany
    {
        return $this->hasMany(FishingSpot::class);
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * @return HasMany<FishingVenueFavorite, $this>
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(FishingVenueFavorite::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function favoritedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'fishing_venue_favorites')
            ->withTimestamps();
    }

    /**
     * @return HasMany<FishingVenueReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(FishingVenueReview::class);
    }

    /**
     * @return HasMany<FishingVenueReview, $this>
     */
    public function publishedReviews(): HasMany
    {
        return $this->hasMany(FishingVenueReview::class)
            ->where('status', FishingVenueReviewStatus::Published->value);
    }

    /**
     * @return HasMany<FishingVenueSource, $this>
     */
    public function sources(): HasMany
    {
        return $this->hasMany(FishingVenueSource::class);
    }

    /**
     * @return HasMany<FishingVenueClaim, $this>
     */
    public function claims(): HasMany
    {
        return $this->hasMany(FishingVenueClaim::class);
    }

    /**
     * @return HasMany<FishingVenueMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(FishingVenueMedia::class);
    }

    /**
     * @return BelongsToMany<FishSpecies, $this>
     */
    public function fishSpecies(): BelongsToMany
    {
        return $this->belongsToMany(FishSpecies::class, 'fishing_venue_fish_species');
    }

    /**
     * @return BelongsToMany<Amenity, $this>
     */
    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class, 'fishing_venue_amenity');
    }

    public function syncPostgisLocation(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql'
            || ! Schema::hasColumn($this->getTable(), 'location')) {
            return;
        }

        if ($this->latitude === null || $this->longitude === null) {
            DB::table($this->getTable())
                ->where('id', $this->id)
                ->update(['location' => null]);

            return;
        }

        DB::statement(
            'UPDATE fishing_venues SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography WHERE id = ?',
            [(float) $this->longitude, (float) $this->latitude, $this->id],
        );
    }
}
