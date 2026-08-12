<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\StoreCancellationPolicyRequest;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditLog;
use App\Modules\Bookings\Models\CancellationPolicy;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Organizations\Queries\ManageableOrganizations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CancellationPolicyController extends Controller
{
    /**
     * Show cancellation policies for manageable organizations.
     */
    public function index(Request $request, ManageableOrganizations $manageableOrganizations): Response
    {
        $user = $request->user();
        assert($user instanceof User);
        Gate::authorize('viewAny', Organization::class);

        $memberships = $manageableOrganizations->membershipsFor($user);
        $organizationIds = $memberships->pluck('organization_id');

        $policies = CancellationPolicy::query()
            ->with('organization')
            ->whereIn('organization_id', $organizationIds)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return Inertia::render('operator/cancellation-policies/index', [
            'organizations' => $memberships->map(fn (OrganizationMember $membership): array => [
                'id' => $membership->organization->id,
                'name' => $membership->organization->name,
            ])->values()->all(),
            'policies' => $policies->map(fn (CancellationPolicy $policy): array => [
                'id' => $policy->id,
                'name' => $policy->name,
                'free_cancellation_hours' => $policy->free_cancellation_hours,
                'late_refund_percentage' => $policy->late_refund_percentage,
                'is_default' => $policy->is_default,
                'organization' => [
                    'id' => $policy->organization->id,
                    'name' => $policy->organization->name,
                ],
            ])->values()->all(),
        ]);
    }

    /**
     * Store a cancellation policy.
     */
    public function store(
        StoreCancellationPolicyRequest $request,
        RecordAuditLog $recordAuditLog
    ): RedirectResponse {
        $user = $request->user();
        assert($user instanceof User);

        $validated = $request->validated();
        $organizationId = (int) $validated['organization_id'];
        $organization = Organization::query()->findOrFail($organizationId);
        Gate::authorize('manage', $organization);

        $policy = DB::transaction(function () use ($organizationId, $validated): CancellationPolicy {
            if ((bool) $validated['is_default']) {
                CancellationPolicy::query()
                    ->where('organization_id', $organizationId)
                    ->update(['is_default' => false]);
            }

            return CancellationPolicy::create([
                'organization_id' => $organizationId,
                'name' => $validated['name'],
                'free_cancellation_hours' => $validated['free_cancellation_hours'],
                'late_refund_percentage' => $validated['late_refund_percentage'],
                'is_default' => $validated['is_default'],
            ]);
        });

        $recordAuditLog->handle(
            action: 'cancellation_policy.created',
            subject: $policy,
            actor: $user,
            organization: $organization,
            after: [
                'name' => $policy->name,
                'free_cancellation_hours' => $policy->free_cancellation_hours,
                'late_refund_percentage' => $policy->late_refund_percentage,
                'is_default' => $policy->is_default,
            ],
            request: $request,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Polityka anulacji została dodana.',
        ]);
    }

    /**
     * Mark a policy as the default for its organization.
     */
    public function makeDefault(
        Request $request,
        CancellationPolicy $cancellationPolicy,
        RecordAuditLog $recordAuditLog
    ): RedirectResponse {
        $user = $request->user();
        assert($user instanceof User);

        $organization = $cancellationPolicy->organization;
        assert($organization instanceof Organization);

        Gate::authorize('manage', $organization);

        DB::transaction(function () use ($cancellationPolicy): void {
            CancellationPolicy::query()
                ->where('organization_id', $cancellationPolicy->organization_id)
                ->update(['is_default' => false]);

            $cancellationPolicy->forceFill(['is_default' => true])->save();
        });

        $recordAuditLog->handle(
            action: 'cancellation_policy.default_changed',
            subject: $cancellationPolicy,
            actor: $user,
            organization: $organization,
            after: [
                'policy_id' => $cancellationPolicy->id,
                'name' => $cancellationPolicy->name,
            ],
            request: $request,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Domyślna polityka anulacji została zmieniona.',
        ]);
    }
}
