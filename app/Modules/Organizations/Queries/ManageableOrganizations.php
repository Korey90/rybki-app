<?php

namespace App\Modules\Organizations\Queries;

use App\Models\User;
use App\Modules\Organizations\Enums\OrganizationMemberRole;
use App\Modules\Organizations\Models\OrganizationMember;
use Illuminate\Database\Eloquent\Collection;

class ManageableOrganizations
{
    /**
     * @return Collection<int, OrganizationMember>
     */
    public function membershipsFor(User $user): Collection
    {
        return $user->organizationMemberships()
            ->with('organization')
            ->whereIn('role', [
                OrganizationMemberRole::Owner,
                OrganizationMemberRole::Manager,
            ])
            ->get()
            ->sortBy(fn (OrganizationMember $membership): string => $membership->organization->name)
            ->values();
    }

    /**
     * @return array<int, int>
     */
    public function idsFor(User $user): array
    {
        return $this->membershipsFor($user)
            ->pluck('organization_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    public function canManageOrganization(User $user, int $organizationId): bool
    {
        return $user->organizationMemberships()
            ->where('organization_id', $organizationId)
            ->whereIn('role', [
                OrganizationMemberRole::Owner,
                OrganizationMemberRole::Manager,
            ])
            ->exists();
    }
}
