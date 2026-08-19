<?php

namespace App\Modules\Fisheries\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $fishing_spot_id
 * @property string $storage_path
 * @property int $sort_order
 * @property Carbon|null $hidden_at
 * @property string|null $hidden_reason
 * @property int|null $hidden_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['fishing_spot_id', 'storage_path', 'sort_order', 'hidden_at', 'hidden_reason', 'hidden_by_user_id'])]
class FishingSpotMedia extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hidden_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<FishingSpot, $this>
     */
    public function spot(): BelongsTo
    {
        return $this->belongsTo(FishingSpot::class, 'fishing_spot_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function hiddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hidden_by_user_id');
    }
}
