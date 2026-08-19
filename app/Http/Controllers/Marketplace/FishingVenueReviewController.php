<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Fisheries\Enums\FishingVenueCommercialStatus;
use App\Modules\Fisheries\Enums\FishingVenueStatus;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Reviews\Actions\CreateFishingVenueReview;
use App\Modules\Reviews\Models\FishingVenueReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FishingVenueReviewController extends Controller
{
    public function store(
        Request $request,
        FishingVenue $fishingVenue,
        CreateFishingVenueReview $createFishingVenueReview
    ): RedirectResponse {
        abort_unless($this->isPubliclyVisible($fishingVenue), 404);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'booking_uuid' => ['required', 'string', 'exists:bookings,uuid'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        $booking = Booking::query()
            ->where('uuid', $validated['booking_uuid'])
            ->firstOrFail();

        Gate::authorize('create', [FishingVenueReview::class, $fishingVenue, $booking]);

        $createFishingVenueReview->handle(
            user: $user,
            venue: $fishingVenue,
            booking: $booking,
            rating: (int) $validated['rating'],
            title: $validated['title'] ?? null,
            body: $validated['body'] ?? null,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Opinia została opublikowana.',
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
