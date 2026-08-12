<?php

namespace App\Modules\Fisheries\Actions;

use App\Modules\Fisheries\Enums\FishingSpotStatus;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingVenue;

class CreateFishingSpot
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(FishingVenue $venue, array $attributes): FishingSpot
    {
        return $venue->spots()->create([
            'name' => $attributes['name'],
            'description' => $attributes['description'] ?? null,
            'capacity' => $attributes['capacity'] ?? 1,
            'base_price_amount' => $attributes['base_price_amount'] ?? 0,
            'currency' => $attributes['currency'] ?? 'PLN',
            'status' => $attributes['status'] ?? FishingSpotStatus::Active,
            'sort_order' => $attributes['sort_order'] ?? $this->nextSortOrder($venue),
        ]);
    }

    private function nextSortOrder(FishingVenue $venue): int
    {
        return ((int) $venue->spots()->max('sort_order')) + 1;
    }
}
