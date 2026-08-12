<?php

namespace App\Modules\Organizations\Models;

use App\Models\User;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Bookings\Models\CancellationPolicy;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\GovernmentData\Models\FishingVenueClaim;
use App\Modules\Organizations\Enums\OrganizationVerificationStatus;
use App\Modules\Payments\Models\ProviderAccount;
use App\Modules\Settlements\Models\SettlementPeriod;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string|null $legal_name
 * @property string|null $tax_id
 * @property string|null $regon
 * @property string $country
 * @property string|null $address
 * @property OrganizationVerificationStatus $verification_status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'legal_name', 'tax_id', 'regon', 'country', 'address', 'verification_status'])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verification_status' => OrganizationVerificationStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Organization $organization): void {
            $organization->uuid ??= (string) Str::uuid();
        });
    }

    protected static function newFactory(): OrganizationFactory
    {
        return OrganizationFactory::new();
    }

    /**
     * @return HasMany<OrganizationMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    /**
     * @return HasMany<FishingVenue, $this>
     */
    public function fishingVenues(): HasMany
    {
        return $this->hasMany(FishingVenue::class);
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * @return HasMany<CancellationPolicy, $this>
     */
    public function cancellationPolicies(): HasMany
    {
        return $this->hasMany(CancellationPolicy::class);
    }

    /**
     * @return HasMany<FishingVenueClaim, $this>
     */
    public function venueClaims(): HasMany
    {
        return $this->hasMany(FishingVenueClaim::class);
    }

    /**
     * @return HasMany<ProviderAccount, $this>
     */
    public function providerAccounts(): HasMany
    {
        return $this->hasMany(ProviderAccount::class);
    }

    /**
     * @return HasMany<SettlementPeriod, $this>
     */
    public function settlementPeriods(): HasMany
    {
        return $this->hasMany(SettlementPeriod::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_members')
            ->withPivot('role')
            ->withTimestamps();
    }
}
