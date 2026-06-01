<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\KitchenTicketResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use ApiResponse;

    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
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
                'view_kot', 'manage_kot'
            ],
            'staff' => [
                'view_pos', 'manage_pos',
                'view_customers',
                'view_products',
                'view_menu',
                'view_tables',
                'view_orders', 'manage_orders',
                'view_kot'
            ],
        ];

        $userRole = $user->role ?? 'staff';
        $userPerms = $permissionsMap[$userRole] ?? [];

        if (!in_array('*', $userPerms) && !in_array($permission, $userPerms)) {
            abort(response()->json([
                'success' => false,
                'message' => 'Forbidden: You do not have permission to execute this operation'
            ], 403));
        }
    }

    /**
     * Display a listing of orders.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission('view_orders');

        $query = Order::with([
            'customer:id,name,phone',
            'table:id,table_number,capacity,status',
        ]);

        if ($request->boolean('include_items')) {
            $query->with('items:id,order_id,menu_item_id,product_id,service_id,name,quantity,unit_price,discount_amount,vat_amount,total_amount,kitchen_status,notes,created_at,updated_at');
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by table
        if ($request->has('restaurant_table_id')) {
            $query->where('restaurant_table_id', $request->input('restaurant_table_id'));
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(15);

        return $this->success(
            OrderResource::collection($orders)->response()->getData(true),
            'Orders retrieved successfully'
        );
    }

    /**
     * Store a newly created order.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $this->authorizePermission('manage_orders');

        $order = $this->orderService->createOrder($request->validated());

        return $this->success(
            new OrderResource($order->load(['customer', 'table', 'items'])),
            'Order created successfully',
            201
        );
    }

    /**
     * Display the specified order.
     */
    public function show($tenant, $id): JsonResponse
    {
        $this->authorizePermission('view_orders');

        $order = Order::with(['customer', 'table', 'items', 'kitchenTickets.items.orderItem'])->findOrFail($id);

        return $this->success(
            new OrderResource($order),
            'Order retrieved successfully'
        );
    }

    /**
     * Update the specified order in storage.
     */
    public function update(UpdateOrderRequest $request, $tenant, $id): JsonResponse
    {
        $this->authorizePermission('manage_orders');

        $order = Order::findOrFail($id);
        $updatedOrder = $this->orderService->updateOrder($order, $request->validated());

        return $this->success(
            new OrderResource($updatedOrder->load(['customer', 'table', 'items'])),
            'Order updated successfully'
        );
    }

    /**
     * Remove the specified order from storage.
     */
    public function destroy($tenant, $id): JsonResponse
    {
        $this->authorizePermission('manage_orders');

        $order = Order::findOrFail($id);
        $order->delete();

        return $this->success(
            null,
            'Order deleted successfully'
        );
    }

    /**
     * Mark the order as completed.
     */
    public function complete($tenant, $id): JsonResponse
    {
        $this->authorizePermission('manage_orders');

        $order = Order::findOrFail($id);
        $completedOrder = $this->orderService->completeOrder($order);

        return $this->success(
            new OrderResource($completedOrder->load(['customer', 'table', 'items'])),
            'Order completed successfully'
        );
    }

    /**
     * Mark the order as cancelled.
     */
    public function cancel($tenant, $id): JsonResponse
    {
        $this->authorizePermission('manage_orders');

        $order = Order::findOrFail($id);
        $cancelledOrder = $this->orderService->cancelOrder($order);

        return $this->success(
            new OrderResource($cancelledOrder->load(['customer', 'table', 'items'])),
            'Order cancelled successfully'
        );
    }

    /**
     * Update status (e.g. status, payment_status, kitchen_status).
     */
    public function status(UpdateOrderStatusRequest $request, $tenant, $id): JsonResponse
    {
        $this->authorizePermission('manage_orders');

        $order = Order::findOrFail($id);
        $order->update($request->validated());

        return $this->success(
            new OrderResource($order->load(['customer', 'table', 'items'])),
            'Order status updated successfully'
        );
    }

    /**
     * Generate kitchen ticket from the order items.
     */
    public function kitchenTicket($tenant, $id): JsonResponse
    {
        $this->authorizePermission('manage_orders');

        $order = Order::with('items')->findOrFail($id);
        $tickets = $this->orderService->generateKitchenTickets($order);

        return $this->success(
            KitchenTicketResource::collection($tickets),
            'Kitchen ticket generated successfully'
        );
    }
}
