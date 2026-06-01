<?php

namespace App\Services;

use App\Models\Tenant;

class TenantService
{
    /**
     * Find a tenant by ID/slug.
     */
    public function findById(string $id): ?Tenant
    {
        return Tenant::find($id);
    }

    /**
     * Validate whether a tenant is active.
     */
    public function isActive(Tenant $tenant): bool
    {
        if (isset($tenant->status) && $tenant->status === 'suspended') {
            return false;
        }

        return $tenant->is_active !== false;
    }
}
