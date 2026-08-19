<?php

namespace App\Modules\Bookings\Models;

use App\Models\User;
use App\Modules\Bookings\Enums\BookingStatus;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Legal\Models\LegalAcceptance;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\Refund;
use App\Modules\Reviews\Models\FishingVenueReview;
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
 * @property string $booking_number
 * @property int|null $user_id
 * @property int $organization_id
 * @property int $fishing_venue_id
 * @property BookingStatus $status
 * @property string $currency
 * @property int $subtotal_amount
 * @property int $discount_amount
 * @property int $total_amount
 * @property int $commission_bps
 * @property int $platform_fee_amount
 * @property int $operator_amount
 * @property string $customer_name
 * @property string $customer_email
 * @property string|null $customer_phone
 * @property array<string, mixed>|null $cancellation_policy_snapshot
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'booking_number',
    'user_id',
    'organization_id',
    'fishing_venue_id',
    'status',
    'currency',
    'subtotal_amount',
    'discount_amount',
    'total_amount',
    'commission_bps',
    'platform_fee_amount',
    'operator_amount',
    'customer_name',
    'customer_email',
    'customer_phone',
    'cancellation_policy_snapshot',
    'confirmed_at',
    'cancelled_at',
])]
class Booking extends Model
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
            'status' => BookingStatus::class,
            'cancellation_policy_snapshot' => 'array',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Booking $booking): void {
            $booking->uuid ??= (string) Str::uuid();
            $booking->booking_number ??= self::generateBookingNumber();
        });
    }

    private static function generateBookingNumber(): string
    {
        do {
            $number = 'BK-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (static::query()->where('booking_number', $number)->exists());

        return $number;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<FishingVenue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(FishingVenue::class, 'fishing_venue_id');
    }

    /**
     * @return HasMany<SpotReservation, $this>
     */
    public function spotReservations(): HasMany
    {
        return $this->hasMany(SpotReservation::class);
    }

    /**
     * @return HasMany<BookingPriceLine, $this>
     */
    public function priceLines(): HasMany
    {
        return $this->hasMany(BookingPriceLine::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * @return HasMany<BookingNotificationLog, $this>
     */
    public function notificationLogs(): HasMany
    {
        return $this->hasMany(BookingNotificationLog::class);
    }

    /**
     * @return HasMany<LegalAcceptance, $this>
     */
    public function legalAcceptances(): HasMany
    {
        return $this->hasMany(LegalAcceptance::class);
    }

    /**
     * @return HasOne<FishingVenueReview, $this>
     */
    public function review(): HasOne
    {
        return $this->hasOne(FishingVenueReview::class);
    }
}
