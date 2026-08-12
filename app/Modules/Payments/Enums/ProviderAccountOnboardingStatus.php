<?php

namespace App\Modules\Payments\Enums;

enum ProviderAccountOnboardingStatus: string
{
    case Pending = 'pending';
    case Enabled = 'enabled';
    case Restricted = 'restricted';
}
