<?php

namespace App\Modules\Fisheries\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $fishing_spot_id
 * @property string $storage_path
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['fishing_spot_id', 'storage_path', 'sort_order'])]
class FishingSpotMedia extends Model
{
    /**
     * @return BelongsTo<FishingSpot, $this>
     */
    public function spot(): BelongsTo
    {
        return $this->belongsTo(FishingSpot::class, 'fishing_spot_id');
    }
}
