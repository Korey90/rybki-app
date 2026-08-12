<?php

namespace App\Modules\GovernmentData\Actions;

use App\Models\User;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\GovernmentData\Enums\FishingVenueClaimStatus;
use App\Modules\GovernmentData\Models\FishingVenueClaim;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReviewFishingVenueClaim
{
    public function handle(FishingVenueClaim $claim, User $reviewer, FishingVenueClaimStatus $status): FishingVenueClaim
    {
        if (! in_array($status, [FishingVenueClaimStatus::Approved, FishingVenueClaimStatus::Rejected], true)) {
            throw new InvalidArgumentException('Claim review accepts only approved or rejected status.');
        }

        return DB::transaction(function () use ($claim, $reviewer, $status): FishingVenueClaim {
            $claim = FishingVenueClaim::query()
                ->whereKey($claim->id)
                ->lockForUpdate()
                ->firstOrFail();

            $claim->fill([
                'status' => $status,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ])->save();

            if ($status === FishingVenueClaimStatus::Approved) {
                $venue = FishingVenue::query()
                    ->whereKey($claim->fishing_venue_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $venue->fill(['organization_id' => $claim->organization_id])->save();

                FishingVenueClaim::query()
                    ->where('fishing_venue_id', $venue->id)
                    ->whereKeyNot($claim->id)
                    ->where('status', FishingVenueClaimStatus::Pending->value)
                    ->update([
                        'status' => FishingVenueClaimStatus::Rejected->value,
                        'reviewed_by' => $reviewer->id,
                        'reviewed_at' => now(),
                        'updated_at' => now(),
                    ]);
            }

            return $claim->refresh();
        });
    }
}
