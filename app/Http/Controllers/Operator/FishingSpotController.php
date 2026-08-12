<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\StoreFishingSpotRequest;
use App\Models\User;
use App\Modules\Fisheries\Actions\CreateFishingSpot;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingVenue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class FishingSpotController extends Controller
{
    /**
     * Create a spot inside an owned fishing venue.
     */
    public function store(
        StoreFishingSpotRequest $request,
        FishingVenue $fishingVenue,
        CreateFishingSpot $createFishingSpot
    ): RedirectResponse {
        $user = $request->user();
        assert($user instanceof User);

        Gate::authorize('create', [FishingSpot::class, $fishingVenue]);

        $createFishingSpot->handle($fishingVenue, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Fishing spot created.')]);

        return to_route('operator.fishing-venues.show', $fishingVenue);
    }
}
