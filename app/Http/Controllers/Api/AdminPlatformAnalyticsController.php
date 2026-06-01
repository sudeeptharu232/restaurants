<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PlatformAnalyticsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminPlatformAnalyticsController extends Controller
{
    use ApiResponse;

    protected PlatformAnalyticsService $analyticsService;

    public function __construct(PlatformAnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Get dynamic platform analytics overview.
     */
    public function index(): JsonResponse
    {
        $metrics = $this->analyticsService->getDashboardMetrics();
        
        return $this->success($metrics, 'Platform overview analytics retrieved successfully');
    }

    /**
     * Get platform tenant registry stats.
     */
    public function tenants(): JsonResponse
    {
        $metrics = $this->analyticsService->getDashboardMetrics();
        
        return $this->success([
            'total_tenants' => $metrics['total_tenants'],
            'active_tenants' => $metrics['active_tenants'],
            'inactive_tenants' => $metrics['inactive_tenants'],
            'suspended_tenants' => $metrics['suspended_tenants'],
        ], 'Tenant stats retrieved successfully');
    }

    /**
     * Get platform revenue analytics.
     */
    public function revenue(): JsonResponse
    {
        $metrics = $this->analyticsService->getDashboardMetrics();
        
        return $this->success([
            'active_subscriptions' => $metrics['active_subscriptions'],
            'trial_subscriptions' => $metrics['trial_subscriptions'],
            'expired_subscriptions' => $metrics['expired_subscriptions'],
        ], 'Platform revenue analytics retrieved successfully');
    }

    /**
     * Aggregate and show operational usage statistics across the isolated tenant databases.
     */
    public function usage(): JsonResponse
    {
        $usage = $this->analyticsService->getUsageMetrics();
        
        return $this->success($usage, 'Platform usage aggregates retrieved successfully');
    }
}
