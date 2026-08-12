<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Policies\Concerns\HandlesOrganizationAuthorization;

class FishingSpotPolicy
{
    use HandlesOrganizationAuthorization;

    public function create(User $user, FishingVenue $fishingVenue): bool
    {
        return $user->is_admin || $this->managesOrganization($user, $fishingVenue->organization_id);
    }

    public function view(User $user, FishingSpot $fishingSpot): bool
    {
        return $user->is_admin || $this->managesOrganization($user, $fishingSpot->venue?->organization_id);
    }

    public function update(User $user, FishingSpot $fishingSpot): bool
    {
        return $this->view($user, $fishingSpot);
    }

    public function manage(User $user, FishingSpot $fishingSpot): bool
    {
        return $this->update($user, $fishingSpot);
    }
}
