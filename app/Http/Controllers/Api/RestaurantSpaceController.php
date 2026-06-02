<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRestaurantSpaceRequest;
use App\Http\Requests\UpdateRestaurantSpaceRequest;
use App\Http\Resources\RestaurantSpaceResource;
use App\Models\RestaurantSpace;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
class RestaurantSpaceController extends Controller
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
                'view_pos',
                'manage_pos',
                'view_inventory',
                'manage_inventory',
                'view_customers',
                'manage_customers',
                'view_products',
                'manage_products',
                'view_menu',
                'manage_menu',
                'view_tables',
                'manage_tables'
            ],
            'staff' => [
                'view_pos',
                'manage_pos',
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
     * Display a listing of restaurant spaces.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission('view_tables');

        $cacheKey = 'restaurant_spaces_index_' . md5(json_encode([
            'tenant_id' => tenant('id'),
            'search' => $request->input('search'),
            'is_active' => $request->input('is_active'),
            'page' => $request->input('page', 1),
        ]));

        $data = Cache::remember($cacheKey, 60, function () use ($request) {

            $query = RestaurantSpace::query()->withCount('tables');

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where('name', 'like', "%{$search}%");
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $spaces = $query->paginate(15);


            return RestaurantSpaceResource::collection($spaces)
                ->response()
                ->getData(true);
        });

        return $this->success($data, 'Restaurant spaces retrieved successfully');
    }

    /**
     * Store a newly created restaurant space.
     */
    public function store(StoreRestaurantSpaceRequest $request): JsonResponse
    {
        $this->authorizePermission('manage_tables');

        $space = RestaurantSpace::create($request->validated());
        $space->loadCount('tables');

        return $this->success(
            new RestaurantSpaceResource($space),
            'Restaurant space created successfully',
            201
        );
    }

    /**
     * Display the specified restaurant space.
     */
    public function show($tenant, $id): JsonResponse
    {
        $this->authorizePermission('view_tables');

        $space = RestaurantSpace::withCount('tables')->findOrFail($id);

        return $this->success(
            new RestaurantSpaceResource($space),
            'Restaurant space retrieved successfully'
        );
    }

    /**
     * Update the specified restaurant space in storage.
     */
    public function update(UpdateRestaurantSpaceRequest $request, $tenant, $id): JsonResponse
    {
        $this->authorizePermission('manage_tables');

        $space = RestaurantSpace::findOrFail($id);
        $space->update($request->validated());
        $space->loadCount('tables');

        return $this->success(
            new RestaurantSpaceResource($space),
            'Restaurant space updated successfully'
        );
    }

    /**
     * Remove the specified restaurant space from storage.
     */
    public function destroy($tenant, $id): JsonResponse
    {
        $this->authorizePermission('manage_tables');

        $space = RestaurantSpace::findOrFail($id);
        $space->delete();

        return $this->success(
            null,
            'Restaurant space deleted successfully'
        );
    }
}
