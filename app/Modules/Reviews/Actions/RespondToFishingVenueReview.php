<?php

namespace App\Modules\Reviews\Actions;

use App\Models\User;
use App\Modules\Reviews\Models\FishingVenueReview;

class RespondToFishingVenueReview
{
    public function handle(FishingVenueReview $review, User $operator, string $response): FishingVenueReview
    {
        $review->forceFill([
            'operator_response' => $response,
            'operator_responded_by_user_id' => $operator->id,
            'operator_responded_at' => now(),
        ])->save();

        return $review;
    }
}
