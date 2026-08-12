<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\GovernmentData\Models\FishingVenueClaim;
use App\Modules\Organizations\Models\Organization;
use App\Policies\Concerns\HandlesOrganizationAuthorization;

class FishingVenueClaimPolicy
{
    use HandlesOrganizationAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function create(User $user, FishingVenue $fishingVenue, Organization $organization): bool
    {
        if (! $this->managesOrganization($user, $organization->id)) {
            return false;
        }

        return $fishingVenue->organization_id === null
            || $fishingVenue->organization_id === $organization->id;
    }

    public function review(User $user, FishingVenueClaim $fishingVenueClaim): bool
    {
        return $user->is_admin;
    }
}
