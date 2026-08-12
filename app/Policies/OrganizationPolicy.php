<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Policies\Concerns\HandlesOrganizationAuthorization;

class OrganizationPolicy
{
    use HandlesOrganizationAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_admin || $user->canUseOperatorPanel();
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->is_admin || $this->belongsToOrganization($user, $organization->id);
    }

    public function create(User $user): bool
    {
        return $user->is_admin || $user->canUseOperatorPanel();
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->is_admin || $this->managesOrganization($user, $organization->id);
    }

    public function manage(User $user, Organization $organization): bool
    {
        return $this->update($user, $organization);
    }
}
