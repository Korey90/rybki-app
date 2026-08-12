<?php

namespace App\Modules\Settlements\Models;

use App\Modules\Bookings\Models\Booking;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\Refund;
use App\Modules\Settlements\Enums\SettlementItemType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $settlement_period_id
 * @property int|null $booking_id
 * @property int|null $payment_id
 * @property int|null $refund_id
 * @property SettlementItemType $type
 * @property int $gross_amount
 * @property int $platform_fee_amount
 * @property int $refund_amount
 * @property int $operator_amount
 * @property string $currency
 * @property Carbon $occurred_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'settlement_period_id',
    'booking_id',
    'payment_id',
    'refund_id',
    'type',
    'gross_amount',
    'platform_fee_amount',
    'refund_amount',
    'operator_amount',
    'currency',
    'occurred_at',
    'metadata',
])]
class SettlementItem extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SettlementItemType::class,
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<SettlementPeriod, $this>
     */
    public function settlementPeriod(): BelongsTo
    {
        return $this->belongsTo(SettlementPeriod::class);
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<Refund, $this>
     */
    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }
}
