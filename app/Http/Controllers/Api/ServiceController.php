<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    use ApiResponse;

    /**
     * Helper to enforce permission checks inline.
     */
    protected function authorizePermission(string $permission): void
    {
        $user = auth()->user();
        if (!$user) {
            abort(response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401));
        }

        $permissionsMap = [
            'super_admin' => ['*'],
            'owner' => ['*'],
            'manager' => [
                'view_pos', 'manage_pos',
                'view_inventory', 'manage_inventory',
                'view_customers', 'manage_customers',
                'view_products', 'manage_products',
                'view_menu', 'manage_menu',
                'view_tables', 'manage_tables'
            ],
            'staff' => [
                'view_pos', 'manage_pos',
                'view_customers',
                'view_products',
                'view_menu',
                'view_tables'
            ],
        ];

        $userRole = $user->role ?? 'staff';
        $userPerms = $permissionsMap[$userRole] ?? [];

        if (!in_array('*', $userPerms) && !in_array($permission, $userPerms)) {
            abort(response()->json([
                'success' => false,
                'message' => 'Forbidden: You do not have permission to execute this operation'
            ], 403));
        }
    }

    /**
     * Display a listing of services.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission('view_products');

        $query = Service::with('category');

        // Search by name
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->boolean('show_deleted')) {
            $query->withTrashed();
        }

        $services = $query->paginate(15);

        return $this->success(
            ServiceResource::collection($services)->response()->getData(true),
            'Services retrieved successfully'
        );
    }

    /**
     * Store a newly created service.
     */
    public function store(StoreServiceRequest $request): JsonResponse
    {
        $this->authorizePermission('manage_products');

        $service = Service::create($request->validated());

        return $this->success(
            new ServiceResource($service->load('category')),
            'Service created successfully',
            201
        );
    }

    /**
     * Display the specified service.
     */
    public function show($tenant, $id): JsonResponse
    {
        $this->authorizePermission('view_products');

        $service = Service::with('category')->findOrFail($id);

        return $this->success(
            new ServiceResource($service),
            'Service retrieved successfully'
        );
    }

    /**
     * Update the specified service in storage.
     */
    public function update(UpdateServiceRequest $request, $tenant, $id): JsonResponse
    {
        $this->authorizePermission('manage_products');

        $service = Service::findOrFail($id);
        $service->update($request->validated());

        return $this->success(
            new ServiceResource($service->load('category')),
            'Service updated successfully'
        );
    }

    /**
     * Remove the specified service from storage.
     */
    public function destroy($tenant, $id): JsonResponse
    {
        $this->authorizePermission('manage_products');

        $service = Service::findOrFail($id);
        $service->delete();

        return $this->success(
            null,
            'Service deleted successfully'
        );
    }
}
