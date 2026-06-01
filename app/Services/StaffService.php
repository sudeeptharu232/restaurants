<?php

namespace App\Services;

use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StaffService
{
    protected PermissionService $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    /**
     * Create a new staff user directly.
     */
    public function createStaff(array $data, User $actor): User
    {
        $role = $data['role'] ?? 'staff';

        // Non-owners cannot create owners
        if ($role === 'owner' && $actor->role !== 'owner' && $actor->role !== 'super_admin') {
            abort(response()->json([
                'success' => false,
                'message' => 'Forbidden: You do not have permission to create an owner user',
            ], 403));
        }

        // Validate custom permissions being granted
        if (isset($data['permissions']) && is_array($data['permissions'])) {
            if (!$this->permissionService->canGrantPermissions($actor, $data['permissions'])) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Forbidden: You cannot grant permissions you do not possess',
                ], 403));
            }
        }

        return DB::transaction(function () use ($data, $role) {
            return User::create([
                'name'         => $data['name'],
                'email'        => $data['email'],
                'password'     => Hash::make($data['password']),
                'role'         => $role,
                'phone'        => $data['phone'] ?? null,
                'permissions'  => $data['permissions'] ?? null,
                'is_active'    => $data['is_active'] ?? true,
            ]);
        });
    }

    /**
     * Update an existing staff user's profile info.
     */
    public function updateStaff(User $staff, array $data, User $actor): User
    {
        // Prevent staff/managers from modifying an owner user
        if ($staff->role === 'owner' && $actor->role !== 'owner' && $actor->role !== 'super_admin') {
            abort(response()->json([
                'success' => false,
                'message' => 'Forbidden: You do not have permission to modify an owner',
            ], 403));
        }

        // Handle role escalation checks if role changes
        if (isset($data['role']) && $data['role'] !== $staff->role) {
            $this->validateRoleEscalation($staff, $data['role'], $actor);
        }

        // Handle permission updates checks if custom permissions are provided
        if (isset($data['permissions']) && is_array($data['permissions'])) {
            if (!$this->permissionService->canGrantPermissions($actor, $data['permissions'])) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Forbidden: You cannot grant permissions you do not possess',
                ], 403));
            }
        }

        return DB::transaction(function () use ($staff, $data) {
            $fill = array_filter($data, function ($key) {
                return in_array($key, ['name', 'phone', 'email', 'role', 'permissions', 'is_active']);
            }, ARRAY_FILTER_USE_KEY);

            if (isset($data['password']) && !empty($data['password'])) {
                $fill['password'] = Hash::make($data['password']);
            }

            $staff->update($fill);
            return $staff;
        });
    }

    /**
     * Deactivate a staff member.
     */
    public function deactivateStaff(User $staff, User $actor): User
    {
        if ($staff->id === $actor->id) {
            abort(response()->json([
                'success' => false,
                'message' => 'Conflict: You cannot deactivate yourself',
            ], 409));
        }

        if ($staff->role === 'owner') {
            abort(response()->json([
                'success' => false,
                'message' => 'Forbidden: Owner users cannot be deactivated',
            ], 403));
        }

        if ($staff->role === 'owner' && $actor->role !== 'owner' && $actor->role !== 'super_admin') {
            abort(response()->json([
                'success' => false,
                'message' => 'Forbidden: You do not have permission to deactivate this user',
            ], 403));
        }

        $staff->update(['is_active' => false]);
        return $staff;
    }

    /**
     * Reactivate a staff member.
     */
    public function reactivateStaff(User $staff, User $actor): User
    {
        if ($staff->role === 'owner' && $actor->role !== 'owner' && $actor->role !== 'super_admin') {
            abort(response()->json([
                'success' => false,
                'message' => 'Forbidden: You do not have permission to reactivate this user',
            ], 403));
        }

        $staff->update(['is_active' => true]);
        return $staff;
    }

    /**
     * Update permissions of a staff user.
     */
    public function updatePermissions(User $staff, array $permissions, User $actor): User
    {
        if ($staff->role === 'owner') {
            abort(response()->json([
                'success' => false,
                'message' => 'Forbidden: Owner permissions are non-restrictable and cannot be modified',
            ], 403));
        }

        if (!$this->permissionService->canGrantPermissions($actor, $permissions)) {
            abort(response()->json([
                'success' => false,
                'message' => 'Forbidden: You cannot grant permissions you do not possess',
            ], 403));
        }

        $staff->update(['permissions' => $permissions]);
        return $staff;
    }

    /**
     * Update role of a staff user.
     */
    public function updateRole(User $staff, string $role, User $actor): User
    {
        $this->validateRoleEscalation($staff, $role, $actor);

        $staff->update(['role' => $role]);
        return $staff;
    }

    /**
     * Helper to validate role escalation rules.
     */
    protected function validateRoleEscalation(User $staff, string $newRole, User $actor): void
    {
        if ($staff->id === $actor->id) {
            abort(response()->json([
                'success' => false,
                'message' => 'Conflict: You cannot change your own role',
            ], 409));
        }

        // Only owners/super_admins can promote someone to owner
        if ($newRole === 'owner' && $actor->role !== 'owner' && $actor->role !== 'super_admin') {
            abort(response()->json([
                'success' => false,
                'message' => 'Forbidden: You do not have permission to promote a user to owner',
            ], 403));
        }

        // Only owners/super_admins can demote an owner
        if ($staff->role === 'owner' && $actor->role !== 'owner' && $actor->role !== 'super_admin') {
            abort(response()->json([
                'success' => false,
                'message' => 'Forbidden: You do not have permission to demote an owner user',
            ], 403));
        }
    }
}
