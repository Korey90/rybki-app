<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Availability\Services\AvailabilityService;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Pricing\Services\PricingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FishingVenueAvailabilityController extends Controller
{
    /**
     * Show available spots with price quotes for a requested range.
     */
    public function __invoke(
        Request $request,
        FishingVenue $fishingVenue,
        AvailabilityService $availabilityService,
        PricingService $pricingService
    ): JsonResponse {
        $validated = Validator::make($request->query(), [
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ])->validate();

        $startsAt = CarbonImmutable::parse((string) $validated['starts_at']);
        $endsAt = CarbonImmutable::parse((string) $validated['ends_at']);

        $availableSpots = $fishingVenue->spots()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (FishingSpot $spot) use ($availabilityService, $pricingService, $startsAt, $endsAt): ?array {
                $availability = $availabilityService->check($spot, $startsAt, $endsAt);

                if (! $availability->available) {
                    return null;
                }

                return [
                    'uuid' => $spot->uuid,
                    'name' => $spot->name,
                    'capacity' => $spot->capacity,
                    'price' => $pricingService->quote($spot, $startsAt, $endsAt)->toArray(),
                ];
            })
            ->filter()
            ->values();

        return response()->json([
            'venue' => [
                'uuid' => $fishingVenue->uuid,
                'name' => $fishingVenue->name,
                'slug' => $fishingVenue->slug,
            ],
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $endsAt->toIso8601String(),
            'available_spots' => $availableSpots,
        ]);
    }
}
