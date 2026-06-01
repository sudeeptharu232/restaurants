<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubscriptionPlanRequest;
use App\Http\Requests\UpdateSubscriptionPlanRequest;
use App\Http\Resources\SubscriptionPlanResource;
use App\Services\AdminSubscriptionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminSubscriptionPlanController extends Controller
{
    use ApiResponse;

    protected AdminSubscriptionService $subService;

    public function __construct(AdminSubscriptionService $subService)
    {
        $this->subService = $subService;
    }

    /**
     * List all subscription plans.
     */
    public function index(): JsonResponse
    {
        $plans = $this->subService->listPlans();
        
        return $this->success(SubscriptionPlanResource::collection($plans), 'Plans retrieved successfully');
    }

    /**
     * Create a new subscription plan.
     */
    public function store(StoreSubscriptionPlanRequest $request): JsonResponse
    {
        $plan = $this->subService->createPlan($request->validated());
        
        return $this->success(new SubscriptionPlanResource($plan), 'Subscription plan created successfully', 201);
    }

    /**
     * Show detailed settings of a subscription plan.
     */
    public function show(int $id): JsonResponse
    {
        $plan = \App\Models\SubscriptionPlan::findOrFail($id);
        
        return $this->success(new SubscriptionPlanResource($plan), 'Plan details retrieved successfully');
    }

    /**
     * Update plan limits.
     */
    public function update(int $id, UpdateSubscriptionPlanRequest $request): JsonResponse
    {
        $plan = $this->subService->updatePlan($id, $request->validated());
        
        return $this->success(new SubscriptionPlanResource($plan), 'Plan updated successfully');
    }

    /**
     * Soft-delete/Hard-delete subscription plan safely.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->subService->deletePlan($id);
        
        return $this->success(null, 'Subscription plan deleted successfully');
    }
}
