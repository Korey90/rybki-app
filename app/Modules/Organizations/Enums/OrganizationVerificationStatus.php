<?php

namespace App\Modules\Organizations\Enums;

enum OrganizationVerificationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
}
