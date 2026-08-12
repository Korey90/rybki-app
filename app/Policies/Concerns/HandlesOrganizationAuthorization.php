<?php

namespace App\Policies\Concerns;

use App\Models\User;
use App\Modules\Organizations\Enums\OrganizationMemberRole;

trait HandlesOrganizationAuthorization
{
    protected function managesOrganization(User $user, ?int $organizationId): bool
    {
        if ($organizationId === null) {
            return false;
        }

        return $user->organizationMemberships()
            ->where('organization_id', $organizationId)
            ->whereIn('role', [
                OrganizationMemberRole::Owner,
                OrganizationMemberRole::Manager,
            ])
            ->exists();
    }

    protected function belongsToOrganization(User $user, ?int $organizationId): bool
    {
        if ($organizationId === null) {
            return false;
        }

        return $user->organizationMemberships()
            ->where('organization_id', $organizationId)
            ->exists();
    }
}
