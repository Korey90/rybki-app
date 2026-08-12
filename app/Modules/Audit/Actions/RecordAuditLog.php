<?php

namespace App\Modules\Audit\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class RecordAuditLog
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function handle(
        string $action,
        ?Model $subject = null,
        ?User $actor = null,
        ?Organization $organization = null,
        ?array $before = null,
        ?array $after = null,
        ?Request $request = null
    ): AuditLog {
        return AuditLog::create([
            'actor_user_id' => $actor?->id,
            'organization_id' => $organization?->id,
            'action' => $action,
            'subject_type' => $subject === null ? null : $subject::class,
            'subject_id' => $subject === null ? null : (int) $subject->getKey(),
            'before' => $before,
            'after' => $after,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
