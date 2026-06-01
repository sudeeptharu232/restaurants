<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreManualPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use ApiResponse;

    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Helper to enforce permissions check inline.
     */
    protected function authorizePermission(string $permission, bool $allowReadStaff = false): void
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
            'manager' => [
                'view_pos', 'manage_pos',
                'view_inventory', 'manage_inventory',
                'view_customers', 'manage_customers',
                'view_products', 'manage_products',
                'view_menu', 'manage_menu',
                'view_tables', 'manage_tables',
                'view_orders', 'manage_orders',
                'view_kot', 'manage_kot',
                'manage_invoices', 'view_invoices',
                'manage_payments', 'view_payments'
            ],
            'staff' => [
                'view_pos', 'manage_pos',
                'view_customers',
                'view_products',
                'view_menu',
                'view_tables',
                'view_orders', 'manage_orders',
                'view_kot', 'manage_kot'
            ],
        ];

        $userRole = $user->role ?? 'staff';
        $userPerms = $permissionsMap[$userRole] ?? [];

        // Check if owner or has manage_payments permission
        $hasPermission = in_array('*', $userPerms) || in_array($permission, $userPerms);

        // For read operations: allow if they have manage_payments OR manage_invoices OR manage_orders OR view_orders OR view_invoices
        if ($allowReadStaff && !$hasPermission) {
            if (
                in_array('manage_invoices', $userPerms) ||
                in_array('view_invoices', $userPerms) ||
                in_array('manage_orders', $userPerms) ||
                in_array('view_orders', $userPerms)
            ) {
                $hasPermission = true;
            }
        }

        if (!$hasPermission) {
            abort(response()->json([
                'success' => false,
                'message' => 'Forbidden: You do not have permission to execute this operation'
            ], 403));
        }
    }

    /**
     * Display a listing of payments.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission('manage_payments', true);

        $query = Payment::query()
            ->select([
                'id',
                'invoice_id',
                'order_id',
                'customer_id',
                'amount',
                'gateway',
                'transaction_id',
                'status',
                'payment_date',
                'notes',
                'created_at',
                'updated_at',
            ]);

        if ($request->has('gateway')) {
            $query->where('gateway', $request->input('gateway'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = min((int) $request->input('per_page', 25), 100);
        $payments = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->success(
            PaymentResource::collection($payments),
            'Payments retrieved successfully'
        );
    }

    /**
     * Display the specified payment.
     */
    public function show($tenant, $id): JsonResponse
    {
        $this->authorizePermission('manage_payments', true);

        $payment = Payment::with(['invoice', 'order', 'customer'])->findOrFail($id);

        return $this->success(
            new PaymentResource($payment),
            'Payment retrieved successfully'
        );
    }

    /**
     * Store a manual payment.
     */
    public function storeManual(StoreManualPaymentRequest $request): JsonResponse
    {
        $this->authorizePermission('manage_payments');

        $payment = $this->paymentService->createManualPayment($request->validated());

        return $this->success(
            new PaymentResource($payment),
            'Manual payment recorded successfully',
            201
        );
    }
}
