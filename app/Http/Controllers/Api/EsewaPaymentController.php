<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InitiateEsewaPaymentRequest;
use App\Http\Requests\VerifyEsewaPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\PaymentService;
use App\Services\EsewaPaymentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EsewaPaymentController extends Controller
{
    use ApiResponse;

    protected PaymentService $paymentService;
    protected EsewaPaymentService $esewaService;

    public function __construct(PaymentService $paymentService, EsewaPaymentService $esewaService)
    {
        $this->paymentService = $paymentService;
        $this->esewaService = $esewaService;
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

        $hasPermission = in_array('*', $userPerms) || in_array($permission, $userPerms);

        if (!$hasPermission) {
            abort(response()->json([
                'success' => false,
                'message' => 'Forbidden: You do not have permission to execute this operation'
            ], 403));
        }
    }

    /**
     * Initiate eSewa payment for invoice or order.
     */
    public function initiate($tenant, InitiateEsewaPaymentRequest $request): JsonResponse
    {
        $this->authorizePermission('manage_payments');

        $invoiceId = $request->input('invoice_id');
        $orderId = $request->input('order_id');
        $amount = (float)$request->input('amount');

        $invoice = $invoiceId ? Invoice::findOrFail($invoiceId) : null;
        $order = $orderId ? Order::findOrFail($orderId) : null;

        $payload = $this->esewaService->initiatePayment($invoice, $order, $amount);

        return $this->success(
            $payload,
            'eSewa payment payload generated successfully',
            201
        );
    }

    /**
     * Handle manual eSewa verification request.
     */
    public function verify($tenant, VerifyEsewaPaymentRequest $request): JsonResponse
    {
        $this->authorizePermission('manage_payments');

        $uuid = $request->input('transaction_uuid');
        $amount = (float)$request->input('total_amount');
        $refId = $request->input('ref_id');

        $payment = Payment::where('transaction_id', $uuid)->firstOrFail();

        // 1. Verify transaction status with eSewa gateway
        $verified = $this->esewaService->verifyTransaction($uuid, $amount, $refId);

        // 2. Mark successful and apply balances
        $updated = $this->paymentService->completeGatewayPayment($payment, $refId, $verified);

        return $this->success(
            new PaymentResource($updated),
            'eSewa payment verified and applied successfully'
        );
    }

    /**
     * Public success callback landing (does not require Sanctum).
     */
    public function esewaSuccess(Request $request, $tenant): JsonResponse
    {
        $dataParam = $request->input('data');
        
        $uuid = null;
        $amount = 0.00;
        $refId = null;

        // Parse Standard Nepalese base64 ePay format
        if ($dataParam) {
            $decoded = json_decode(base64_decode($dataParam), true);
            if ($decoded) {
                $uuid = $decoded['transaction_uuid'] ?? null;
                $amount = (float)($decoded['total_amount'] ?? 0);
                $refId = $decoded['transaction_code'] ?? null;
            }
        }

        // Direct request params fallback for seamless testing
        $uuid = $uuid ?? $request->input('transaction_uuid');
        $amount = $amount ?: (float)$request->input('total_amount');
        $refId = $refId ?? $request->input('ref_id');

        if (!$uuid || !$amount || !$refId) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid eSewa callback payload parameters.'
            ], 422);
        }

        $payment = Payment::where('transaction_id', $uuid)->first();
        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Associated pending payment not found.'
            ], 404);
        }

        try {
            // Verify and complete
            $verified = $this->esewaService->verifyTransaction($uuid, $amount, $refId);
            $updated = $this->paymentService->completeGatewayPayment($payment, $refId, $verified);

            return response()->json([
                'success' => true,
                'message' => 'eSewa payment successfully verified and completed.',
                'data' => new PaymentResource($updated)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Public failure callback landing (does not require Sanctum).
     */
    public function esewaFailure(Request $request, $tenant): JsonResponse
    {
        $dataParam = $request->input('data');
        
        $uuid = null;

        if ($dataParam) {
            $decoded = json_decode(base64_decode($dataParam), true);
            if ($decoded) {
                $uuid = $decoded['transaction_uuid'] ?? null;
            }
        }

        $uuid = $uuid ?? $request->input('transaction_uuid');

        if (!$uuid) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid eSewa callback payload parameters.'
            ], 422);
        }

        $payment = Payment::where('transaction_id', $uuid)->first();
        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Associated payment not found.'
            ], 404);
        }

        $updated = $this->paymentService->failGatewayPayment($payment, ['error' => 'User cancelled or gateway transaction failed.']);

        return response()->json([
            'success' => false,
            'message' => 'eSewa payment marked as failed.',
            'data' => new PaymentResource($updated)
        ], 422);
    }
}
