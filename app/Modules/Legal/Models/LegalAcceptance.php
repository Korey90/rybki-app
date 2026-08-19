<?php

namespace App\Modules\Legal\Models;

use App\Models\User;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Legal\Enums\LegalDocumentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int|null $user_id
 * @property int|null $booking_id
 * @property string|null $customer_email
 * @property LegalDocumentType $document_type
 * @property string $document_version
 * @property string $context
 * @property bool $marketing_consent
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $accepted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'booking_id',
    'customer_email',
    'document_type',
    'document_version',
    'context',
    'marketing_consent',
    'ip_address',
    'user_agent',
    'accepted_at',
])]
class LegalAcceptance extends Model
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
            'document_type' => LegalDocumentType::class,
            'marketing_consent' => 'boolean',
            'accepted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (LegalAcceptance $acceptance): void {
            $acceptance->uuid ??= (string) Str::uuid();
            $acceptance->accepted_at ??= Carbon::now();
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
