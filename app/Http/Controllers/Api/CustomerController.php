<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
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
     * Display a listing of customers.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission('view_customers');

        $query = Customer::query()
            ->select(['id', 'name', 'phone', 'email', 'address', 'points', 'created_at', 'updated_at'])
            ->withSum([
                'orders as total_spent' => fn ($query) => $query->where('status', 'completed'),
            ], 'total')
            ->withSum([
                'orders as due_amount' => fn ($query) => $query->where('status', '!=', 'completed'),
            ], 'total');

        // Search by name, phone, email
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter active/deleted if applicable
        if ($request->boolean('show_deleted')) {
            $query->withTrashed();
        }

        $customers = $query->paginate(15);

        return $this->success(
            CustomerResource::collection($customers)->response()->getData(true),
            'Customers retrieved successfully'
        );
    }

    /**
     * Store a newly created customer.
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $this->authorizePermission('manage_customers');

        $customer = Customer::create($request->validated());

        return $this->success(
            new CustomerResource($customer),
            'Customer created successfully',
            201
        );
    }

    /**
     * Display the specified customer.
     */
    public function show($tenant, $id): JsonResponse
    {
        $this->authorizePermission('view_customers');

        $customer = Customer::findOrFail($id);

        return $this->success(
            new CustomerResource($customer),
            'Customer retrieved successfully'
        );
    }

    /**
     * Update the specified customer in storage.
     */
    public function update(UpdateCustomerRequest $request, $tenant, $id): JsonResponse
    {
        $this->authorizePermission('manage_customers');

        $customer = Customer::findOrFail($id);
        $customer->update($request->validated());

        return $this->success(
            new CustomerResource($customer),
            'Customer updated successfully'
        );
    }

    /**
     * Remove the specified customer from storage.
     */
    public function destroy($tenant, $id): JsonResponse
    {
        $this->authorizePermission('manage_customers');

        $customer = Customer::findOrFail($id);
        $customer->delete();

        return $this->success(
            null,
            'Customer deleted successfully'
        );
    }
}
