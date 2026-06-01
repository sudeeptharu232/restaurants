<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateKitchenTicketStatusRequest;
use App\Http\Requests\UpdateKitchenTicketItemStatusRequest;
use App\Http\Resources\KitchenTicketResource;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\Order;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KitchenTicketController extends Controller
{
    use ApiResponse;

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
                'view_kot', 'manage_kot'
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
     * Display a listing of kitchen tickets (Kitchen Display Queue).
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission('view_kot');

        $query = KitchenTicket::with(['order.table', 'items.orderItem']);

        // Filter by type (KOT, BOT)
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $tickets = $query->orderBy('created_at', 'asc')->get();

        return $this->success(
            KitchenTicketResource::collection($tickets),
            'Kitchen tickets retrieved successfully'
        );
    }

    /**
     * Display the specified kitchen ticket.
     */
    public function show($tenant, $id): JsonResponse
    {
        $this->authorizePermission('view_kot');

        $ticket = KitchenTicket::with(['order.table', 'items.orderItem'])->findOrFail($id);

        return $this->success(
            new KitchenTicketResource($ticket),
            'Kitchen ticket retrieved successfully'
        );
    }

    /**
     * Update the status of the entire kitchen ticket.
     */
    public function updateStatus(UpdateKitchenTicketStatusRequest $request, $tenant, $id): JsonResponse
    {
        $this->authorizePermission('manage_kot');

        return DB::transaction(function () use ($request, $id) {
            $ticket = KitchenTicket::with('items')->findOrFail($id);
            $newStatus = $request->input('status');

            $ticket->status = $newStatus;
            $ticket->save();

            // Bubble down: Update all ticket items to the new status
            foreach ($ticket->items as $item) {
                $item->status = $newStatus;
                $item->save();

                // Also update the individual order items' kitchen_status
                if ($item->orderItem) {
                    $item->orderItem->kitchen_status = $newStatus;
                    $item->orderItem->save();
                }
            }

            // Sync parent order status
            $this->syncOrderKitchenStatus($ticket->order_id);

            return $this->success(
                new KitchenTicketResource($ticket->load(['order.table', 'items.orderItem'])),
                'Kitchen ticket status updated successfully'
            );
        });
    }

    /**
     * Update the status of a specific item within a ticket.
     */
    public function updateItemStatus(UpdateKitchenTicketItemStatusRequest $request, $tenant, $id, $itemId): JsonResponse
    {
        $this->authorizePermission('manage_kot');

        return DB::transaction(function () use ($request, $id, $itemId) {
            $ticket = KitchenTicket::findOrFail($id);
            $ticketItem = KitchenTicketItem::where('kitchen_ticket_id', $id)->findOrFail($itemId);
            
            $newStatus = $request->input('status');
            $ticketItem->status = $newStatus;
            $ticketItem->save();

            // Also update the individual order item's kitchen_status
            if ($ticketItem->orderItem) {
                $ticketItem->orderItem->kitchen_status = $newStatus;
                $ticketItem->orderItem->save();
            }

            // Bubble up: Determine if ticket status should change automatically
            $allItems = KitchenTicketItem::where('kitchen_ticket_id', $id)->get();
            
            $statuses = $allItems->pluck('status')->unique()->toArray();
            
            if (count($statuses) === 1) {
                // If all items share the exact same status, the ticket inherits it!
                $ticket->status = $statuses[0];
            } else {
                // Mixed states: if any item is 'preparing', ticket is 'preparing'
                if (in_array('preparing', $statuses)) {
                    $ticket->status = 'preparing';
                } elseif (in_array('ready', $statuses) && !in_array('pending', $statuses)) {
                    $ticket->status = 'ready';
                } else {
                    $ticket->status = 'preparing';
                }
            }
            $ticket->save();

            // Sync parent order status
            $this->syncOrderKitchenStatus($ticket->order_id);

            return $this->success(
                new KitchenTicketResource($ticket->load(['order.table', 'items.orderItem'])),
                'Kitchen ticket item status updated successfully'
            );
        });
    }

    /**
     * Record printing of a kitchen ticket (set printed_at timestamp).
     */
    public function print($tenant, $id): JsonResponse
    {
        $this->authorizePermission('manage_kot');

        $ticket = KitchenTicket::findOrFail($id);
        $ticket->printed_at = now();
        $ticket->save();

        return $this->success(
            new KitchenTicketResource($ticket->load(['order.table', 'items.orderItem'])),
            'Kitchen ticket printed successfully'
        );
    }

    /**
     * Synchronize parent order's kitchen_status based on all associated tickets.
     */
    protected function syncOrderKitchenStatus(int $orderId): void
    {
        $order = Order::find($orderId);
        if (!$order) {
            return;
        }

        $allTickets = KitchenTicket::where('order_id', $orderId)->get();
        if ($allTickets->isEmpty()) {
            return;
        }

        $statuses = $allTickets->pluck('status')->unique()->toArray();

        if (count($statuses) === 1) {
            // All tickets are KOT/BOT ready or served
            $unifiedStatus = $statuses[0];
            // Map 'preparing' to order preparing, 'ready' to ready, 'served' to served.
            if (in_array($unifiedStatus, ['pending', 'preparing', 'ready', 'served'])) {
                $order->kitchen_status = $unifiedStatus;
            }
        } else {
            // Mixed states
            if (in_array('preparing', $statuses) || in_array('pending', $statuses)) {
                $order->kitchen_status = 'preparing';
            } elseif (in_array('ready', $statuses)) {
                $order->kitchen_status = 'ready';
            }
        }
        $order->save();
    }
}
