<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAuditLogController extends Controller
{
    use ApiResponse;

    /**
     * List all audit logs with pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::query()->latest();
        
        if ($request->has('tenant_id')) {
            $query->where('tenant_id', $request->input('tenant_id'));
        }
        
        if ($request->has('event')) {
            $query->where('event', $request->input('event'));
        }
        
        $logs = $query->paginate($request->input('per_page', 15));
        
        return $this->success(
            AuditLogResource::collection($logs)->response()->getData(true),
            'Audit logs retrieved successfully'
        );
    }

    /**
     * Show detailed audit log record.
     */
    public function show(int $id): JsonResponse
    {
        $log = AuditLog::findOrFail($id);
        
        return $this->success(new AuditLogResource($log), 'Audit log details retrieved successfully');
    }
}
