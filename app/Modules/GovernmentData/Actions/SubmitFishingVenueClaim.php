<?php

namespace App\Modules\GovernmentData\Actions;

use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\GovernmentData\Enums\FishingVenueClaimStatus;
use App\Modules\GovernmentData\Models\FishingVenueClaim;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Validation\ValidationException;

class SubmitFishingVenueClaim
{
    public function handle(FishingVenue $venue, Organization $organization, ?string $evidence): FishingVenueClaim
    {
        if ($venue->organization_id !== null && $venue->organization_id !== $organization->id) {
            throw ValidationException::withMessages([
                'organization_id' => 'To łowisko jest już przypisane do innej organizacji.',
            ]);
        }

        return FishingVenueClaim::query()->updateOrCreate(
            [
                'fishing_venue_id' => $venue->id,
                'organization_id' => $organization->id,
            ],
            [
                'status' => FishingVenueClaimStatus::Pending,
                'evidence' => $evidence,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ],
        );
    }
}
