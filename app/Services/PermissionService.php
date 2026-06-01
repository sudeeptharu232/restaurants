<?php

namespace App\Services;

use App\Models\User;

class PermissionService
{
    /**
     * Supported permissions inside the application.
     */
    protected array $supportedPermissions = [
        'manage_customers',
        'manage_products',
        'manage_menu',
        'manage_tables',
        'manage_orders',
        'manage_kot',
        'manage_invoices',
        'manage_payments',
        'manage_inventory',
        'manage_expenses',
        'view_analytics',
        'manage_settings',
        'manage_staff',
        'manage_reports',
    ];

    /**
     * Get all supported permission names.
     */
    public function getPermissions(): array
    {
        return $this->supportedPermissions;
    }

    /**
     * Check if a permission name is valid.
     */
    public function isValidPermission(string $permission): bool
    {
        return in_array($permission, $this->supportedPermissions);
    }

    /**
     * Return default permissions based on user role.
     */
    public function getDefaultPermissions(string $role): array
    {
        switch ($role) {
            case 'super_admin':
            case 'owner':
                return $this->supportedPermissions;

            case 'manager':
                return [
                    'manage_customers',
                    'manage_products',
                    'manage_menu',
                    'manage_tables',
                    'manage_orders',
                    'manage_kot',
                    'manage_invoices',
                    'manage_payments',
                    'view_analytics',
                    'manage_reports',
                ];

            case 'staff':
                return [
                    'manage_customers',
                    'manage_orders',
                    'manage_kot',
                ];

            default:
                return [];
        }
    }

    /**
     * Resolve effective permissions for a user.
     */
    public function getEffectivePermissions(User $user): array
    {
        if ($user->role === 'super_admin' || $user->role === 'owner') {
            return $this->supportedPermissions;
        }

        // If custom permissions are configured, they completely override the defaults
        if (is_array($user->permissions)) {
            return array_values(array_filter($user->permissions, [$this, 'isValidPermission']));
        }

        return $this->getDefaultPermissions($user->role);
    }

    /**
     * Check if a user has a specific permission.
     */
    public function hasPermission(User $user, string $permission): bool
    {
        if ($user->role === 'super_admin' || $user->role === 'owner') {
            return true;
        }

        if (!$user->is_active) {
            return false;
        }

        $effective = $this->getEffectivePermissions($user);
        return in_array($permission, $effective);
    }

    /**
     * Validate if an actor can grant a list of requested permissions.
     */
    public function canGrantPermissions(User $actor, array $requestedPermissions): bool
    {
        if ($actor->role === 'super_admin' || $actor->role === 'owner') {
            return true;
        }

        // Only owner/super_admin can grant manage_settings or manage_staff
        $ownerOnly = ['manage_settings', 'manage_staff'];
        foreach ($requestedPermissions as $p) {
            if (in_array($p, $ownerOnly)) {
                return false;
            }
        }

        // Actors cannot grant permissions they do not have
        $actorPerms = $this->getEffectivePermissions($actor);
        foreach ($requestedPermissions as $p) {
            if (!in_array($p, $actorPerms)) {
                return false;
            }
        }

        return true;
    }
}
