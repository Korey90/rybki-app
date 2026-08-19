<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Fisheries\Enums\FishingVenueCommercialStatus;
use App\Modules\Fisheries\Enums\FishingVenueStatus;
use App\Modules\Fisheries\Models\Amenity;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Fisheries\Models\FishingVenueMedia;
use App\Modules\Fisheries\Models\FishSpecies;
use App\Modules\Fisheries\Support\FishingMediaPayload;
use App\Modules\Reviews\Enums\FishingVenueReviewStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FavoriteVenueController extends Controller
{
    public function __construct(private readonly FishingMediaPayload $fishingMediaPayload) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        assert($user instanceof User);

        $venues = $user->favoriteFishingVenues()
            ->with([
                'amenities',
                'fishSpecies',
                'media' => fn ($query) => $query->whereNull('hidden_at')->orderBy('sort_order')->orderBy('id'),
                'spots' => fn ($query) => $query->orderBy('base_price_amount'),
            ])
            ->withCount(['reviews as reviews_count' => fn ($query) => $query->where('status', FishingVenueReviewStatus::Published->value)])
            ->withAvg(['reviews as rating_average' => fn ($query) => $query->where('status', FishingVenueReviewStatus::Published->value)], 'rating')
            ->where('status', FishingVenueStatus::Published->value)
            ->whereIn('commercial_status', $this->publicCommercialStatuses())
            ->orderByPivot('created_at', 'desc')
            ->limit(100)
            ->get();

        return Inertia::render('account/favorites/index', [
            'venues' => $venues->map(fn (FishingVenue $venue): array => $this->venuePayload($venue))->values()->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function venuePayload(FishingVenue $venue): array
    {
        $startingPrice = $venue->spots->min('base_price_amount');
        $firstSpot = $venue->spots->first();
        $primaryMedia = $venue->media->first();
        $ratingAverage = $venue->getAttribute('rating_average');

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
            'available_spots_count' => null,
            'starting_price_amount' => $startingPrice !== null ? (int) $startingPrice : null,
            'currency' => $firstSpot instanceof FishingSpot ? $firstSpot->currency : 'PLN',
            'rating_average' => $ratingAverage !== null ? round((float) $ratingAverage, 1) : null,
            'reviews_count' => (int) ($venue->getAttribute('reviews_count') ?? 0),
            'is_favorited' => true,
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
