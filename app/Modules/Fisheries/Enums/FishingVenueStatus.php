<?php

namespace App\Modules\Fisheries\Enums;

enum FishingVenueStatus: string
{
    case Draft = 'DRAFT';
    case Published = 'PUBLISHED';
    case Archived = 'ARCHIVED';
    case Suspended = 'SUSPENDED';
}
