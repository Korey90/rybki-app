<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserAccountType;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Organizations\Enums\OrganizationMemberRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AuthenticatedHomeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        if ($user->is_admin) {
            return to_route('admin.audit-logs.index');
        }

        $hasManageableOrganization = $user->organizationMemberships()
            ->whereIn('role', [
                OrganizationMemberRole::Owner,
                OrganizationMemberRole::Manager,
            ])
            ->exists();

        if ($user->account_type === UserAccountType::VenueOwner) {
            return $hasManageableOrganization
                ? to_route('operator.dashboard')
                : to_route('operator.organizations.index');
        }

        if ($hasManageableOrganization) {
            return to_route('operator.dashboard');
        }

        return to_route('marketplace.fishing-venues.index');
    }
}
