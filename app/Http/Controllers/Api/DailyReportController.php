<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateDailyReportRequest;
use App\Http\Resources\DailyReportResource;
use App\Jobs\SendWhatsAppDailyReportJob;
use App\Models\DailyReport;
use App\Models\WhatsAppReportSetting;
use App\Services\DailyReportService;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailyReportController extends Controller
{
    use ApiResponse;

    protected DailyReportService $reportService;

    public function __construct(DailyReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Permission helper.
     */
    protected function authorizePermission(string $permission): void
    {
        $user = auth()->user();
        if (!$user) {
            abort(response()->json(['success' => false, 'message' => 'Unauthenticated'], 401));
        }
        if (!$user->is_active) {
            abort(response()->json(['success' => false, 'message' => 'Forbidden: Account suspended'], 403));
        }

        $permissionsMap = [
            'super_admin' => ['*'],
            'owner'       => ['*'],
            'manager'     => ['view_analytics', 'manage_settings', 'manage_reports'],
            'staff'       => [],
        ];

        $userRole = $user->role ?? 'staff';
        $userPerms = $permissionsMap[$userRole] ?? [];
        $hasPermission = in_array('*', $userPerms) || in_array($permission, $userPerms);

        if (!$hasPermission) {
            abort(response()->json([
                'success' => false,
                'message' => 'Forbidden: You do not have permission to execute this operation',
            ], 403));
        }
    }

    /**
     * GET /api/{tenant}/daily-reports
     * List all daily reports.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission('view_analytics');

        $reports = DailyReport::orderBy('report_date', 'desc')
            ->paginate($request->get('per_page', 30));

        return $this->success(
            DailyReportResource::collection($reports)->response()->getData(true),
            'Daily reports retrieved successfully'
        );
    }

    /**
     * GET /api/{tenant}/daily-reports/{id}
     * View a single daily report.
     */
    public function show($tenant, int $id): JsonResponse
    {
        $this->authorizePermission('view_analytics');

        $report = DailyReport::findOrFail($id);

        return $this->success(new DailyReportResource($report), 'Daily report retrieved successfully');
    }

    /**
     * POST /api/{tenant}/daily-reports/generate
     * Manually generate a daily report for a given date.
     */
    public function generate(GenerateDailyReportRequest $request): JsonResponse
    {
        $this->authorizePermission('manage_settings');

        $date = $request->input('report_date', Carbon::now('Asia/Kathmandu')->toDateString());
        $regenerate = (bool) $request->input('regenerate', false);

        $report = $this->reportService->generateForDate($date, $regenerate);

        return $this->success(new DailyReportResource($report), 'Daily report generated successfully');
    }

    /**
     * POST /api/{tenant}/daily-reports/{id}/send-whatsapp
     * Manually trigger WhatsApp send for a report.
     */
    public function sendWhatsApp($tenant, int $id): JsonResponse
    {
        $this->authorizePermission('manage_settings');

        $report = DailyReport::findOrFail($id);

        // Prevent re-sending already sent reports without explicit regeneration
        if ($report->whatsapp_status === 'sent') {
            return $this->error('Report has already been sent via WhatsApp. Regenerate if you need to resend.', 409);
        }

        $setting = WhatsAppReportSetting::first();
        if (!$setting) {
            return $this->error('WhatsApp settings not configured for this tenant.', 422);
        }

        $result = $this->reportService->sendViaWhatsApp($report, $setting);
        $report->refresh();

        $message = $result['success']
            ? 'WhatsApp report sent successfully'
            : 'WhatsApp report send failed: ' . ($result['error'] ?? 'Unknown error');

        $statusCode = $result['success'] ? 200 : 422;

        return response()->json([
            'success' => $result['success'],
            'message' => $message,
            'data'    => new DailyReportResource($report),
            'sandbox' => $result['sandbox'] ?? false,
        ], $statusCode);
    }
}
