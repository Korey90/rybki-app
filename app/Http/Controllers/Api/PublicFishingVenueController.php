<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Fisheries\Enums\FishingVenueCommercialStatus;
use App\Modules\Fisheries\Enums\FishingVenueStatus;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Fisheries\Queries\FishingVenueGeoQuery;
use App\Modules\GovernmentData\Models\FishingVenueSource;
use App\Modules\Reviews\Enums\FishingVenueReviewStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicFishingVenueController extends Controller
{
    public function __construct(private readonly FishingVenueGeoQuery $fishingVenueGeoQuery) {}

    /**
     * List public fishing venues.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'integer', 'min:1', 'max:'.FishingVenueGeoQuery::MAX_RADIUS_KM],
            'sort' => ['nullable', 'string', 'in:name,distance'],
        ]);
        $latitude = isset($validated['latitude']) ? (float) $validated['latitude'] : null;
        $longitude = isset($validated['longitude']) ? (float) $validated['longitude'] : null;
        $radiusKm = isset($validated['radius_km']) ? (int) $validated['radius_km'] : null;
        $sortByDistance = $latitude !== null
            && $longitude !== null
            && (($validated['sort'] ?? 'distance') === 'distance');

        $query = FishingVenue::query()
            ->with('spots')
            ->withCount(['reviews as reviews_count' => fn ($query) => $query->where('status', FishingVenueReviewStatus::Published->value)])
            ->withAvg(['reviews as rating_average' => fn ($query) => $query->where('status', FishingVenueReviewStatus::Published->value)], 'rating')
            ->where('status', FishingVenueStatus::Published->value)
            ->whereIn('commercial_status', $this->publicCommercialStatuses())
            ->when($validated['q'] ?? null, fn ($query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->when($validated['city'] ?? null, fn ($query, string $city) => $query->where('city', 'like', "%{$city}%"));

        $this->fishingVenueGeoQuery->apply($query, $latitude, $longitude, $radiusKm, $sortByDistance);

        $venues = $query
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->when(
                $sortByDistance,
                fn ($venues) => $venues->sortBy(fn (FishingVenue $venue): float => $this->fishingVenueGeoQuery->distanceKm($venue, $latitude, $longitude) ?? PHP_FLOAT_MAX)->values(),
            );

        return response()->json([
            'venues' => $venues->map(fn (FishingVenue $venue): array => $this->venuePayload($venue, $latitude, $longitude))->all(),
        ]);
    }

    /**
     * Show a public fishing venue.
     */
    public function show(FishingVenue $fishingVenue): JsonResponse
    {
        abort_unless($this->isPubliclyVisible($fishingVenue), 404);

        $fishingVenue->load(['spots', 'sources.dataSource']);
        $fishingVenue->loadCount(['reviews as reviews_count' => fn ($query) => $query->where('status', FishingVenueReviewStatus::Published->value)]);
        $fishingVenue->loadAvg(['reviews as rating_average' => fn ($query) => $query->where('status', FishingVenueReviewStatus::Published->value)], 'rating');

        return response()->json([
            'venue' => $this->venuePayload($fishingVenue),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function venuePayload(FishingVenue $venue, ?float $latitude = null, ?float $longitude = null): array
    {
        $startingPrice = $venue->spots->min('base_price_amount');
        $firstSpot = $venue->spots->first();
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
            'distance_km' => $this->fishingVenueGeoQuery->distanceKm($venue, $latitude, $longitude),
            'starting_price_amount' => $startingPrice !== null ? (int) $startingPrice : null,
            'currency' => $firstSpot instanceof FishingSpot ? $firstSpot->currency : 'PLN',
            'rating_average' => $ratingAverage !== null ? round((float) $ratingAverage, 1) : null,
            'reviews_count' => (int) ($venue->getAttribute('reviews_count') ?? 0),
            'sources' => $venue->relationLoaded('sources')
                ? $venue->sources->map(fn (FishingVenueSource $source): array => [
                    'provider' => $source->dataSource->provider,
                    'dataset_name' => $source->dataSource->dataset_name,
                    'dataset_url' => $source->dataSource->dataset_url,
                    'external_id' => $source->external_id,
                    'source_updated_at' => $source->source_updated_at?->toIso8601String(),
                    'last_synced_at' => $source->last_synced_at?->toIso8601String(),
                ])->values()->all()
                : [],
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

    private function isPubliclyVisible(FishingVenue $fishingVenue): bool
    {
        return $fishingVenue->status === FishingVenueStatus::Published
            && in_array($fishingVenue->commercial_status->value, $this->publicCommercialStatuses(), true);
    }
}
