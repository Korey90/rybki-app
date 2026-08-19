<?php

namespace App\Modules\Reviews\Actions;

use App\Models\User;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Reviews\Models\FishingVenueFavorite;

class SetFishingVenueFavorite
{
    public function handle(User $user, FishingVenue $venue, bool $favorited): bool
    {
        if (! $favorited) {
            FishingVenueFavorite::query()
                ->where('user_id', $user->id)
                ->where('fishing_venue_id', $venue->id)
                ->delete();

            return false;
        }

        FishingVenueFavorite::query()->firstOrCreate([
            'user_id' => $user->id,
            'fishing_venue_id' => $venue->id,
        ]);

        return true;
    }
}
