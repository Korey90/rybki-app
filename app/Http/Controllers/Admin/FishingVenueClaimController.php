<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditLog;
use App\Modules\GovernmentData\Actions\ReviewFishingVenueClaim;
use App\Modules\GovernmentData\Enums\FishingVenueClaimStatus;
use App\Modules\GovernmentData\Models\FishingVenueClaim;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FishingVenueClaimController extends Controller
{
    /**
     * Show pending and reviewed venue ownership claims.
     */
    public function index(): Response
    {
        Gate::authorize('viewAny', FishingVenueClaim::class);

        $claims = FishingVenueClaim::query()
            ->with(['venue', 'organization', 'reviewer'])
            ->latest()
            ->limit(100)
            ->get();

        return Inertia::render('admin/venue-claims/index', [
            'claims' => $claims->map(fn (FishingVenueClaim $claim): array => [
                'id' => $claim->id,
                'status' => $claim->status->value,
                'evidence' => $claim->evidence,
                'reviewed_at' => $claim->reviewed_at?->toIso8601String(),
                'created_at' => $claim->created_at?->toIso8601String(),
                'venue' => [
                    'name' => $claim->venue->name,
                    'slug' => $claim->venue->slug,
                    'commercial_status' => $claim->venue->commercial_status->value,
                    'organization_id' => $claim->venue->organization_id,
                ],
                'organization' => [
                    'id' => $claim->organization->id,
                    'name' => $claim->organization->name,
                    'verification_status' => $claim->organization->verification_status->value,
                ],
                'reviewer' => $claim->reviewer === null ? null : [
                    'name' => $claim->reviewer->name,
                    'email' => $claim->reviewer->email,
                ],
            ])->values()->all(),
        ]);
    }

    /**
     * Approve or reject a venue ownership claim.
     */
    public function review(
        Request $request,
        FishingVenueClaim $fishingVenueClaim,
        ReviewFishingVenueClaim $reviewFishingVenueClaim,
        RecordAuditLog $recordAuditLog
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                Rule::in([
                    FishingVenueClaimStatus::Approved->value,
                    FishingVenueClaimStatus::Rejected->value,
                ]),
            ],
        ]);

        $user = $request->user();
        abort_unless($user instanceof User, 403);
        Gate::authorize('review', $fishingVenueClaim);

        $previousStatus = $fishingVenueClaim->status->value;

        $claim = $reviewFishingVenueClaim->handle(
            claim: $fishingVenueClaim,
            reviewer: $user,
            status: FishingVenueClaimStatus::from((string) $validated['status']),
        );

        $claim->load('organization', 'venue');

        $recordAuditLog->handle(
            action: 'venue_claim.reviewed',
            subject: $claim,
            actor: $user,
            organization: $claim->organization,
            before: [
                'claim_status' => $previousStatus,
            ],
            after: [
                'claim_status' => $claim->status->value,
                'venue_uuid' => $claim->venue->uuid,
                'venue_organization_id' => $claim->venue->organization_id,
            ],
            request: $request,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Status zgłoszenia został zapisany.',
        ]);
    }
}
