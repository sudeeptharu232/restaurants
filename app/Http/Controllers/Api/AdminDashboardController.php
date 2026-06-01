<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PlatformAnalyticsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    use ApiResponse;

    protected PlatformAnalyticsService $analyticsService;

    public function __construct(PlatformAnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Get platform central dashboard overview metrics.
     */
    public function index(): JsonResponse
    {
        $metrics = $this->analyticsService->getDashboardMetrics();

        return $this->success($metrics, 'Platform dashboard metrics retrieved successfully');
    }
}
