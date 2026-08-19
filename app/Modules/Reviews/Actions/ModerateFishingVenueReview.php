<?php

namespace App\Modules\Reviews\Actions;

use App\Models\User;
use App\Modules\Reviews\Enums\FishingVenueReviewStatus;
use App\Modules\Reviews\Models\FishingVenueReview;

class ModerateFishingVenueReview
{
    public function handle(
        FishingVenueReview $review,
        User $moderator,
        FishingVenueReviewStatus $status,
        ?string $hiddenReason = null,
    ): FishingVenueReview {
        $review->forceFill([
            'status' => $status,
            'hidden_reason' => $status === FishingVenueReviewStatus::Hidden ? $hiddenReason : null,
            'moderated_by_user_id' => $moderator->id,
            'moderated_at' => now(),
        ])->save();

        return $review;
    }
}
