<?php

namespace App\Modules\Fisheries\Enums;

enum FishingVenueType: string
{
    case Private = 'PRIVATE';
    case Pzw = 'PZW';
    case WodyPolskie = 'WODY_POLSKIE';
    case OtherPublic = 'OTHER_PUBLIC';
    case Other = 'OTHER';

    public function isPublicWater(): bool
    {
        return in_array($this, [self::Pzw, self::WodyPolskie, self::OtherPublic], true);
    }
}
