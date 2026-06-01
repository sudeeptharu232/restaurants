<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['tenant_id', 'user_id', 'event', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'ip_address', 'user_agent'])]
class AuditLog extends Model
{
    /**
     * Disable updated_at column since audit logs are write-once records.
     */
    const UPDATED_AT = null;

    /**
     * Bind strictly to central database connection.
     */
    protected $connection = 'central';

    /**
     * Cast attributes dynamically.
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'json',
            'new_values' => 'json',
        ];
    }

    /**
     * Get the tenant associated with the audit event.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
