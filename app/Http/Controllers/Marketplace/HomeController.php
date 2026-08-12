<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Modules\Bookings\Enums\BookingStatus;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Fisheries\Enums\FishingVenueCommercialStatus;
use App\Modules\Fisheries\Enums\FishingVenueStatus;
use App\Modules\Fisheries\Models\Amenity;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Fisheries\Models\FishingVenueMedia;
use App\Modules\Fisheries\Models\FishSpecies;
use App\Modules\Fisheries\Support\FishingMediaPayload;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(private readonly FishingMediaPayload $fishingMediaPayload) {}

    /**
     * Show the marketplace home page.
     */
    public function __invoke(): Response
    {
        $venues = FishingVenue::query()
            ->with([
                'amenities',
                'fishSpecies',
                'media' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
                'spots' => fn ($query) => $query->orderBy('base_price_amount'),
            ])
            ->where('status', FishingVenueStatus::Published->value)
            ->whereIn('commercial_status', $this->publicCommercialStatuses())
            ->latest('published_at')
            ->limit(6)
            ->get();

        return Inertia::render('marketplace/home', [
            'featured_venues' => $venues->map(fn (FishingVenue $venue): array => $this->venuePayload($venue))->all(),
            'stats' => [
                'venues_count' => FishingVenue::query()
                    ->where('status', FishingVenueStatus::Published->value)
                    ->whereIn('commercial_status', $this->publicCommercialStatuses())
                    ->count(),
                'spots_count' => FishingSpot::query()
                    ->whereHas('venue', fn ($query) => $query
                        ->where('status', FishingVenueStatus::Published->value)
                        ->where('commercial_status', FishingVenueCommercialStatus::Bookable->value))
                    ->count(),
                'confirmed_bookings_count' => Booking::query()
                    ->where('status', BookingStatus::Confirmed->value)
                    ->count(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function venuePayload(FishingVenue $venue): array
    {
        $startingPrice = $venue->spots->min('base_price_amount');
        $firstSpot = $venue->spots->first();
        $currency = $firstSpot instanceof FishingSpot ? $firstSpot->currency : 'PLN';
        $primaryMedia = $venue->media->first();

        return [
            'uuid' => $venue->uuid,
            'name' => $venue->name,
            'slug' => $venue->slug,
            'description' => $venue->description,
            'city' => $venue->city,
            'voivodeship' => $venue->voivodeship,
            'latitude' => $venue->latitude,
            'longitude' => $venue->longitude,
            'venue_type' => $venue->venue_type->value,
            'commercial_status' => $venue->commercial_status->value,
            'is_bookable' => $venue->commercial_status === FishingVenueCommercialStatus::Bookable,
            'spots_count' => $venue->spots->count(),
            'media_count' => $venue->media->count(),
            'primary_media' => $primaryMedia instanceof FishingVenueMedia
                ? $this->fishingMediaPayload->venue($primaryMedia, true)
                : null,
            'distance_km' => null,
            'starting_price_amount' => $startingPrice !== null ? (int) $startingPrice : null,
            'currency' => $currency,
            'fish_species' => $venue->fishSpecies->map(fn (FishSpecies $species): array => [
                'name' => $species->name,
                'slug' => $species->slug,
            ])->values()->all(),
            'amenities' => $venue->amenities->map(fn (Amenity $amenity): array => [
                'name' => $amenity->name,
                'slug' => $amenity->slug,
                'icon' => $amenity->icon,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function publicCommercialStatuses(): array
    {
        return [
            FishingVenueCommercialStatus::Bookable->value,
            FishingVenueCommercialStatus::InformationOnly->value,
            FishingVenueCommercialStatus::Claimable->value,
        ];
    }
}
