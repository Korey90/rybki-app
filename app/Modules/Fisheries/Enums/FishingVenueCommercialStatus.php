<?php

namespace App\Modules\Fisheries\Enums;

enum FishingVenueCommercialStatus: string
{
    case InformationOnly = 'INFORMATION_ONLY';
    case Claimable = 'CLAIMABLE';
    case Bookable = 'BOOKABLE';
}
