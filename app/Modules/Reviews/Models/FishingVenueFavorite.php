<?php

namespace App\Modules\Reviews\Models;

use App\Models\User;
use App\Modules\Fisheries\Models\FishingVenue;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $fishing_venue_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'fishing_venue_id'])]
class FishingVenueFavorite extends Model
{
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
}
