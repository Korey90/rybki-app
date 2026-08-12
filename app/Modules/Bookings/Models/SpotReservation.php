<?php

namespace App\Modules\Bookings\Models;

use App\Modules\Bookings\Enums\ReservationStatus;
use App\Modules\Fisheries\Models\FishingSpot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $booking_id
 * @property int $fishing_spot_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property ReservationStatus $status
 * @property Carbon|null $hold_expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['booking_id', 'fishing_spot_id', 'starts_at', 'ends_at', 'status', 'hold_expires_at'])]
class SpotReservation extends Model
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
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => ReservationStatus::class,
            'hold_expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SpotReservation $reservation): void {
            $reservation->uuid ??= (string) Str::uuid();
        });
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<FishingSpot, $this>
     */
    public function spot(): BelongsTo
    {
        return $this->belongsTo(FishingSpot::class, 'fishing_spot_id');
    }
}
