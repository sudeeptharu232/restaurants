<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\Category;
use App\Models\DaybookClosing;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoOrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Fetch dependencies from active tenant context
        $customer = Customer::first();
        $momo = MenuItem::where('name', 'Chicken Momo')->first();
        $thakali = MenuItem::where('name', 'Thakali Mutton Set')->first();
        $coke = Product::where('name', 'Coca Cola 250ml')->first();
        $owner = User::first(); // Grab tenant owner for shift closing

        if (!$momo || !$thakali || !$coke || !$owner) {
            return; // Safety check
        }

        // Calculate math values
        // Chicken Momo * 2 = 360
        // Thakali Mutton Set * 1 = 550
        // Coke * 2 = 160
        // Subtotal = 1070
        // Discount = 70.00
        // VAT = 13% on 1000 = 130.00
        // Service Charge = 10% on 1000 = 100.00
        // Total = 1230.00

        // 2. Create Order
        $order = Order::updateOrCreate(
            ['order_number' => 'ORD-2026-0001'],
            [
                'customer_id' => $customer->id,
                'type' => 'dine_in',
                'status' => 'completed',
                'payment_status' => 'paid',
                'kitchen_status' => 'served',
                'subtotal' => 1070.00,
                'discount_amount' => 70.00,
                'tax_amount' => 130.00,
                'vat_amount' => 130.00,
                'service_charge_amount' => 100.00,
                'total' => 1230.00,
                'paid_amount' => 1230.00,
                'due_amount' => 0.00,
                'notes' => 'Table 4, Extra spicy chutney requested.',
            ]
        );

        // 3. Create Order Items
        $item1 = OrderItem::updateOrCreate(
            ['order_id' => $order->id, 'menu_item_id' => $momo->id],
            [
                'name' => 'Chicken Momo',
                'quantity' => 2.00,
                'unit_price' => 180.00,
                'discount_amount' => 0.00,
                'vat_amount' => 46.80,
                'total_amount' => 360.00,
                'kitchen_status' => 'served',
            ]
        );

        $item2 = OrderItem::updateOrCreate(
            ['order_id' => $order->id, 'menu_item_id' => $thakali->id],
            [
                'name' => 'Thakali Mutton Set',
                'quantity' => 1.00,
                'unit_price' => 550.00,
                'discount_amount' => 0.00,
                'vat_amount' => 71.50,
                'total_amount' => 550.00,
                'kitchen_status' => 'served',
            ]
        );

        $item3 = OrderItem::updateOrCreate(
            ['order_id' => $order->id, 'product_id' => $coke->id],
            [
                'name' => 'Coca Cola 250ml',
                'quantity' => 2.00,
                'unit_price' => 80.00,
                'discount_amount' => 0.00,
                'vat_amount' => 20.80,
                'total_amount' => 160.00,
                'kitchen_status' => 'served',
            ]
        );

        // 4. Create Kitchen Order Ticket (KOT)
        $kot = KitchenTicket::updateOrCreate(
            ['order_id' => $order->id, 'ticket_number' => 'KOT-2026-000001'],
            [
                'status' => 'served',
                'type' => 'KOT',
            ]
        );

        KitchenTicketItem::updateOrCreate(
            ['kitchen_ticket_id' => $kot->id, 'order_item_id' => $item1->id],
            [
                'quantity' => 2.00,
                'status' => 'served',
            ]
        );

        KitchenTicketItem::updateOrCreate(
            ['kitchen_ticket_id' => $kot->id, 'order_item_id' => $item2->id],
            [
                'quantity' => 1.00,
                'status' => 'served',
            ]
        );

        // 5. Create Invoice
        $invoice = Invoice::updateOrCreate(
            ['order_id' => $order->id, 'invoice_number' => 'INV-2026-0001'],
            [
                'subtotal' => 1070.00,
                'discount' => 70.00,
                'vat_amount' => 130.00,
                'service_charge' => 100.00,
                'total' => 1230.00,
                'payment_status' => 'paid',
            ]
        );

        // 6. Create Payment (cash transaction)
        Payment::updateOrCreate(
            ['invoice_id' => $invoice->id],
            [
                'gateway' => 'cash',
                'amount' => 1230.00,
                'payment_date' => Carbon::now(),
            ]
        );

        // 7. Seed Operational Expenses
        $expenseCategory = Category::where('slug', 'beverages')->first();
        Expense::updateOrCreate(
            ['title' => 'Raw Coca Cola crates supply'],
            [
                'category_id' => $expenseCategory ? $expenseCategory->id : null,
                'description' => 'Purchased 3 Coca Cola crates from wholesaler for retail stock.',
                'amount' => 300.00,
                'expense_date' => Carbon::now()->toDateString(),
            ]
        );

        // 8. Seed Daybook Closings Shift drawer auditing
        DaybookClosing::updateOrCreate(
            ['closing_date' => Carbon::now()->toDateString()],
            [
                'opening_balance' => 2000.00,
                'cash_sales' => 1230.00,
                'digital_sales' => 0.00,
                'expenses' => 300.00,
                'expected_balance' => 2930.00, // 2000 + 1230 - 300 = 2930 expected
                'actual_balance' => 2930.00,
                'discrepancy' => 0.00,
                'closed_by_user_id' => $owner->id,
                'notes' => 'Shift closed smoothly. Cash matched exactly with invoice register.',
            ]
        );
    }
}
