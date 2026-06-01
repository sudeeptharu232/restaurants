<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\InvoiceService;
use App\Services\PdfService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    use ApiResponse;

    protected InvoiceService $invoiceService;
    protected PdfService $pdfService;

    public function __construct(InvoiceService $invoiceService, PdfService $pdfService)
    {
        $this->invoiceService = $invoiceService;
        $this->pdfService = $pdfService;
    }

    /**
     * Helper to enforce permissions check inline.
     */
    protected function authorizePermission(string $permission, bool $allowOrderStaff = false): void
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
                'manage_invoices', 'view_invoices'
            ],
            'staff' => [
                'view_pos', 'manage_pos',
                'view_customers',
                'view_products',
                'view_menu',
                'view_tables',
                'view_orders', 'manage_orders',
                'view_kot', 'manage_kot'
                // Staff blocked from invoices by default unless explicit permission given
            ],
        ];

        $userRole = $user->role ?? 'staff';
        $userPerms = $permissionsMap[$userRole] ?? [];

        // Check if owner or has manage_invoices permission
        $hasPermission = in_array('*', $userPerms) || in_array($permission, $userPerms);

        // For read operations: allow if they have manage_invoices OR manage_orders permission
        if ($allowOrderStaff && !$hasPermission) {
            if (in_array('manage_orders', $userPerms) || in_array('view_orders', $userPerms)) {
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
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission('manage_invoices', true);

        $query = Invoice::with(['customer:id,name,phone', 'order:id,order_number,status']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = min((int) $request->input('per_page', 25), 100);
        $invoices = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->success(
            InvoiceResource::collection($invoices),
            'Invoices retrieved successfully'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $this->authorizePermission('manage_invoices');

        $invoice = $this->invoiceService->createManualInvoice($request->validated());

        return $this->success(
            new InvoiceResource($invoice->load(['items', 'customer', 'order'])),
            'Invoice created successfully',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show($tenant, $id): JsonResponse
    {
        $this->authorizePermission('manage_invoices', true);

        $invoice = Invoice::with(['items', 'customer', 'order'])->findOrFail($id);

        return $this->success(
            new InvoiceResource($invoice),
            'Invoice retrieved successfully'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateInvoiceRequest $request, $tenant, $id): JsonResponse
    {
        $this->authorizePermission('manage_invoices');

        $invoice = Invoice::findOrFail($id);
        $updated = $this->invoiceService->updateInvoice($invoice, $request->validated());

        return $this->success(
            new InvoiceResource($updated->load(['items', 'customer', 'order'])),
            'Invoice updated successfully'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($tenant, $id): JsonResponse
    {
        $this->authorizePermission('manage_invoices');

        $invoice = Invoice::findOrFail($id);

        // Prevent deleting active issued/paid/partially_paid invoices
        if (in_array($invoice->status, ['issued', 'paid', 'partially_paid'])) {
            return abort(response()->json([
                'success' => false,
                'message' => 'Cannot delete active issued or paid invoices.'
            ], 422));
        }

        $invoice->delete();

        return $this->success(
            null,
            'Invoice deleted successfully'
        );
    }

    /**
     * Generate invoice from an existing Order.
     */
    public function createFromOrder(Request $request, $tenant, $orderId): JsonResponse
    {
        $this->authorizePermission('manage_invoices');

        $order = Order::findOrFail($orderId);
        
        $invoice = $this->invoiceService->createInvoiceFromOrder($order, [
            'allow_duplicate' => filter_var($request->input('allow_duplicate', false), FILTER_VALIDATE_BOOLEAN)
        ]);

        return $this->success(
            new InvoiceResource($invoice->load(['items', 'customer', 'order'])),
            'Invoice generated from order successfully',
            201
        );
    }

    /**
     * Transition invoice to issued and generate PDF.
     */
    public function issue($tenant, $id): JsonResponse
    {
        $this->authorizePermission('manage_invoices');

        $invoice = Invoice::findOrFail($id);
        $issued = $this->invoiceService->issueInvoice($invoice);

        return $this->success(
            new InvoiceResource($issued->load(['items', 'customer', 'order'])),
            'Invoice issued successfully'
        );
    }

    /**
     * Transition invoice to cancelled.
     */
    public function cancel($tenant, $id): JsonResponse
    {
        $this->authorizePermission('manage_invoices');

        $invoice = Invoice::findOrFail($id);
        $cancelled = $this->invoiceService->cancelInvoice($invoice);

        return $this->success(
            new InvoiceResource($cancelled->load(['items', 'customer', 'order'])),
            'Invoice cancelled successfully'
        );
    }

    /**
     * Stream download response for the invoice PDF.
     */
    public function downloadPdf($tenant, $id)
    {
        $this->authorizePermission('manage_invoices', true);

        $invoice = Invoice::findOrFail($id);

        return $this->pdfService->downloadInvoicePdf($invoice);
    }

    /**
     * Re-render PDF file stream.
     */
    public function regeneratePdf($tenant, $id): JsonResponse
    {
        $this->authorizePermission('manage_invoices');

        $invoice = Invoice::findOrFail($id);
        $this->pdfService->generateInvoicePdf($invoice);

        return $this->success(
            new InvoiceResource($invoice->load(['items', 'customer', 'order'])),
            'Invoice PDF regenerated successfully'
        );
    }
}
