<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\StoreOrganizationRequest;
use App\Models\User;
use App\Modules\Organizations\Actions\CreateOrganization;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    /**
     * Show organizations available to the authenticated operator.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        assert($user instanceof User);
        Gate::authorize('viewAny', Organization::class);

        $memberships = $user->organizationMemberships()
            ->with('organization')
            ->get()
            ->sortBy(fn (OrganizationMember $membership): string => $membership->organization->name)
            ->values();

        return Inertia::render('operator/organizations/index', [
            'organizations' => $memberships->map(function (OrganizationMember $membership): array {
                $organization = $membership->organization;

                return [
                    'uuid' => $organization->uuid,
                    'name' => $organization->name,
                    'legal_name' => $organization->legal_name,
                    'tax_id' => $organization->tax_id,
                    'regon' => $organization->regon,
                    'country' => $organization->country,
                    'address' => $organization->address,
                    'verification_status' => $organization->verification_status->value,
                    'role' => $membership->role->value,
                    'created_at' => $organization->created_at?->toIso8601String(),
                ];
            })->all(),
        ]);
    }

    /**
     * Create an operator organization for the authenticated user.
     */
    public function store(StoreOrganizationRequest $request, CreateOrganization $createOrganization): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);
        Gate::authorize('create', Organization::class);

        $createOrganization->handle($user, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organization created.')]);

        return to_route('operator.organizations.index');
    }
}
