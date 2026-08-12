<?php

namespace App\Modules\Settlements\Models;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Payments\Enums\PaymentProvider;
use App\Modules\Payments\Models\ProviderAccount;
use App\Modules\Settlements\Enums\SettlementPeriodStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $organization_id
 * @property int|null $provider_account_id
 * @property PaymentProvider|null $provider
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property SettlementPeriodStatus $status
 * @property string $currency
 * @property int $gross_amount
 * @property int $platform_fee_amount
 * @property int $refund_amount
 * @property int $net_payout_amount
 * @property int $bookings_count
 * @property int $refunds_count
 * @property Carbon|null $generated_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $paid_at
 * @property string|null $provider_payout_id
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id',
    'provider_account_id',
    'provider',
    'period_start',
    'period_end',
    'status',
    'currency',
    'gross_amount',
    'platform_fee_amount',
    'refund_amount',
    'net_payout_amount',
    'bookings_count',
    'refunds_count',
    'generated_at',
    'approved_at',
    'paid_at',
    'provider_payout_id',
    'notes',
])]
class SettlementPeriod extends Model
{
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
            'provider' => PaymentProvider::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'status' => SettlementPeriodStatus::class,
            'generated_at' => 'datetime',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SettlementPeriod $settlementPeriod): void {
            $settlementPeriod->uuid ??= (string) Str::uuid();
        });
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<ProviderAccount, $this>
     */
    public function providerAccount(): BelongsTo
    {
        return $this->belongsTo(ProviderAccount::class);
    }

    /**
     * @return HasMany<SettlementItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SettlementItem::class);
    }

    /**
     * @return HasOne<OperatorPayout, $this>
     */
    public function payout(): HasOne
    {
        return $this->hasOne(OperatorPayout::class);
    }
}
