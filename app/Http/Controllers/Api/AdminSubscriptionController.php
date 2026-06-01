<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignSubscriptionRequest;
use App\Http\Requests\UpdateSubscriptionRequest;
use App\Http\Resources\AdminSubscriptionResource;
use App\Services\AdminSubscriptionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSubscriptionController extends Controller
{
    use ApiResponse;

    protected AdminSubscriptionService $subService;

    public function __construct(AdminSubscriptionService $subService)
    {
        $this->subService = $subService;
    }

    /**
     * List all subscriptions with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $subs = $this->subService->listSubscriptions($request->all());
        
        return $this->success(
            AdminSubscriptionResource::collection($subs)->response()->getData(true),
            'Subscriptions retrieved successfully'
        );
    }

    /**
     * Show a single subscription detail.
     */
    public function show(int $id): JsonResponse
    {
        $sub = \App\Models\Subscription::with('plan', 'tenant')->findOrFail($id);
        
        return $this->success(new AdminSubscriptionResource($sub), 'Subscription details retrieved successfully');
    }

    /**
     * Assign a plan to a tenant.
     */
    public function assign(string $tenantId, AssignSubscriptionRequest $request): JsonResponse
    {
        $dates = $request->only(['starts_at', 'ends_at']);
        $sub = $this->subService->assignSubscription(
            $tenantId,
            $request->input('subscription_plan_id'),
            $dates
        );
        
        return $this->success(new AdminSubscriptionResource($sub), 'Subscription assigned successfully', 201);
    }

    /**
     * Update dates or status.
     */
    public function update(int $id, UpdateSubscriptionRequest $request): JsonResponse
    {
        $sub = $this->subService->updateSubscription($id, $request->validated());
        
        return $this->success(new AdminSubscriptionResource($sub), 'Subscription updated successfully');
    }

    /**
     * Cancel subscription.
     */
    public function cancel(int $id): JsonResponse
    {
        $sub = $this->subService->cancelSubscription($id);
        
        return $this->success(new AdminSubscriptionResource($sub), 'Subscription canceled successfully');
    }

    /**
     * Expire subscription.
     */
    public function expire(int $id): JsonResponse
    {
        $sub = $this->subService->expireSubscription($id);
        
        return $this->success(new AdminSubscriptionResource($sub), 'Subscription manually expired successfully');
    }

    /**
     * Renew subscription.
     */
    public function renew(int $id, Request $request): JsonResponse
    {
        $planId = $request->input('subscription_plan_id');
        $sub = $this->subService->renewSubscription($id, $planId);
        
        return $this->success(new AdminSubscriptionResource($sub), 'Subscription renewed successfully');
    }
}
