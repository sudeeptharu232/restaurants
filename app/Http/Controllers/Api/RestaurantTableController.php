<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRestaurantTableRequest;
use App\Http\Requests\UpdateRestaurantTableRequest;
use App\Http\Resources\RestaurantTableResource;
use App\Models\RestaurantTable;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RestaurantTableController extends Controller
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
     * Display a listing of restaurant tables.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission('view_tables');

        $query = RestaurantTable::with(['space' => function ($q) {
            $q->withCount('tables');
        }]);

        // Search by table number
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('table_number', 'like', "%{$search}%");
        }

        // Filter by space
        if ($request->has('restaurant_space_id')) {
            $query->where('restaurant_space_id', $request->input('restaurant_space_id'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $tables = $query->paginate(15);

        return $this->success(
            RestaurantTableResource::collection($tables)->response()->getData(true),
            'Restaurant tables retrieved successfully'
        );
    }

    /**
     * Store a newly created restaurant table.
     */
    public function store(StoreRestaurantTableRequest $request): JsonResponse
    {
        $this->authorizePermission('manage_tables');

        $table = RestaurantTable::create($request->validated());

        return $this->success(
            new RestaurantTableResource($table->load(['space' => function ($q) {
                $q->withCount('tables');
            }])),
            'Restaurant table created successfully',
            201
        );
    }

    /**
     * Display the specified restaurant table.
     */
    public function show($tenant, $id): JsonResponse
    {
        $this->authorizePermission('view_tables');

        $table = RestaurantTable::with(['space' => function ($q) {
            $q->withCount('tables');
        }])->findOrFail($id);

        return $this->success(
            new RestaurantTableResource($table),
            'Restaurant table retrieved successfully'
        );
    }

    /**
     * Update the specified restaurant table in storage.
     */
    public function update(UpdateRestaurantTableRequest $request, $tenant, $id): JsonResponse
    {
        $this->authorizePermission('manage_tables');

        $table = RestaurantTable::findOrFail($id);
        $table->update($request->validated());

        return $this->success(
            new RestaurantTableResource($table->load(['space' => function ($q) {
                $q->withCount('tables');
            }])),
            'Restaurant table updated successfully'
        );
    }

    /**
     * Remove the specified restaurant table from storage.
     */
    public function destroy($tenant, $id): JsonResponse
    {
        $this->authorizePermission('manage_tables');

        $table = RestaurantTable::findOrFail($id);
        $table->delete();

        return $this->success(
            null,
            'Restaurant table deleted successfully'
        );
    }
}
