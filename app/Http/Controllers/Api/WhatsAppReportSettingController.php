<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateWhatsAppReportSettingRequest;
use App\Http\Resources\WhatsAppReportSettingResource;
use App\Models\WhatsAppReportSetting;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class WhatsAppReportSettingController extends Controller
{
    use ApiResponse;

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
            'manager'     => ['manage_settings', 'view_analytics'],
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
     * GET /api/{tenant}/whatsapp-settings
     * View current WhatsApp report settings.
     */
    public function show(): JsonResponse
    {
        $this->authorizePermission('manage_settings');

        // Return existing settings, or a default empty model
        $setting = WhatsAppReportSetting::first() ?? new WhatsAppReportSetting([
            'enabled'                  => false,
            'owner_whatsapp_number'    => null,
            'send_time'                => '22:00:00',
            'timezone'                 => 'Asia/Kathmandu',
            'include_sales_summary'    => true,
            'include_payment_summary'  => true,
            'include_due_summary'      => true,
            'include_top_products'     => true,
            'include_inventory_alerts' => true,
        ]);

        return $this->success(new WhatsAppReportSettingResource($setting), 'WhatsApp settings retrieved successfully');
    }

    /**
     * PUT /api/{tenant}/whatsapp-settings
     * Update WhatsApp report settings.
     */
    public function update(UpdateWhatsAppReportSettingRequest $request): JsonResponse
    {
        $this->authorizePermission('manage_settings');

        $data = $request->validated();

        // Normalize send_time to HH:MM:SS
        if (isset($data['send_time'])) {
            $data['send_time'] = $data['send_time'] . ':00';
        }

        $setting = WhatsAppReportSetting::first();
        if ($setting) {
            $setting->update($data);
        } else {
            $setting = WhatsAppReportSetting::create($data);
        }

        return $this->success(new WhatsAppReportSettingResource($setting), 'WhatsApp settings updated successfully');
    }
}
