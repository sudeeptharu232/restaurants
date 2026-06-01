<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    /**
     * Record a central audit log entry.
     */
    public function log(
        string $event,
        ?string $tenantId = null,
        ?int $userId = null,
        ?string $auditableType = null,
        ?int $auditableId = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): AuditLog {
        // Resolve dynamic user from request if not supplied
        if (!$userId && auth()->check()) {
            $userId = auth()->id();
        }

        return AuditLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'event' => $event,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
