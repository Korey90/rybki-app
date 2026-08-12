<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\StoreFishingVenueRequest;
use App\Models\User;
use App\Modules\Availability\Models\AvailabilityBlock;
use App\Modules\Availability\Models\AvailabilityRule;
use App\Modules\Fisheries\Actions\CreateFishingVenue;
use App\Modules\Fisheries\Enums\FishingVenueStatus;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingSpotMedia;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Fisheries\Models\FishingVenueMedia;
use App\Modules\Fisheries\Support\FishingMediaPayload;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Organizations\Queries\ManageableOrganizations;
use App\Modules\Pricing\Models\PricingRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FishingVenueController extends Controller
{
    public function __construct(
        private readonly ManageableOrganizations $manageableOrganizations,
        private readonly FishingMediaPayload $fishingMediaPayload,
    ) {}

    /**
     * Show fishing venues managed by the authenticated operator.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        assert($user instanceof User);
        Gate::authorize('viewAny', FishingVenue::class);

        $memberships = $this->manageableOrganizations->membershipsFor($user);
        $organizationIds = $memberships->pluck('organization_id');

        $venues = FishingVenue::query()
            ->with([
                'organization',
                'media' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            ])
            ->withCount('spots')
            ->whereIn('organization_id', $organizationIds)
            ->latest()
            ->get();

        return Inertia::render('operator/fishing-venues/index', [
            'organizations' => $memberships->map(function (OrganizationMember $membership): array {
                $organization = $membership->organization;

                return [
                    'id' => $organization->id,
                    'uuid' => $organization->uuid,
                    'name' => $organization->name,
                    'verification_status' => $organization->verification_status->value,
                ];
            })->values()->all(),
            'venues' => $venues->map(fn (FishingVenue $venue): array => $this->venueListPayload($venue))->all(),
        ]);
    }

    /**
     * Create a fishing venue under one of the user's organizations.
     */
    public function store(StoreFishingVenueRequest $request, CreateFishingVenue $createFishingVenue): RedirectResponse
    {
        $organization = Organization::query()->findOrFail((int) $request->validated('organization_id'));
        Gate::authorize('create', [FishingVenue::class, $organization]);

        $venue = $createFishingVenue->handle($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Fishing venue created.')]);

        return to_route('operator.fishing-venues.show', $venue);
    }

    /**
     * Show a single operator fishing venue with its spots.
     */
    public function show(Request $request, FishingVenue $fishingVenue): Response
    {
        $user = $request->user();
        assert($user instanceof User);

        Gate::authorize('view', $fishingVenue);

        $fishingVenue->load([
            'organization',
            'media' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            'spots' => fn ($query) => $query->with([
                'media' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
                'availabilityRules' => fn ($query) => $query->orderBy('day_of_week')->orderBy('opens_at'),
                'availabilityBlocks' => fn ($query) => $query->latest('starts_at'),
                'pricingRules' => fn ($query) => $query->orderByDesc('priority')->orderByDesc('starts_at'),
            ])->orderBy('sort_order')->orderBy('name'),
        ]);

        return Inertia::render('operator/fishing-venues/show', [
            'venue' => $this->venueDetailPayload($fishingVenue),
        ]);
    }

    /**
     * Publish a fishing venue.
     */
    public function publish(Request $request, FishingVenue $fishingVenue): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        Gate::authorize('publish', $fishingVenue);

        $fishingVenue->forceFill([
            'status' => FishingVenueStatus::Published,
            'published_at' => $fishingVenue->published_at ?? now(),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Fishing venue published.')]);

        return to_route('operator.fishing-venues.show', $fishingVenue);
    }

    /**
     * @return array<string, mixed>
     */
    private function venueListPayload(FishingVenue $venue): array
    {
        $organization = $venue->organization;
        assert($organization instanceof Organization);
        $primaryMedia = $venue->media->first();

        return [
            'uuid' => $venue->uuid,
            'name' => $venue->name,
            'slug' => $venue->slug,
            'city' => $venue->city,
            'voivodeship' => $venue->voivodeship,
            'venue_type' => $venue->venue_type->value,
            'commercial_status' => $venue->commercial_status->value,
            'status' => $venue->status->value,
            'published_at' => $venue->published_at?->toIso8601String(),
            'spots_count' => $venue->spots_count,
            'media_count' => $venue->media->count(),
            'primary_media' => $primaryMedia instanceof FishingVenueMedia
                ? $this->fishingMediaPayload->venue($primaryMedia, true)
                : null,
            'organization' => [
                'uuid' => $organization->uuid,
                'name' => $organization->name,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function venueDetailPayload(FishingVenue $venue): array
    {
        $organization = $venue->organization;
        assert($organization instanceof Organization);

        return [
            'uuid' => $venue->uuid,
            'name' => $venue->name,
            'slug' => $venue->slug,
            'description' => $venue->description,
            'venue_type' => $venue->venue_type->value,
            'commercial_status' => $venue->commercial_status->value,
            'latitude' => $venue->latitude,
            'longitude' => $venue->longitude,
            'address' => $venue->address,
            'city' => $venue->city,
            'postal_code' => $venue->postal_code,
            'voivodeship' => $venue->voivodeship,
            'country' => $venue->country,
            'phone' => $venue->phone,
            'email' => $venue->email,
            'website' => $venue->website,
            'rules' => $venue->rules,
            'check_in_instructions' => $venue->check_in_instructions,
            'status' => $venue->status->value,
            'published_at' => $venue->published_at?->toIso8601String(),
            'media' => $venue->media
                ->values()
                ->map(fn (FishingVenueMedia $media, int $index): array => $this->fishingMediaPayload->venue($media, $index === 0))
                ->all(),
            'organization' => [
                'uuid' => $organization->uuid,
                'name' => $organization->name,
            ],
            'spots' => $venue->spots->map(fn (FishingSpot $spot): array => [
                'id' => $spot->id,
                'uuid' => $spot->uuid,
                'name' => $spot->name,
                'description' => $spot->description,
                'capacity' => $spot->capacity,
                'base_price_amount' => $spot->base_price_amount,
                'currency' => $spot->currency,
                'status' => $spot->status->value,
                'sort_order' => $spot->sort_order,
                'media' => $spot->media
                    ->values()
                    ->map(fn (FishingSpotMedia $media, int $index): array => $this->fishingMediaPayload->spot($media, $index === 0))
                    ->all(),
                'availability_rules' => $spot->availabilityRules->map(fn (AvailabilityRule $rule): array => [
                    'id' => $rule->id,
                    'day_of_week' => $rule->day_of_week,
                    'opens_at' => substr($rule->opens_at, 0, 5),
                    'closes_at' => substr($rule->closes_at, 0, 5),
                    'valid_from' => $rule->valid_from?->toDateString(),
                    'valid_until' => $rule->valid_until?->toDateString(),
                    'minimum_booking_minutes' => $rule->minimum_booking_minutes,
                    'maximum_booking_minutes' => $rule->maximum_booking_minutes,
                ])->values()->all(),
                'availability_blocks' => $spot->availabilityBlocks->map(fn (AvailabilityBlock $block): array => [
                    'id' => $block->id,
                    'starts_at' => $block->starts_at->toIso8601String(),
                    'ends_at' => $block->ends_at->toIso8601String(),
                    'reason' => $block->reason,
                ])->values()->all(),
                'pricing_rules' => $spot->pricingRules->map(fn (PricingRule $rule): array => [
                    'id' => $rule->id,
                    'starts_at' => $rule->starts_at?->toIso8601String(),
                    'ends_at' => $rule->ends_at?->toIso8601String(),
                    'day_of_week' => $rule->day_of_week,
                    'price_amount' => $rule->price_amount,
                    'currency' => $rule->currency,
                    'minimum_duration_minutes' => $rule->minimum_duration_minutes,
                    'priority' => $rule->priority,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }
}
