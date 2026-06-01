<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStaffRequest;
use App\Http\Requests\UpdateStaffRequest;
use App\Http\Requests\UpdateStaffPermissionsRequest;
use App\Http\Requests\UpdateStaffRoleRequest;
use App\Http\Resources\StaffResource;
use App\Models\User;
use App\Services\StaffService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    use ApiResponse;

    protected StaffService $staffService;

    public function __construct(StaffService $staffService)
    {
        $this->staffService = $staffService;
    }

    /**
     * Enforce role/permission authorization inline.
     */
    protected function authorizePermission(string $permission): void
    {
        $user = auth()->user();
        if (!$user) {
            abort(response()->json(['success' => false, 'message' => 'Unauthenticated'], 401));
        }

        if (!$user->is_active) {
            abort(response()->json(['success' => false, 'message' => 'Forbidden: Account suspended or inactive'], 403));
        }

        // Standard RBAC check utilizing our standard hasPermission helper
        if (!$user->hasPermission($permission)) {
            abort(response()->json([
                'success' => false,
                'message' => 'Forbidden: You do not have permission to execute this operation',
            ], 403));
        }
    }

    /**
     * GET /api/{tenant}/staff
     */
    public function index($tenant, Request $request): JsonResponse
    {
        $this->authorizePermission('manage_staff');

        $staff = User::query()
            ->select(['id', 'name', 'email', 'phone', 'role', 'permissions', 'is_active', 'created_at', 'updated_at'])
            ->orderBy('name', 'asc')
            ->paginate($request->get('per_page', 30));

        return $this->success(
            StaffResource::collection($staff)->response()->getData(true),
            'Staff directory retrieved successfully'
        );
    }

    /**
     * POST /api/{tenant}/staff
     */
    public function store($tenant, StoreStaffRequest $request): JsonResponse
    {
        $this->authorizePermission('manage_staff');

        $user = $this->staffService->createStaff($request->validated(), auth()->user());

        return $this->success(
            new StaffResource($user),
            'Staff member created successfully',
            201
        );
    }

    /**
     * GET /api/{tenant}/staff/{id}
     */
    public function show($tenant, $id): JsonResponse
    {
        $this->authorizePermission('manage_staff');

        $staff = User::findOrFail($id);

        return $this->success(
            new StaffResource($staff),
            'Staff details retrieved successfully'
        );
    }

    /**
     * PUT /api/{tenant}/staff/{id}
     */
    public function update($tenant, $id, UpdateStaffRequest $request): JsonResponse
    {
        $this->authorizePermission('manage_staff');

        $staff = User::findOrFail($id);
        $updated = $this->staffService->updateStaff($staff, $request->validated(), auth()->user());

        return $this->success(
            new StaffResource($updated),
            'Staff profile updated successfully'
        );
    }

    /**
     * DELETE /api/{tenant}/staff/{id}
     */
    public function destroy($tenant, $id): JsonResponse
    {
        $this->authorizePermission('manage_staff');

        $staff = User::findOrFail($id);

        if ($staff->role === 'owner') {
            return $this->error('Forbidden: Owner users cannot be deleted', 403);
        }

        if ($staff->id === auth()->id()) {
            return $this->error('Conflict: You cannot delete yourself', 409);
        }

        $staff->delete();

        return $this->success(null, 'Staff user deleted successfully');
    }

    /**
     * PUT /api/{tenant}/staff/{id}/activate
     */
    public function activate($tenant, $id): JsonResponse
    {
        $this->authorizePermission('manage_staff');

        $staff = User::findOrFail($id);
        $updated = $this->staffService->reactivateStaff($staff, auth()->user());

        return $this->success(
            new StaffResource($updated),
            'Staff user reactivated successfully'
        );
    }

    /**
     * PUT /api/{tenant}/staff/{id}/deactivate
     */
    public function deactivate($tenant, $id): JsonResponse
    {
        $this->authorizePermission('manage_staff');

        $staff = User::findOrFail($id);
        $updated = $this->staffService->deactivateStaff($staff, auth()->user());

        return $this->success(
            new StaffResource($updated),
            'Staff user deactivated successfully'
        );
    }

    /**
     * PUT /api/{tenant}/staff/{id}/permissions
     */
    public function updatePermissions($tenant, $id, UpdateStaffPermissionsRequest $request): JsonResponse
    {
        $this->authorizePermission('manage_staff');

        $staff = User::findOrFail($id);
        $updated = $this->staffService->updatePermissions($staff, $request->input('permissions'), auth()->user());

        return $this->success(
            new StaffResource($updated),
            'Staff permissions updated successfully'
        );
    }

    /**
     * PUT /api/{tenant}/staff/{id}/role
     */
    public function updateRole($tenant, $id, UpdateStaffRoleRequest $request): JsonResponse
    {
        $this->authorizePermission('manage_staff');

        $staff = User::findOrFail($id);
        $updated = $this->staffService->updateRole($staff, $request->input('role'), auth()->user());

        return $this->success(
            new StaffResource($updated),
            'Staff role updated successfully'
        );
    }
}
