<?php

namespace App\Modules\Settlements\Models;

use App\Modules\Payments\Enums\PaymentProvider;
use App\Modules\Payments\Models\ProviderAccount;
use App\Modules\Settlements\Enums\OperatorPayoutStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $settlement_period_id
 * @property int|null $provider_account_id
 * @property PaymentProvider|null $provider
 * @property OperatorPayoutStatus $status
 * @property int $amount
 * @property string $currency
 * @property string|null $provider_payout_id
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $failed_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'settlement_period_id',
    'provider_account_id',
    'provider',
    'status',
    'amount',
    'currency',
    'provider_payout_id',
    'scheduled_at',
    'approved_at',
    'paid_at',
    'failed_at',
    'metadata',
])]
class OperatorPayout extends Model
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
            'status' => OperatorPayoutStatus::class,
            'scheduled_at' => 'datetime',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (OperatorPayout $operatorPayout): void {
            $operatorPayout->uuid ??= (string) Str::uuid();
        });
    }

    /**
     * @return BelongsTo<SettlementPeriod, $this>
     */
    public function settlementPeriod(): BelongsTo
    {
        return $this->belongsTo(SettlementPeriod::class);
    }

    /**
     * @return BelongsTo<ProviderAccount, $this>
     */
    public function providerAccount(): BelongsTo
    {
        return $this->belongsTo(ProviderAccount::class);
    }
}
