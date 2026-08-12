<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $user->is_admin;
    }
}
