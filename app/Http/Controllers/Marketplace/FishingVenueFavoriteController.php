<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Fisheries\Enums\FishingVenueCommercialStatus;
use App\Modules\Fisheries\Enums\FishingVenueStatus;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Reviews\Actions\SetFishingVenueFavorite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FishingVenueFavoriteController extends Controller
{
    public function store(
        Request $request,
        FishingVenue $fishingVenue,
        SetFishingVenueFavorite $setFishingVenueFavorite
    ): RedirectResponse {
        abort_unless($this->isPubliclyVisible($fishingVenue), 404);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $setFishingVenueFavorite->handle($user, $fishingVenue, true);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Łowisko dodane do ulubionych.',
        ]);
    }

    public function destroy(
        Request $request,
        FishingVenue $fishingVenue,
        SetFishingVenueFavorite $setFishingVenueFavorite
    ): RedirectResponse {
        abort_unless($this->isPubliclyVisible($fishingVenue), 404);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $setFishingVenueFavorite->handle($user, $fishingVenue, false);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Łowisko usunięte z ulubionych.',
        ]);
    }

    private function isPubliclyVisible(FishingVenue $fishingVenue): bool
    {
        return $fishingVenue->status === FishingVenueStatus::Published
            && in_array($fishingVenue->commercial_status, [
                FishingVenueCommercialStatus::Bookable,
                FishingVenueCommercialStatus::InformationOnly,
                FishingVenueCommercialStatus::Claimable,
            ], true);
    }
}
