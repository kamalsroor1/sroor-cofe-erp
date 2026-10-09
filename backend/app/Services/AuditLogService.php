<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    public function log(
        string $action,
        Model $auditable,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $actorId = null,
    ): AuditLog {
        return AuditLog::create([
            // Authenticated user, else the explicit actor (job/command), else NULL ("system").
            // Never a made-up id: crediting user 1 would forge the audit trail.
            'user_id' => Auth::id() ?? $actorId,
            'action_type' => $action,
            'auditable_type' => get_class($auditable),
            'auditable_id' => $auditable->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip() ?? '127.0.0.1',
            'user_agent' => Request::userAgent() ?? 'System / CLI',
        ]);
    }
}
