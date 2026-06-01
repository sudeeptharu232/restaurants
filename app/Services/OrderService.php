<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\RestaurantTable;
use App\Models\BusinessSetting;
use App\Models\MenuItem;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

class OrderService
{
    /**
     * Create a new order with items.
     */
    public function createOrder(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $order = new Order();
            $order->customer_id = $data['customer_id'] ?? null;
            $order->restaurant_table_id = $data['restaurant_table_id'] ?? null;
            $order->type = $data['order_type'] ?? 'regular';
            $order->status = $data['status'] ?? 'pending';
            $order->payment_status = $data['payment_status'] ?? 'unpaid';
            $order->kitchen_status = 'pending';
            $order->notes = $data['notes'] ?? null;
            $order->delivery_address = $data['delivery_address'] ?? null;
            $order->service_charge_amount = $data['service_charge_amount'] ?? 0;
            
            // Auto generate order number
            $order->order_number = $this->generateOrderNumber();

            // Temp save order to generate ID
            $order->subtotal = 0;
            $order->discount_amount = 0;
            $order->tax_amount = 0;
            $order->vat_amount = 0;
            $order->total = 0;
            $order->paid_amount = 0;
            $order->due_amount = 0;
            $order->save();

            // Calculate items
            $subtotal = 0;
            $totalItemDiscounts = 0;
            $orderItems = [];

            foreach ($data['items'] as $itemData) {
                $orderItem = new OrderItem();
                $orderItem->order_id = $order->id;
                $orderItem->menu_item_id = $itemData['menu_item_id'] ?? null;
                $orderItem->product_id = $itemData['product_id'] ?? null;
                $orderItem->service_id = $itemData['service_id'] ?? null;
                $orderItem->quantity = $itemData['quantity'];
                $orderItem->notes = $itemData['notes'] ?? null;
                $orderItem->kitchen_status = 'pending';

                // Fetch database reference details if possible
                $resolvedName = null;
                $resolvedPrice = null;

                if ($orderItem->menu_item_id) {
                    $menuItem = MenuItem::findOrFail($orderItem->menu_item_id);
                    $resolvedName = $menuItem->name;
                    $resolvedPrice = $menuItem->price;
                } elseif ($orderItem->product_id) {
                    $product = Product::findOrFail($orderItem->product_id);
                    $resolvedName = $product->name;
                    $resolvedPrice = $product->price;
                } elseif ($orderItem->service_id) {
                    $service = Service::findOrFail($orderItem->service_id);
                    $resolvedName = $service->name;
                    $resolvedPrice = $service->price;
                }

                $orderItem->name = $resolvedName ?? $itemData['name'] ?? 'Custom Item';
                $unitPrice = $resolvedPrice ?? $itemData['unit_price'] ?? 0;
                $orderItem->unit_price = $unitPrice;

                $itemSubtotal = $orderItem->quantity * $unitPrice;
                $itemDiscount = $itemData['discount_amount'] ?? 0;
                
                // Keep item discount from exceeding item subtotal
                if ($itemDiscount > $itemSubtotal) {
                    $itemDiscount = $itemSubtotal;
                }

                $orderItem->discount_amount = $itemDiscount;
                $orderItem->total_amount = $itemSubtotal - $itemDiscount;
                
                $subtotal += $itemSubtotal;
                $totalItemDiscounts += $itemDiscount;

                $orderItems[] = $orderItem;
            }

            // Order-level discount
            $orderLevelDiscount = $data['discount_amount'] ?? 0;
            $orderDiscount = $totalItemDiscounts + $orderLevelDiscount;

            // Keep discount from exceeding subtotal
            if ($orderDiscount > $subtotal) {
                $orderDiscount = $subtotal;
            }

            $taxableAmount = $subtotal - $orderDiscount;

            // Resolve VAT dynamically
            $vatEnabledSetting = BusinessSetting::where('tenant_id', tenant('id'))
                ->where('key', 'vat_enabled')
                ->first();
            $vatEnabled = $vatEnabledSetting ? filter_var($vatEnabledSetting->value, FILTER_VALIDATE_BOOLEAN) : false;

            $vatAmount = $vatEnabled ? round($taxableAmount * 0.13, 2) : 0;

            $totalAmount = $taxableAmount + $vatAmount + $order->service_charge_amount;
            $paidAmount = 0;
            $dueAmount = $totalAmount - $paidAmount;

            // Update order financial calculations
            $order->subtotal = $subtotal;
            $order->discount_amount = $orderDiscount;
            $order->tax_amount = $vatAmount; // compatible with original column name
            $order->vat_amount = $vatAmount;
            $order->total = $totalAmount;
            $order->paid_amount = $paidAmount;
            $order->due_amount = $dueAmount;
            $order->save();

            // Save items
            foreach ($orderItems as $item) {
                // If VAT enabled, calculate item-level VAT safely
                $itemTaxable = $item->total_amount;
                $item->vat_amount = $vatEnabled ? round($itemTaxable * 0.13, 2) : 0;
                $item->save();
            }

            // Dine-in table status update
            if ($order->type === 'dine_in' && $order->restaurant_table_id) {
                $this->updateTableStatus($order->restaurant_table_id, 'occupied');
            }

            return $order->load('items');
        });
    }

    /**
     * Update an existing order with items.
     */
    public function updateOrder(Order $order, array $data): Order
    {
        return DB::transaction(function () use ($order, $data) {
            $oldTableId = $order->restaurant_table_id;

            $order->customer_id = $data['customer_id'] ?? $order->customer_id;
            $order->restaurant_table_id = array_key_exists('restaurant_table_id', $data) ? $data['restaurant_table_id'] : $order->restaurant_table_id;
            $order->type = $data['order_type'] ?? $order->type;
            $order->status = $data['status'] ?? $order->status;
            $order->payment_status = $data['payment_status'] ?? $order->payment_status;
            $order->notes = $data['notes'] ?? $order->notes;
            $order->delivery_address = $data['delivery_address'] ?? $order->delivery_address;
            $order->service_charge_amount = $data['service_charge_amount'] ?? $order->service_charge_amount;

            // Handle table swap
            if ($oldTableId !== $order->restaurant_table_id) {
                if ($oldTableId) {
                    $this->updateTableStatus($oldTableId, 'vacant');
                }
                if ($order->type === 'dine_in' && $order->restaurant_table_id) {
                    $this->updateTableStatus($order->restaurant_table_id, 'occupied');
                }
            }

            // Recalculate items if provided
            if (isset($data['items']) && is_array($data['items'])) {
                // Delete old items
                $order->items()->delete();

                $subtotal = 0;
                $totalItemDiscounts = 0;
                $orderItems = [];

                foreach ($data['items'] as $itemData) {
                    $orderItem = new OrderItem();
                    $orderItem->order_id = $order->id;
                    $orderItem->menu_item_id = $itemData['menu_item_id'] ?? null;
                    $orderItem->product_id = $itemData['product_id'] ?? null;
                    $orderItem->service_id = $itemData['service_id'] ?? null;
                    $orderItem->quantity = $itemData['quantity'];
                    $orderItem->notes = $itemData['notes'] ?? null;
                    $orderItem->kitchen_status = $itemData['kitchen_status'] ?? 'pending';

                    $resolvedName = null;
                    $resolvedPrice = null;

                    if ($orderItem->menu_item_id) {
                        $menuItem = MenuItem::findOrFail($orderItem->menu_item_id);
                        $resolvedName = $menuItem->name;
                        $resolvedPrice = $menuItem->price;
                    } elseif ($orderItem->product_id) {
                        $product = Product::findOrFail($orderItem->product_id);
                        $resolvedName = $product->name;
                        $resolvedPrice = $product->price;
                    } elseif ($orderItem->service_id) {
                        $service = Service::findOrFail($orderItem->service_id);
                        $resolvedName = $service->name;
                        $resolvedPrice = $service->price;
                    }

                    $orderItem->name = $resolvedName ?? $itemData['name'] ?? 'Custom Item';
                    $unitPrice = $resolvedPrice ?? $itemData['unit_price'] ?? 0;
                    $orderItem->unit_price = $unitPrice;

                    $itemSubtotal = $orderItem->quantity * $unitPrice;
                    $itemDiscount = $itemData['discount_amount'] ?? 0;

                    if ($itemDiscount > $itemSubtotal) {
                        $itemDiscount = $itemSubtotal;
                    }

                    $orderItem->discount_amount = $itemDiscount;
                    $orderItem->total_amount = $itemSubtotal - $itemDiscount;

                    $subtotal += $itemSubtotal;
                    $totalItemDiscounts += $itemDiscount;

                    $orderItems[] = $orderItem;
                }

                $orderLevelDiscount = $data['discount_amount'] ?? 0;
                $orderDiscount = $totalItemDiscounts + $orderLevelDiscount;

                if ($orderDiscount > $subtotal) {
                    $orderDiscount = $subtotal;
                }

                $taxableAmount = $subtotal - $orderDiscount;

                // Resolve VAT dynamically
                $vatEnabledSetting = BusinessSetting::where('tenant_id', tenant('id'))
                    ->where('key', 'vat_enabled')
                    ->first();
                $vatEnabled = $vatEnabledSetting ? filter_var($vatEnabledSetting->value, FILTER_VALIDATE_BOOLEAN) : false;

                $vatAmount = $vatEnabled ? round($taxableAmount * 0.13, 2) : 0;

                $totalAmount = $taxableAmount + $vatAmount + $order->service_charge_amount;
                $paidAmount = $order->paid_amount;
                $dueAmount = $totalAmount - $paidAmount;

                $order->subtotal = $subtotal;
                $order->discount_amount = $orderDiscount;
                $order->tax_amount = $vatAmount;
                $order->vat_amount = $vatAmount;
                $order->total = $totalAmount;
                $order->due_amount = $dueAmount;
                $order->save();

                foreach ($orderItems as $item) {
                    $itemTaxable = $item->total_amount;
                    $item->vat_amount = $vatEnabled ? round($itemTaxable * 0.13, 2) : 0;
                    $item->save();
                }
            } else {
                // Just update order-level financials if service charge / discount changed
                $orderLevelDiscount = $data['discount_amount'] ?? $order->discount_amount;
                // Re-fetch all order items
                $subtotal = $order->items()->sum(DB::raw('quantity * unit_price'));
                $totalItemDiscounts = $order->items()->sum('discount_amount');
                $orderDiscount = $totalItemDiscounts + ($data['discount_amount'] ?? 0);

                if ($orderDiscount > $subtotal) {
                    $orderDiscount = $subtotal;
                }

                $taxableAmount = $subtotal - $orderDiscount;

                $vatEnabledSetting = BusinessSetting::where('tenant_id', tenant('id'))
                    ->where('key', 'vat_enabled')
                    ->first();
                $vatEnabled = $vatEnabledSetting ? filter_var($vatEnabledSetting->value, FILTER_VALIDATE_BOOLEAN) : false;

                $vatAmount = $vatEnabled ? round($taxableAmount * 0.13, 2) : 0;

                $totalAmount = $taxableAmount + $vatAmount + $order->service_charge_amount;
                $paidAmount = $order->paid_amount;
                $dueAmount = $totalAmount - $paidAmount;

                $order->subtotal = $subtotal;
                $order->discount_amount = $orderDiscount;
                $order->tax_amount = $vatAmount;
                $order->vat_amount = $vatAmount;
                $order->total = $totalAmount;
                $order->due_amount = $dueAmount;
                $order->save();
            }

            // Sync table vacant trigger if completed or cancelled
            if (in_array($order->status, ['completed', 'cancelled'])) {
                if ($order->restaurant_table_id) {
                    $this->updateTableStatus($order->restaurant_table_id, 'vacant');
                }
            }

            return $order->load('items');
        });
    }

    /**
     * Mark an order as completed.
     */
    public function completeOrder(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order->status = 'completed';
            $order->save();

            if ($order->restaurant_table_id) {
                $this->updateTableStatus($order->restaurant_table_id, 'vacant');
            }

            return $order;
        });
    }

    /**
     * Mark an order as cancelled.
     */
    public function cancelOrder(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order->status = 'cancelled';
            $order->save();

            if ($order->restaurant_table_id) {
                $this->updateTableStatus($order->restaurant_table_id, 'vacant');
            }

            return $order;
        });
    }

    /**
     * Generate KOT/BOT kitchen tickets for the order.
     */
    public function generateKitchenTickets(Order $order): array
    {
        return DB::transaction(function () use ($order) {
            $items = $order->items;
            
            // Incremental generation: Skip items that are already on a ticket
            $existingItemIds = KitchenTicketItem::whereIn('order_item_id', $items->pluck('id'))->pluck('order_item_id')->toArray();
            $newItems = $items->reject(fn($item) => in_array($item->id, $existingItemIds));

            if ($newItems->isEmpty()) {
                return KitchenTicket::where('order_id', $order->id)->with('items.orderItem')->get()->all();
            }

            $groupedItems = [];

            foreach ($newItems as $item) {
                // Determine whether KOT or BOT
                $category = null;
                if ($item->menu_item_id) {
                    $category = $item->menuItem->category ?? null;
                } elseif ($item->product_id) {
                    $category = $item->product->category ?? null;
                }

                $isBeverage = false;
                if ($category) {
                    $slug = strtolower($category->slug ?? '');
                    $name = strtolower($category->name ?? '');
                    
                    if (str_contains($slug, 'drink') || str_contains($slug, 'beverage') || 
                        str_contains($slug, 'bar') || str_contains($slug, 'wine') || 
                        str_contains($slug, 'beer') || str_contains($slug, 'liquor') || 
                        str_contains($slug, 'juice') || str_contains($name, 'drink') || 
                        str_contains($name, 'beverage') || str_contains($name, 'bar')) {
                        $isBeverage = true;
                    }
                }

                $type = $isBeverage ? 'BOT' : 'KOT';
                $groupedItems[$type][] = $item;
            }

            $tickets = [];

            foreach ($groupedItems as $type => $orderItemsList) {
                $ticket = new KitchenTicket();
                $ticket->order_id = $order->id;
                $ticket->type = $type;
                $ticket->status = 'pending';
                $ticket->ticket_number = $this->generateTicketNumber($type);
                $ticket->save();

                foreach ($orderItemsList as $orderItem) {
                    $ticketItem = new KitchenTicketItem();
                    $ticketItem->kitchen_ticket_id = $ticket->id;
                    $ticketItem->order_item_id = $orderItem->id;
                    $ticketItem->quantity = $orderItem->quantity;
                    $ticketItem->status = 'pending';
                    $ticketItem->save();
                }

                $tickets[] = $ticket->load('items.orderItem');
            }

            // Sync order kitchen_status
            $order->kitchen_status = 'preparing';
            $order->save();

            return $tickets;
        });
    }

    /**
     * Auto generate ticket number
     */
    protected function generateTicketNumber(string $type): string
    {
        $year = date('Y');
        $prefix = $type;
        $nextNumber = KitchenTicket::where('ticket_number', 'like', "{$prefix}-{$year}-%")->count() + 1;
        do {
            $ticketNumber = "{$prefix}-{$year}-" . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
            $nextNumber++;
        } while (KitchenTicket::where('ticket_number', $ticketNumber)->exists());

        return $ticketNumber;
    }

    /**
     * Auto generate order number
     */
    protected function generateOrderNumber(): string
    {
        $year = date('Y');
        $nextNumber = Order::where('order_number', 'like', "ORD-{$year}-%")->count() + 1;
        do {
            $orderNumber = "ORD-{$year}-" . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
            $nextNumber++;
        } while (Order::where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }

    /**
     * Helper to safely toggle restaurant table occupied status.
     */
    protected function updateTableStatus(int $tableId, string $status): void
    {
        $table = RestaurantTable::find($tableId);
        if ($table) {
            $table->status = $status;
            $table->save();
        }
    }
}
