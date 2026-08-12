<?php

namespace App\Modules\Bookings\Models;

use App\Modules\Bookings\Enums\BookingPriceLineType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $booking_id
 * @property BookingPriceLineType $type
 * @property string $description
 * @property int $quantity
 * @property int $unit_amount
 * @property int $total_amount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['booking_id', 'type', 'description', 'quantity', 'unit_amount', 'total_amount'])]
class BookingPriceLine extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BookingPriceLineType::class,
        ];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
