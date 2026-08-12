<?php

namespace App\Modules\Fisheries\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $icon
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'icon'])]
class Amenity extends Model
{
    /**
     * @return BelongsToMany<FishingVenue, $this>
     */
    public function venues(): BelongsToMany
    {
        return $this->belongsToMany(FishingVenue::class, 'fishing_venue_amenity');
    }
}
