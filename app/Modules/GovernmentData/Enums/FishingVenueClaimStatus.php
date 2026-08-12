<?php

namespace App\Modules\GovernmentData\Enums;

enum FishingVenueClaimStatus: string
{
    case Pending = 'PENDING';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
}
