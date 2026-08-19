<?php

namespace App\Modules\Reviews\Models;

use App\Models\User;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Reviews\Enums\FishingVenueReviewStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int $fishing_venue_id
 * @property int $booking_id
 * @property int $rating
 * @property string|null $title
 * @property string|null $body
 * @property FishingVenueReviewStatus $status
 * @property string|null $hidden_reason
 * @property int|null $moderated_by_user_id
 * @property Carbon|null $moderated_at
 * @property string|null $operator_response
 * @property int|null $operator_responded_by_user_id
 * @property Carbon|null $operator_responded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'fishing_venue_id',
    'booking_id',
    'rating',
    'title',
    'body',
    'status',
    'hidden_reason',
    'moderated_by_user_id',
    'moderated_at',
    'operator_response',
    'operator_responded_by_user_id',
    'operator_responded_at',
])]
class FishingVenueReview extends Model
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
            'rating' => 'integer',
            'status' => FishingVenueReviewStatus::class,
            'moderated_at' => 'datetime',
            'operator_responded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (FishingVenueReview $review): void {
            $review->uuid ??= (string) Str::uuid();
        });
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', FishingVenueReviewStatus::Published->value);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<FishingVenue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(FishingVenue::class, 'fishing_venue_id');
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function operatorResponder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_responded_by_user_id');
    }
}
