<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\StoreAvailabilityRuleRequest;
use App\Models\User;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingVenue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class AvailabilityRuleController extends Controller
{
    /**
     * Create an opening-hours rule for a spot.
     */
    public function store(StoreAvailabilityRuleRequest $request, FishingVenue $fishingVenue): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $spot = $this->authorizedSpot($user, $fishingVenue, (int) $request->validated('fishing_spot_id'));

        $spot->availabilityRules()->create($request->safe()->except('fishing_spot_id'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Availability rule created.')]);

        return to_route('operator.fishing-venues.show', $fishingVenue);
    }

    private function authorizedSpot(User $user, FishingVenue $venue, int $spotId): FishingSpot
    {
        Gate::forUser($user)->authorize('update', $venue);

        $spot = $venue->spots()->whereKey($spotId)->firstOrFail();
        Gate::forUser($user)->authorize('manage', $spot);

        return $spot;
    }
}
