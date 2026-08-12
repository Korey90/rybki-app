<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Models\AuditLog;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    /**
     * Show latest audit events.
     */
    public function index(): Response
    {
        Gate::authorize('viewAny', AuditLog::class);

        $logs = AuditLog::query()
            ->with(['actor', 'organization'])
            ->latest('created_at')
            ->limit(100)
            ->get();

        return Inertia::render('admin/audit-logs/index', [
            'logs' => $logs->map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'subject_type' => $log->subject_type,
                'subject_id' => $log->subject_id,
                'before' => $log->before,
                'after' => $log->after,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at?->toIso8601String(),
                'actor' => $log->actor === null ? null : [
                    'name' => $log->actor->name,
                    'email' => $log->actor->email,
                ],
                'organization' => $log->organization === null ? null : [
                    'name' => $log->organization->name,
                ],
            ])->values()->all(),
        ]);
    }
}
