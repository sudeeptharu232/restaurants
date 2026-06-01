<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTenantRequest;
use App\Http\Requests\UpdateTenantRequest;
use App\Http\Resources\AdminTenantResource;
use App\Http\Resources\AdminTenantDetailResource;
use App\Services\AdminTenantService;
use App\Services\BusinessRegistrationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTenantController extends Controller
{
    use ApiResponse;

    protected AdminTenantService $tenantService;

    public function __construct(AdminTenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    /**
     * List tenants with filters/search.
     */
    public function index(Request $request): JsonResponse
    {
        $tenants = $this->tenantService->listTenants($request->all());
        
        return $this->success(
            AdminTenantResource::collection($tenants)->response()->getData(true),
            'Tenants retrieved successfully'
        );
    }

    /**
     * Store a new tenant via BusinessRegistrationService.
     */
    public function store(StoreTenantRequest $request, BusinessRegistrationService $registrationService): JsonResponse
    {
        $result = $registrationService->register($request->validated());
        
        return $this->success([
            'tenant' => new AdminTenantDetailResource($result['tenant']),
            'owner' => $result['user']
        ], 'Tenant created successfully', 201);
    }

    /**
     * Get details of a single tenant.
     */
    public function show(string $id): JsonResponse
    {
        $tenant = $this->tenantService->getTenantDetails($id);
        
        return $this->success(new AdminTenantDetailResource($tenant), 'Tenant details retrieved successfully');
    }

    /**
     * Update tenant metadata.
     */
    public function update(string $id, UpdateTenantRequest $request): JsonResponse
    {
        $tenant = $this->tenantService->getTenantDetails($id);
        
        if ($request->has('name')) {
            $tenant->name = $request->input('name');
        }
        
        // Dynamic metadata fields
        foreach (['status', 'owner_email', 'phone'] as $field) {
            if ($request->has($field)) {
                $tenant->$field = $request->input($field);
            }
        }
        
        $tenant->save();
        
        return $this->success(new AdminTenantDetailResource($tenant), 'Tenant updated successfully');
    }

    /**
     * Activate tenant.
     */
    public function activate(string $id): JsonResponse
    {
        $tenant = $this->tenantService->updateStatus($id, 'active');
        
        return $this->success(new AdminTenantResource($tenant), 'Tenant activated successfully');
    }

    /**
     * Deactivate tenant.
     */
    public function deactivate(string $id): JsonResponse
    {
        $tenant = $this->tenantService->updateStatus($id, 'inactive');
        
        return $this->success(new AdminTenantResource($tenant), 'Tenant deactivated successfully');
    }

    /**
     * Suspend tenant.
     */
    public function suspend(string $id): JsonResponse
    {
        $tenant = $this->tenantService->updateStatus($id, 'suspended');
        
        return $this->success(new AdminTenantResource($tenant), 'Tenant suspended successfully');
    }

    /**
     * Restore suspended tenant.
     */
    public function restore(string $id): JsonResponse
    {
        $tenant = $this->tenantService->updateStatus($id, 'active');
        
        return $this->success(new AdminTenantResource($tenant), 'Tenant restored successfully');
    }

    /**
     * Fetch a read-only database metric summary from isolated tenant DB.
     */
    public function summary(string $id): JsonResponse
    {
        $summary = $this->tenantService->getTenantSummary($id);
        
        return $this->success($summary, 'Tenant database summary retrieved successfully');
    }

    /**
     * Delete/Soft-delete tenant.
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        $force = filter_var($request->query('force', false), FILTER_VALIDATE_BOOLEAN);
        
        $this->tenantService->deleteTenant($id, $force);
        
        $msg = $force ? 'Tenant database permanently deleted' : 'Tenant marked inactive';
        
        return $this->success(null, $msg);
    }
}
