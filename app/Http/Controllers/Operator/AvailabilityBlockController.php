<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\StoreAvailabilityBlockRequest;
use App\Models\User;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingVenue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class AvailabilityBlockController extends Controller
{
    /**
     * Create a manual block for a spot.
     */
    public function store(StoreAvailabilityBlockRequest $request, FishingVenue $fishingVenue): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $spot = $this->authorizedSpot($user, $fishingVenue, (int) $request->validated('fishing_spot_id'));

        $spot->availabilityBlocks()->create([
            ...$request->safe()->except('fishing_spot_id'),
            'created_by' => $user->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Availability block created.')]);

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
