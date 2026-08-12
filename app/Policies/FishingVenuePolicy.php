<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Organizations\Models\Organization;
use App\Policies\Concerns\HandlesOrganizationAuthorization;

class FishingVenuePolicy
{
    use HandlesOrganizationAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_admin || $user->canUseOperatorPanel();
    }

    public function view(User $user, FishingVenue $fishingVenue): bool
    {
        return $user->is_admin || $this->managesOrganization($user, $fishingVenue->organization_id);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->is_admin || $this->managesOrganization($user, $organization->id);
    }

    public function update(User $user, FishingVenue $fishingVenue): bool
    {
        return $this->view($user, $fishingVenue);
    }

    public function publish(User $user, FishingVenue $fishingVenue): bool
    {
        return $this->update($user, $fishingVenue);
    }
}
