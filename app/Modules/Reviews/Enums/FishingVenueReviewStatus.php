<?php

namespace App\Modules\Reviews\Enums;

enum FishingVenueReviewStatus: string
{
    case Published = 'PUBLISHED';
    case Hidden = 'HIDDEN';
}
