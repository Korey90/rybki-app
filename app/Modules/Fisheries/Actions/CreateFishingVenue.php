<?php

namespace App\Modules\Fisheries\Actions;

use App\Modules\Fisheries\Enums\FishingVenueCommercialStatus;
use App\Modules\Fisheries\Enums\FishingVenueStatus;
use App\Modules\Fisheries\Enums\FishingVenueType;
use App\Modules\Fisheries\Models\FishingVenue;
use Illuminate\Support\Str;

class CreateFishingVenue
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes): FishingVenue
    {
        $venueType = FishingVenueType::from((string) $attributes['venue_type']);

        return FishingVenue::create([
            'organization_id' => $attributes['organization_id'],
            'name' => $attributes['name'],
            'slug' => $this->uniqueSlug((string) $attributes['name']),
            'description' => $attributes['description'] ?? null,
            'venue_type' => $venueType,
            'commercial_status' => $venueType->isPublicWater()
                ? FishingVenueCommercialStatus::InformationOnly
                : FishingVenueCommercialStatus::Bookable,
            'latitude' => $attributes['latitude'] ?? null,
            'longitude' => $attributes['longitude'] ?? null,
            'address' => $attributes['address'] ?? null,
            'city' => $attributes['city'] ?? null,
            'postal_code' => $attributes['postal_code'] ?? null,
            'voivodeship' => $attributes['voivodeship'] ?? null,
            'country' => $attributes['country'] ?? 'PL',
            'phone' => $attributes['phone'] ?? null,
            'email' => $attributes['email'] ?? null,
            'website' => $attributes['website'] ?? null,
            'rules' => $attributes['rules'] ?? null,
            'check_in_instructions' => $attributes['check_in_instructions'] ?? null,
            'status' => FishingVenueStatus::Draft,
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);

        if ($baseSlug === '') {
            $baseSlug = Str::lower(Str::random(8));
        }

        $slug = $baseSlug;
        $suffix = 2;

        while (FishingVenue::query()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
