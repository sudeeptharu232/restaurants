<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnalyticsFilterRequest;
use App\Services\AnalyticsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
    use ApiResponse;

    protected AnalyticsService $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Helper to enforce permissions check inline.
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

        // Suspend user active state check
        if (!$user->is_active) {
            abort(response()->json([
                'success' => false,
                'message' => 'Forbidden: Your user account is suspended or inactive'
            ], 403));
        }

        $permissionsMap = [
            'super_admin' => ['*'],
            'owner' => ['*'],
            'manager' => ['view_analytics'],
            'staff' => []
        ];

        $userRole = $user->role ?? 'staff';
        $userPerms = $permissionsMap[$userRole] ?? [];

        $hasPermission = in_array('*', $userPerms) || in_array($permission, $userPerms);

        if (!$hasPermission) {
            abort(response()->json([
                'success' => false,
                'message' => 'Forbidden: You do not have permission to execute this operation'
            ], 403));
        }
    }

    /**
     * GET /api/{tenant}/analytics/overview
     */
    public function overview(): JsonResponse
    {
        $this->authorizePermission('view_analytics');
        $data = $this->analyticsService->getOverviewData();
        return $this->success($data, 'Overview analytics retrieved successfully');
    }

    /**
     * GET /api/{tenant}/analytics/sales
     */
    public function sales(AnalyticsFilterRequest $request): JsonResponse
    {
        $this->authorizePermission('view_analytics');
        $data = $this->analyticsService->getSalesData($request->validated());
        return $this->success($data, 'Sales analytics retrieved successfully');
    }

    /**
     * GET /api/{tenant}/analytics/payments
     */
    public function payments(AnalyticsFilterRequest $request): JsonResponse
    {
        $this->authorizePermission('view_analytics');
        $data = $this->analyticsService->getPaymentsData($request->validated());
        return $this->success($data, 'Payment analytics retrieved successfully');
    }

    /**
     * GET /api/{tenant}/analytics/customers
     */
    public function customers(): JsonResponse
    {
        $this->authorizePermission('view_analytics');
        $data = $this->analyticsService->getCustomersData();
        return $this->success($data, 'Customer analytics retrieved successfully');
    }

    /**
     * GET /api/{tenant}/analytics/products
     */
    public function products(): JsonResponse
    {
        $this->authorizePermission('view_analytics');
        $data = $this->analyticsService->getProductsData();
        return $this->success($data, 'Product analytics retrieved successfully');
    }

    /**
     * GET /api/{tenant}/analytics/expenses
     */
    public function expenses(): JsonResponse
    {
        $this->authorizePermission('view_analytics');
        $data = $this->analyticsService->getExpensesData();
        return $this->success($data, 'Expense analytics retrieved successfully');
    }

    /**
     * GET /api/{tenant}/analytics/due-summary
     */
    public function dueSummary(): JsonResponse
    {
        $this->authorizePermission('view_analytics');
        $data = $this->analyticsService->getDueSummaryData();
        return $this->success($data, 'Due summary analytics retrieved successfully');
    }

    /**
     * GET /api/{tenant}/analytics/top-products
     */
    public function topProducts(): JsonResponse
    {
        $this->authorizePermission('view_analytics');
        $data = $this->analyticsService->getProductsData();
        return $this->success($data, 'Top selling products retrieved successfully');
    }

    /**
     * GET /api/{tenant}/analytics/daily-report
     */
    public function dailyReport(): JsonResponse
    {
        $this->authorizePermission('view_analytics');
        $data = $this->analyticsService->getDailyReportData();
        return $this->success($data, 'Daily report data retrieved successfully');
    }
}
