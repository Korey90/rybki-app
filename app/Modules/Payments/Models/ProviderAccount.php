<?php

namespace App\Modules\Payments\Models;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Payments\Enums\PaymentProvider;
use App\Modules\Payments\Enums\ProviderAccountOnboardingStatus;
use App\Modules\Settlements\Models\OperatorPayout;
use App\Modules\Settlements\Models\SettlementPeriod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property PaymentProvider $provider
 * @property string|null $external_account_id
 * @property ProviderAccountOnboardingStatus $onboarding_status
 * @property bool $payments_enabled
 * @property bool $payouts_enabled
 * @property Carbon|null $last_synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id',
    'provider',
    'external_account_id',
    'onboarding_status',
    'payments_enabled',
    'payouts_enabled',
    'last_synced_at',
])]
class ProviderAccount extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'onboarding_status' => ProviderAccountOnboardingStatus::class,
            'payments_enabled' => 'bool',
            'payouts_enabled' => 'bool',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return HasMany<SettlementPeriod, $this>
     */
    public function settlementPeriods(): HasMany
    {
        return $this->hasMany(SettlementPeriod::class);
    }

    /**
     * @return HasMany<OperatorPayout, $this>
     */
    public function operatorPayouts(): HasMany
    {
        return $this->hasMany(OperatorPayout::class);
    }
}
