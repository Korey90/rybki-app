<?php

namespace App\Modules\GovernmentData\Enums;

enum GovernmentVenueRecordStatus: string
{
    case Pending = 'PENDING';
    case NeedsReview = 'NEEDS_REVIEW';
    case Imported = 'IMPORTED';
    case Matched = 'MATCHED';
    case Skipped = 'SKIPPED';
}
