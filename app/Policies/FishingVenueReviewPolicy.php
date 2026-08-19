<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Reviews\Models\FishingVenueReview;
use App\Policies\Concerns\HandlesOrganizationAuthorization;

class FishingVenueReviewPolicy
{
    use HandlesOrganizationAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_admin || $user->canUseOperatorPanel();
    }

    public function create(User $user, FishingVenue $venue, Booking $booking): bool
    {
        return $booking->user_id === $user->id
            && $booking->fishing_venue_id === $venue->id;
    }

    public function respond(User $user, FishingVenueReview $review): bool
    {
        return $user->is_admin
            || $this->managesOrganization($user, $review->venue?->organization_id);
    }

    public function moderate(User $user): bool
    {
        return $user->is_admin;
    }
}
