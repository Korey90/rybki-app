<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Availability\Services\AvailabilityService;
use App\Modules\Fisheries\Enums\FishingSpotStatus;
use App\Modules\Fisheries\Enums\FishingVenueCommercialStatus;
use App\Modules\Fisheries\Enums\FishingVenueStatus;
use App\Modules\Fisheries\Models\Amenity;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingSpotMedia;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Fisheries\Models\FishingVenueMedia;
use App\Modules\Fisheries\Models\FishSpecies;
use App\Modules\Fisheries\Queries\FishingVenueGeoQuery;
use App\Modules\Fisheries\Support\FishingMediaPayload;
use App\Modules\GovernmentData\Models\FishingVenueSource;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Organizations\Queries\ManageableOrganizations;
use App\Modules\Pricing\Services\PricingService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class FishingVenueController extends Controller
{
    public function __construct(
        private readonly AvailabilityService $availabilityService,
        private readonly PricingService $pricingService,
        private readonly FishingMediaPayload $fishingMediaPayload,
        private readonly FishingVenueGeoQuery $fishingVenueGeoQuery,
    ) {}

    /**
     * Show public fishing venue search.
     */
    public function index(Request $request): Response
    {
        $filters = $this->validatedFilters($request);
        $startsAt = $this->dateFilter($filters, 'starts_at');
        $endsAt = $this->dateFilter($filters, 'ends_at');
        $availableOnly = (bool) Arr::get($filters, 'available_only', false);
        $latitude = $this->coordinateFilter($filters, 'latitude');
        $longitude = $this->coordinateFilter($filters, 'longitude');
        $radiusKm = $this->integerFilter($filters, 'radius_km');
        $sortByDistance = $latitude !== null
            && $longitude !== null
            && ((string) Arr::get($filters, 'sort', 'distance') === 'distance');

        $query = $this->publicVenueQuery()
            ->with([
                'spots' => fn ($query) => $query->where('status', FishingSpotStatus::Active->value)->orderBy('base_price_amount'),
                'fishSpecies',
                'amenities',
                'media' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            ])
            ->when(Arr::get($filters, 'q'), function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('voivodeship', 'like', "%{$search}%");
                });
            })
            ->when(Arr::get($filters, 'city'), fn (Builder $query, string $city) => $query->where('city', 'like', "%{$city}%"));

        $this->fishingVenueGeoQuery->apply($query, $latitude, $longitude, $radiusKm, $sortByDistance);

        $venues = $query
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->map(fn (FishingVenue $venue): array => $this->venueListPayload($venue, $startsAt, $endsAt, $latitude, $longitude))
            ->when(
                $availableOnly && $startsAt !== null && $endsAt !== null,
                fn ($venues) => $venues->filter(fn (array $venue): bool => (int) $venue['available_spots_count'] > 0)->values(),
            )
            ->when(
                $sortByDistance,
                fn ($venues) => $venues->sortBy(fn (array $venue): float => (float) ($venue['distance_km'] ?? PHP_FLOAT_MAX))->values(),
            )
            ->values();

        return Inertia::render('marketplace/fishing-venues/index', [
            'venues' => $venues->all(),
            'filters' => [
                'q' => Arr::get($filters, 'q'),
                'city' => Arr::get($filters, 'city'),
                'starts_at' => Arr::get($filters, 'starts_at'),
                'ends_at' => Arr::get($filters, 'ends_at'),
                'available_only' => $availableOnly,
                'latitude' => Arr::get($filters, 'latitude'),
                'longitude' => Arr::get($filters, 'longitude'),
                'radius_km' => Arr::get($filters, 'radius_km') ?? FishingVenueGeoQuery::DEFAULT_RADIUS_KM,
                'sort' => $sortByDistance ? 'distance' : 'name',
            ],
        ]);
    }

    /**
     * Show a public fishing venue profile.
     */
    public function show(
        Request $request,
        FishingVenue $fishingVenue,
        ManageableOrganizations $manageableOrganizations
    ): Response {
        abort_unless($this->isPubliclyVisible($fishingVenue), 404);

        $filters = $this->validatedAvailabilityFilters($request);
        $startsAt = $this->dateFilter($filters, 'starts_at');
        $endsAt = $this->dateFilter($filters, 'ends_at');

        $fishingVenue->load([
            'media' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            'spots' => fn ($query) => $query
                ->with(['media' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
                ->where('status', FishingSpotStatus::Active->value)
                ->orderBy('sort_order')
                ->orderBy('name'),
            'fishSpecies',
            'amenities',
            'sources.dataSource',
        ]);

        $user = $request->user();

        return Inertia::render('marketplace/fishing-venues/show', [
            'venue' => $this->venueDetailPayload($fishingVenue, $startsAt, $endsAt),
            'availability_query' => [
                'starts_at' => Arr::get($filters, 'starts_at'),
                'ends_at' => Arr::get($filters, 'ends_at'),
            ],
            'claim_organizations' => ! $user instanceof User ? [] : $manageableOrganizations
                ->membershipsFor($user)
                ->map(fn (OrganizationMember $membership): array => [
                    'id' => $membership->organization->id,
                    'name' => $membership->organization->name,
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return Builder<FishingVenue>
     */
    private function publicVenueQuery(): Builder
    {
        return FishingVenue::query()
            ->where('status', FishingVenueStatus::Published->value)
            ->whereIn('commercial_status', $this->publicCommercialStatuses());
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'available_only' => ['nullable', 'boolean'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'integer', 'min:1', 'max:'.FishingVenueGeoQuery::MAX_RADIUS_KM],
            'sort' => ['nullable', 'string', 'in:name,distance'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedAvailabilityFilters(Request $request): array
    {
        return $request->validate([
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function dateFilter(array $filters, string $key): ?CarbonImmutable
    {
        $value = Arr::get($filters, $key);

        return is_string($value) ? CarbonImmutable::parse($value) : null;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function coordinateFilter(array $filters, string $key): ?float
    {
        $value = Arr::get($filters, $key);

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function integerFilter(array $filters, string $key): ?int
    {
        $value = Arr::get($filters, $key);

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function venueListPayload(
        FishingVenue $venue,
        ?CarbonImmutable $startsAt,
        ?CarbonImmutable $endsAt,
        ?float $originLatitude = null,
        ?float $originLongitude = null,
    ): array {
        $availableSpotsCount = null;

        if ($venue->commercial_status === FishingVenueCommercialStatus::Bookable && $startsAt !== null && $endsAt !== null) {
            $availableSpotsCount = $venue->spots
                ->filter(fn (FishingSpot $spot): bool => $this->availabilityService->check($spot, $startsAt, $endsAt)->available)
                ->count();
        }

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
            'available_spots_count' => $availableSpotsCount,
            'distance_km' => $this->fishingVenueGeoQuery->distanceKm($venue, $originLatitude, $originLongitude),
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
     * @return array<string, mixed>
     */
    private function venueDetailPayload(FishingVenue $venue, ?CarbonImmutable $startsAt, ?CarbonImmutable $endsAt): array
    {
        return [
            ...$this->venueListPayload($venue, $startsAt, $endsAt),
            'address' => $venue->address,
            'postal_code' => $venue->postal_code,
            'country' => $venue->country,
            'phone' => $venue->phone,
            'email' => $venue->email,
            'website' => $venue->website,
            'rules' => $venue->rules,
            'check_in_instructions' => $venue->check_in_instructions,
            'can_be_claimed' => $venue->organization_id === null,
            'media' => $venue->media
                ->values()
                ->map(fn (FishingVenueMedia $media, int $index): array => $this->fishingMediaPayload->venue($media, $index === 0))
                ->all(),
            'sources' => $venue->sources->map(fn (FishingVenueSource $source): array => [
                'provider' => $source->dataSource->provider,
                'dataset_name' => $source->dataSource->dataset_name,
                'dataset_url' => $source->dataSource->dataset_url,
                'external_id' => $source->external_id,
                'source_updated_at' => $source->source_updated_at?->toIso8601String(),
                'last_synced_at' => $source->last_synced_at?->toIso8601String(),
            ])->values()->all(),
            'spots' => $venue->spots->map(fn (FishingSpot $spot): array => $this->spotPayload($spot, $startsAt, $endsAt))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function spotPayload(FishingSpot $spot, ?CarbonImmutable $startsAt, ?CarbonImmutable $endsAt): array
    {
        $availability = null;
        $price = null;

        if ($startsAt !== null && $endsAt !== null) {
            $availabilityResult = $this->availabilityService->check($spot, $startsAt, $endsAt);
            $availability = [
                'available' => $availabilityResult->available,
                'reason' => $availabilityResult->reason,
            ];

            if ($availabilityResult->available) {
                $price = $this->pricingService->quote($spot, $startsAt, $endsAt)->toArray();
            }
        }

        return [
            'uuid' => $spot->uuid,
            'name' => $spot->name,
            'description' => $spot->description,
            'capacity' => $spot->capacity,
            'base_price_amount' => $spot->base_price_amount,
            'currency' => $spot->currency,
            'media' => $spot->media
                ->values()
                ->map(fn (FishingSpotMedia $media, int $index): array => $this->fishingMediaPayload->spot($media, $index === 0))
                ->all(),
            'availability' => $availability,
            'price' => $price,
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
