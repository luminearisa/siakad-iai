<?php

namespace Modules\Audit\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Modules\Audit\Models\AuditLog;
use Modules\Identity\Models\User;

class AuditService
{
    /**
     * Record an audit log entry.
     */
    public static function log(
        string $action,
        string $module,
        ?string $description = null,
        ?Model $entity = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?User $user = null
    ): AuditLog {
        $userId = $user?->id ?? Auth::id();
        $ip = Request::ip();
        $userAgent = Request::userAgent();

        return AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'module' => $module,
            'entity_type' => $entity ? get_class($entity) : null,
            'entity_id' => $entity?->getKey(),
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $ip,
            'user_agent' => $userAgent ? substr($userAgent, 0, 500) : null,
            'created_at' => now(),
        ]);
    }
}
