<?php

namespace App\Modules\Organizations\Enums;

enum OrganizationMemberRole: string
{
    case Owner = 'OWNER';
    case Manager = 'MANAGER';
    case Staff = 'STAFF';
}
