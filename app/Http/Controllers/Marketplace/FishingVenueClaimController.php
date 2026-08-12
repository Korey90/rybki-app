<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketplace\StoreFishingVenueClaimRequest;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditLog;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\GovernmentData\Actions\SubmitFishingVenueClaim;
use App\Modules\GovernmentData\Models\FishingVenueClaim;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class FishingVenueClaimController extends Controller
{
    /**
     * Submit a claim request for an imported or public information venue.
     */
    public function store(
        StoreFishingVenueClaimRequest $request,
        FishingVenue $fishingVenue,
        SubmitFishingVenueClaim $submitFishingVenueClaim,
        RecordAuditLog $recordAuditLog
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $organizationId = (int) $request->validated('organization_id');
        $organization = Organization::query()->findOrFail($organizationId);

        Gate::authorize('create', [FishingVenueClaim::class, $fishingVenue, $organization]);

        $claim = $submitFishingVenueClaim->handle(
            venue: $fishingVenue,
            organization: $organization,
            evidence: $request->string('evidence')->toString() ?: null,
        );

        $recordAuditLog->handle(
            action: 'venue_claim.submitted',
            subject: $claim,
            actor: $user,
            organization: $organization,
            after: [
                'venue_uuid' => $fishingVenue->uuid,
                'venue_name' => $fishingVenue->name,
                'claim_status' => $claim->status->value,
            ],
            request: $request,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Zgłoszenie przejęcia profilu trafiło do review admina.',
        ]);
    }
}
