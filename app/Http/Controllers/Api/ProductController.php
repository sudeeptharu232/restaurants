<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
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
     * Display a listing of products.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission('view_products');

        $query = Product::with('category');

        // Search by name or SKU
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
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

        $products = $query->paginate(15);

        return $this->success(
            ProductResource::collection($products)->response()->getData(true),
            'Products retrieved successfully'
        );
    }

    /**
     * Store a newly created product.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $this->authorizePermission('manage_products');

        $product = Product::create($request->validated());

        return $this->success(
            new ProductResource($product->load('category')),
            'Product created successfully',
            201
        );
    }

    /**
     * Display the specified product.
     */
    public function show($tenant, $id): JsonResponse
    {
        $this->authorizePermission('view_products');

        $product = Product::with('category')->findOrFail($id);

        return $this->success(
            new ProductResource($product),
            'Product retrieved successfully'
        );
    }

    /**
     * Update the specified product in storage.
     */
    public function update(UpdateProductRequest $request, $tenant, $id): JsonResponse
    {
        $this->authorizePermission('manage_products');

        $product = Product::findOrFail($id);
        $product->update($request->validated());

        return $this->success(
            new ProductResource($product->load('category')),
            'Product updated successfully'
        );
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy($tenant, $id): JsonResponse
    {
        $this->authorizePermission('manage_products');

        $product = Product::findOrFail($id);
        $product->delete();

        return $this->success(
            null,
            'Product deleted successfully'
        );
    }
}
