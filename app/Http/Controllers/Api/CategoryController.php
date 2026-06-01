<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
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
     * Display a listing of categories.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Category::query();

        // Type: product, service, menu, expense
        if ($request->has('type')) {
            $type = $request->input('type');
            $query->where('type', $type);

            // Dynamic view permission check
            if ($type === 'menu') {
                $this->authorizePermission('view_menu');
            } else {
                $this->authorizePermission('view_products');
            }
        } else {
            // General index check view_products by default
            $this->authorizePermission('view_products');
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->boolean('show_deleted')) {
            $query->withTrashed();
        }

        $categories = $query->get();

        return $this->success(
            CategoryResource::collection($categories),
            'Categories retrieved successfully'
        );
    }

    /**
     * Store a newly created category.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $type = $request->input('type');
        $permission = ($type === 'menu') ? 'manage_menu' : 'manage_products';
        $this->authorizePermission($permission);

        $category = Category::create($request->validated());

        return $this->success(
            new CategoryResource($category),
            'Category created successfully',
            201
        );
    }

    /**
     * Display the specified category.
     */
    public function show($tenant, $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        $permission = ($category->type === 'menu') ? 'view_menu' : 'view_products';
        $this->authorizePermission($permission);

        return $this->success(
            new CategoryResource($category),
            'Category retrieved successfully'
        );
    }

    /**
     * Update the specified category in storage.
     */
    public function update(UpdateCategoryRequest $request, $tenant, $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        $type = $request->input('type', $category->type);
        $permission = ($type === 'menu') ? 'manage_menu' : 'manage_products';
        $this->authorizePermission($permission);

        $category->update($request->validated());

        return $this->success(
            new CategoryResource($category),
            'Category updated successfully'
        );
    }

    /**
     * Remove the specified category from storage.
     */
    public function destroy($tenant, $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        $permission = ($category->type === 'menu') ? 'manage_menu' : 'manage_products';
        $this->authorizePermission($permission);

        $category->delete();

        return $this->success(
            null,
            'Category deleted successfully'
        );
    }
}
