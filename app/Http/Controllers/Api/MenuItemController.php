<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMenuItemRequest;
use App\Http\Requests\UpdateMenuItemRequest;
use App\Http\Resources\MenuItemResource;
use App\Models\MenuItem;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MenuItemController extends Controller
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
     * Display a listing of menu items.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission('view_menu');
        $cacheKey = 'menu_items_index_' . md5(json_encode([
            'tenant_id' => tenant('id'),
            'search' => $request->input('search'),
            'category_id' => $request->input('category_id'),
            'is_available' => $request->input('is_available'),
            'show_deleted' => $request->boolean('show_deleted'),
            'page' => $request->input('page', 1),
        ]));
        $data = Cache::remember($cacheKey, 60, function () use ($request) {

            $query = MenuItem::with('category');

            // Search by name
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where('name', 'like', "%{$search}%");
            }

            // Filter by category
            if ($request->has('category_id')) {
                $query->where('category_id', $request->input('category_id'));
            }

            // Filter by availability
            if ($request->has('is_available')) {
                $query->where('is_available', $request->boolean('is_available'));
            }

            if ($request->boolean('show_deleted')) {
                $query->withTrashed();
            }

            $menuItems = $query->simplePaginate(1);

            return $this->success(
                MenuItemResource::collection($menuItems)->response()->getData(true),
                'Menu items retrieved successfully'
            );
        });
        return $this->success($data, 'Menu items retrieved successfully');
    }

    /**
     * Store a newly created menu item.
     */
    public function store(StoreMenuItemRequest $request): JsonResponse
    {
        $this->authorizePermission('manage_menu');

        $menuItem = MenuItem::create($request->validated());

        return $this->success(
            new MenuItemResource($menuItem->load('category')),
            'Menu item created successfully',
            201
        );
    }

    /**
     * Display the specified menu item.
     */
    public function show($tenant, $id): JsonResponse
    {
        $this->authorizePermission('view_menu');

        $menuItem = MenuItem::with('category')->findOrFail($id);

        return $this->success(
            new MenuItemResource($menuItem),
            'Menu item retrieved successfully'
        );
    }

    /**
     * Update the specified menu item in storage.
     */
    public function update(UpdateMenuItemRequest $request, $tenant, $id): JsonResponse
    {
        $this->authorizePermission('manage_menu');

        $menuItem = MenuItem::findOrFail($id);
        $menuItem->update($request->validated());

        return $this->success(
            new MenuItemResource($menuItem->load('category')),
            'Menu item updated successfully'
        );
    }

    /**
     * Remove the specified menu item from storage.
     */
    public function destroy($tenant, $id): JsonResponse
    {
        $this->authorizePermission('manage_menu');

        $menuItem = MenuItem::findOrFail($id);
        $menuItem->delete();

        return $this->success(
            null,
            'Menu item deleted successfully'
        );
    }
}
