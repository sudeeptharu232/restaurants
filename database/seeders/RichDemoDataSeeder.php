<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Product;
use App\Models\Service;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\Category;
use App\Models\RestaurantSpace;
use App\Models\RestaurantTable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class RichDemoDataSeeder extends Seeder
{
    /**
     * Run rich demo data seeder for the tenant database.
     * Generates 30 days of realistic restaurant/store POS data.
     */
    public function run(): void
    {
        // ─── 1. EXTRA CUSTOMERS ───────────────────────────────────────────
        $customers = [];
        $customerData = [
            ['name' => 'Anita Tamang',    'phone' => '9801122334', 'email' => 'anita@demo.test',   'address' => 'Thamel, Kathmandu',  'points' => 420],
            ['name' => 'Bikram Rai',      'phone' => '9812233445', 'email' => 'bikram@demo.test',  'address' => 'Baneshwor, KTM',     'points' => 180],
            ['name' => 'Chandra Gurung',  'phone' => '9823344556', 'email' => 'chandra@demo.test', 'address' => 'Kupondole, Lalitpur','points' => 95],
            ['name' => 'Dipika Shrestha', 'phone' => '9834455667', 'email' => 'dipika@demo.test',  'address' => 'Patan, Lalitpur',    'points' => 310],
            ['name' => 'Ekraj Magar',     'phone' => '9845566778', 'email' => 'ekraj@demo.test',   'address' => 'Lakeside, Pokhara',  'points' => 60],
            ['name' => 'Fulbari Limbu',   'phone' => '9856677889', 'email' => 'fulbari@demo.test', 'address' => 'Damak, Jhapa',       'points' => 205],
            ['name' => 'Gita Thapa',      'phone' => '9867788990', 'email' => 'gita@demo.test',    'address' => 'Butwal, Rupandehi',  'points' => 520],
        ];

        foreach ($customerData as $cd) {
            $customers[] = Customer::updateOrCreate(['phone' => $cd['phone']], $cd);
        }

        // Also grab original customers
        $allCustomers = Customer::all()->toArray();
        $customers = Customer::all();

        // ─── 2. EXTRA PRODUCTS ────────────────────────────────────────────
        $bevCategory    = Category::where('slug', 'beverages')->first();
        $bakeryCategory = Category::where('slug', 'bakery')->first();
        $foodCategory   = Category::where('slug', 'nepalese-main-course')->first();
        $salonCategory  = Category::where('slug', 'salon-grooming')->first();

        // Create additional categories if needed
        if (!$bevCategory) {
            $bevCategory = Category::create(['slug' => 'beverages', 'name' => 'Beverages', 'type' => 'product', 'is_active' => true]);
        }
        if (!$bakeryCategory) {
            $bakeryCategory = Category::create(['slug' => 'bakery', 'name' => 'Bakery & Sweets', 'type' => 'product', 'is_active' => true]);
        }
        if (!$foodCategory) {
            $foodCategory = Category::create(['slug' => 'nepalese-main-course', 'name' => 'Nepalese Main Course', 'type' => 'menu', 'is_active' => true]);
        }
        if (!$salonCategory) {
            $salonCategory = Category::create(['slug' => 'salon-grooming', 'name' => 'Salon Grooming', 'type' => 'service', 'is_active' => true]);
        }

        $snacksCategory = Category::updateOrCreate(
            ['slug' => 'snacks-fast-food'],
            ['name' => 'Snacks & Fast Food', 'type' => 'product', 'is_active' => true]
        );
        $drinksCategory = Category::updateOrCreate(
            ['slug' => 'hot-beverages'],
            ['name' => 'Hot Beverages', 'type' => 'product', 'is_active' => true]
        );

        // Products
        $products = [];
        $productData = [
            ['sku' => 'PROD-COKE-250',    'name' => 'Coca Cola 250ml',    'price' => 80,   'cost' => 65,  'stock' => 100, 'cat' => $bevCategory],
            ['sku' => 'PROD-BREAD-BRN',   'name' => 'Brown Bread Large',  'price' => 120,  'cost' => 90,  'stock' => 15,  'cat' => $bakeryCategory],
            ['sku' => 'PROD-SPRITE-250',  'name' => 'Sprite 250ml',       'price' => 80,   'cost' => 60,  'stock' => 80,  'cat' => $bevCategory],
            ['sku' => 'PROD-WATER-1L',    'name' => 'Water Bottle 1L',    'price' => 30,   'cost' => 18,  'stock' => 200, 'cat' => $bevCategory],
            ['sku' => 'PROD-CHIPS-LAYS',  'name' => 'Lays Classic 26g',   'price' => 50,   'cost' => 35,  'stock' => 60,  'cat' => $snacksCategory],
            ['sku' => 'PROD-CHIPS-KUR',   'name' => 'Kurkure 100g',       'price' => 60,   'cost' => 42,  'stock' => 45,  'cat' => $snacksCategory],
            ['sku' => 'PROD-TEA-MILK',    'name' => 'Milk Tea (Large)',    'price' => 60,   'cost' => 25,  'stock' => 500, 'cat' => $drinksCategory],
            ['sku' => 'PROD-COFFEE-HOT',  'name' => 'Hot Americano',      'price' => 150,  'cost' => 60,  'stock' => 500, 'cat' => $drinksCategory],
            ['sku' => 'PROD-CAKE-CHOC',   'name' => 'Chocolate Cake Slice','price' => 180, 'cost' => 95,  'stock' => 20,  'cat' => $bakeryCategory],
            ['sku' => 'PROD-JUICE-MANGO', 'name' => 'Mango Juice 200ml',  'price' => 70,   'cost' => 48,  'stock' => 90,  'cat' => $bevCategory],
        ];

        foreach ($productData as $pd) {
            $products[] = Product::updateOrCreate(
                ['sku' => $pd['sku']],
                [
                    'category_id'    => $pd['cat']->id,
                    'name'           => $pd['name'],
                    'price'          => $pd['price'],
                    'cost_price'     => $pd['cost'],
                    'stock_quantity' => $pd['stock'],
                    'track_stock'    => true,
                    'is_active'      => true,
                ]
            );
        }

        // Menu Items
        $menuItems = [];
        $menuItemData = [
            ['name' => 'Thakali Mutton Set',    'price' => 550, 'desc' => 'Traditional Thakali thali with black lentil, greens and ghee.'],
            ['name' => 'Chicken Momo',           'price' => 180, 'desc' => 'Steamed dumplings stuffed with spiced minced chicken.'],
            ['name' => 'Buff Chowmein',          'price' => 160, 'desc' => 'Stir-fried noodles with buffalo meat and vegetables.'],
            ['name' => 'Dal Bhat Set (Veg)',     'price' => 250, 'desc' => 'Traditional Nepali lentil soup, rice, pickles and vegetables.'],
            ['name' => 'Fried Rice (Chicken)',   'price' => 200, 'desc' => 'Wok-fried rice with chicken, eggs and spring onion.'],
            ['name' => 'Veg Momo',               'price' => 140, 'desc' => 'Steamed veg dumplings with tomato-sesame chutney.'],
            ['name' => 'Spring Roll (4 pcs)',    'price' => 120, 'desc' => 'Crispy golden rolls stuffed with mixed vegetables.'],
            ['name' => 'Nepali Khaja Set',       'price' => 220, 'desc' => 'Beaten rice, black soybean, potato tama curry and pickle.'],
        ];

        foreach ($menuItemData as $md) {
            $menuItems[] = MenuItem::updateOrCreate(
                ['name' => $md['name']],
                [
                    'category_id'  => $foodCategory->id,
                    'description'  => $md['desc'],
                    'price'        => $md['price'],
                    'is_available' => true,
                ]
            );
        }

        // Services
        $serviceData = [
            ['name' => 'Hair Cut & Shave',     'price' => 250,  'duration' => 30],
            ['name' => 'Facial Treatment',      'price' => 800,  'duration' => 60],
            ['name' => 'Full Body Massage',     'price' => 1500, 'duration' => 90],
            ['name' => 'Nail Art (Both Hands)', 'price' => 600,  'duration' => 45],
        ];

        foreach ($serviceData as $sd) {
            Service::updateOrCreate(
                ['name' => $sd['name']],
                [
                    'category_id'      => $salonCategory->id,
                    'price'            => $sd['price'],
                    'duration_minutes' => $sd['duration'],
                    'is_active'        => true,
                ]
            );
        }

        // ─── 3. RESTAURANT SPACES & TABLES ───────────────────────────────
        $mainHall    = RestaurantSpace::updateOrCreate(['name' => 'Main Dining Hall'], ['is_active' => true]);
        $rooftop     = RestaurantSpace::updateOrCreate(['name' => 'Rooftop Garden'],   ['is_active' => true]);
        $privateRoom = RestaurantSpace::updateOrCreate(['name' => 'Private Room A'],   ['is_active' => true]);

        $tableSetup = [
            ['number' => 'T-01', 'space' => $mainHall,    'seats' => 4, 'status' => 'vacant'],
            ['number' => 'T-02', 'space' => $mainHall,    'seats' => 4, 'status' => 'vacant'],
            ['number' => 'T-03', 'space' => $mainHall,    'seats' => 6, 'status' => 'vacant'],
            ['number' => 'T-04', 'space' => $mainHall,    'seats' => 4, 'status' => 'occupied'],
            ['number' => 'T-05', 'space' => $mainHall,    'seats' => 2, 'status' => 'vacant'],
            ['number' => 'R-01', 'space' => $rooftop,     'seats' => 4, 'status' => 'vacant'],
            ['number' => 'R-02', 'space' => $rooftop,     'seats' => 6, 'status' => 'reserved'],
            ['number' => 'P-01', 'space' => $privateRoom, 'seats' => 12, 'status' => 'vacant'],
        ];

        foreach ($tableSetup as $ts) {
            RestaurantTable::updateOrCreate(
                ['table_number' => $ts['number']],
                [
                    'restaurant_space_id' => $ts['space']->id,
                    'capacity'            => $ts['seats'],
                    'status'              => $ts['status'],
                ]
            );
        }

        // ─── 4. HISTORICAL ORDERS (30 days) ──────────────────────────────
        $menuItemsList = MenuItem::all();
        $productsList  = Product::all();
        $customersList = Customer::all();

        $gateways  = ['cash', 'cash', 'cash', 'qr', 'bank', 'esewa'];
        $orderTypes = ['dine_in', 'dine_in', 'takeaway', 'delivery', 'dine_in'];
        $orderNum  = Order::count() + 2; // next order number

        // Generate 3-8 orders per day for the last 30 days
        for ($daysAgo = 30; $daysAgo >= 0; $daysAgo--) {
            $date = Carbon::now()->subDays($daysAgo);
            $ordersPerDay = rand(3, 8);

            for ($i = 0; $i < $ordersPerDay; $i++) {
                $customer = $customersList->random();
                $orderType = $orderTypes[array_rand($orderTypes)];
                $gateway   = $gateways[array_rand($gateways)];

                // Random hour during business hours
                $orderTime = $date->copy()->setHour(rand(10, 21))->setMinute(rand(0, 59));

                // Pick 1-3 random menu items
                $selectedMenuItems = $menuItemsList->random(rand(1, 3));
                $subtotal = 0;
                $vatAmt   = 0;
                $items    = [];

                foreach ($selectedMenuItems as $mi) {
                    $qty   = rand(1, 3);
                    $price = $mi->price;
                    $itemTotal = $qty * $price;
                    $itemVat   = round($itemTotal * 0.13, 2);
                    $subtotal += $itemTotal;
                    $vatAmt   += $itemVat;
                    $items[]   = ['type' => 'menu', 'model' => $mi, 'qty' => $qty, 'price' => $price, 'total' => $itemTotal, 'vat' => $itemVat];
                }

                // Sometimes add a product
                if (rand(0, 1)) {
                    $prod = $productsList->random();
                    $qty   = rand(1, 2);
                    $price = $prod->price;
                    $itemTotal = $qty * $price;
                    $itemVat   = round($itemTotal * 0.13, 2);
                    $subtotal += $itemTotal;
                    $vatAmt   += $itemVat;
                    $items[]   = ['type' => 'product', 'model' => $prod, 'qty' => $qty, 'price' => $price, 'total' => $itemTotal, 'vat' => $itemVat];
                }

                $discountAmt      = (rand(0, 4) === 0) ? round($subtotal * 0.05, 2) : 0; // 20% chance of 5% discount
                $serviceCharge    = round(($subtotal - $discountAmt) * 0.10, 2);
                $total            = round($subtotal - $discountAmt + $vatAmt + $serviceCharge, 2);

                $orderNumberStr = 'ORD-' . $date->format('Y') . '-' . str_pad($orderNum, 4, '0', STR_PAD_LEFT);
                $orderNum++;

                $order = Order::create([
                    'customer_id'          => $customer->id,
                    'order_number'         => $orderNumberStr,
                    'type'                 => $orderType,
                    'status'               => 'completed',
                    'payment_status'       => 'paid',
                    'kitchen_status'       => 'served',
                    'subtotal'             => $subtotal,
                    'discount_amount'      => $discountAmt,
                    'tax_amount'           => $vatAmt,
                    'vat_amount'           => $vatAmt,
                    'service_charge_amount'=> $serviceCharge,
                    'total'                => $total,
                    'paid_amount'          => $total,
                    'due_amount'           => 0.00,
                    'notes'                => null,
                    'created_at'           => $orderTime,
                    'updated_at'           => $orderTime,
                ]);

                // Order Items
                foreach ($items as $item) {
                    OrderItem::create([
                        'order_id'       => $order->id,
                        'menu_item_id'   => $item['type'] === 'menu'    ? $item['model']->id : null,
                        'product_id'     => $item['type'] === 'product' ? $item['model']->id : null,
                        'name'           => $item['model']->name,
                        'quantity'       => $item['qty'],
                        'unit_price'     => $item['price'],
                        'discount_amount'=> 0,
                        'vat_amount'     => $item['vat'],
                        'total_amount'   => $item['total'],
                        'kitchen_status' => 'served',
                        'created_at'     => $orderTime,
                        'updated_at'     => $orderTime,
                    ]);
                }

                // Invoice
                $invoiceNum = 'INV-' . $date->format('Y') . '-' . str_pad($order->id, 4, '0', STR_PAD_LEFT);
                $invoice = Invoice::create([
                    'order_id'       => $order->id,
                    'customer_id'    => $customer->id,
                    'invoice_number' => $invoiceNum,
                    'subtotal'       => $subtotal,
                    'discount'       => $discountAmt,
                    'vat_amount'     => $vatAmt,
                    'service_charge' => $serviceCharge,
                    'total'          => $total,
                    'taxable_amount' => $subtotal - $discountAmt,
                    'paid_amount'    => $total,
                    'due_amount'     => 0.00,
                    'status'         => 'paid',
                    'invoice_date'   => $orderTime->toDateString(),
                    'due_date'       => $orderTime->copy()->addDays(7)->toDateString(),
                    'created_at'     => $orderTime,
                    'updated_at'     => $orderTime,
                ]);

                // Payment
                Payment::create([
                    'invoice_id'  => $invoice->id,
                    'order_id'    => $order->id,
                    'customer_id' => $customer->id,
                    'gateway'     => $gateway,
                    'amount'      => $total,
                    'status'      => 'successful',
                    'payment_date'=> $orderTime,
                    'created_at'  => $orderTime,
                    'updated_at'  => $orderTime,
                ]);
            }
        }

        // ─── 5. FEW ORDERS WITH DUE AMOUNTS (for due-summary analytics) ───
        $dueCustomer = $customersList->first();
        for ($di = 0; $di < 3; $di++) {
            $date  = Carbon::now()->subDays(rand(5, 25));
            $total = rand(500, 2000) * 1.0;
            $paid  = round($total * 0.5, 2);
            $due   = round($total - $paid, 2);

            $dueOrderNum = 'ORD-DUE-' . str_pad($orderNum++, 4, '0', STR_PAD_LEFT);
            $dueOrder = Order::create([
                'customer_id'          => $dueCustomer->id,
                'order_number'         => $dueOrderNum,
                'type'                 => 'dine_in',
                'status'               => 'completed',
                'payment_status'       => 'partially_paid',
                'kitchen_status'       => 'served',
                'subtotal'             => $total,
                'discount_amount'      => 0,
                'tax_amount'           => 0,
                'vat_amount'           => 0,
                'service_charge_amount'=> 0,
                'total'                => $total,
                'paid_amount'          => $paid,
                'due_amount'           => $due,
                'created_at'           => $date,
                'updated_at'           => $date,
            ]);

            $dueInvoiceNum = 'INV-DUE-' . str_pad($dueOrder->id, 4, '0', STR_PAD_LEFT);
            $dueInvoice = Invoice::create([
                'order_id'       => $dueOrder->id,
                'customer_id'    => $dueCustomer->id,
                'invoice_number' => $dueInvoiceNum,
                'subtotal'       => $total,
                'discount'       => 0,
                'vat_amount'     => 0,
                'service_charge' => 0,
                'total'          => $total,
                'taxable_amount' => $total,
                'paid_amount'    => $paid,
                'due_amount'     => $due,
                'status'         => 'partially_paid',
                'invoice_date'   => $date->toDateString(),
                'due_date'       => $date->copy()->addDays(7)->toDateString(),
                'created_at'     => $date,
                'updated_at'     => $date,
            ]);

            Payment::create([
                'invoice_id'  => $dueInvoice->id,
                'order_id'    => $dueOrder->id,
                'customer_id' => $dueCustomer->id,
                'gateway'     => 'cash',
                'amount'      => $paid,
                'status'      => 'successful',
                'payment_date'=> $date,
                'created_at'  => $date,
                'updated_at'  => $date,
            ]);
        }

        // ─── 6. DAILY EXPENSES (30 days) ─────────────────────────────────
        $expenseCategories = [$bevCategory, $bakeryCategory, $foodCategory];
        $expenseTitles = [
            'Raw material restocking',
            'Vegetable & produce purchase',
            'Gas & fuel refill',
            'Staff overtime pay',
            'Kitchen cleaning supplies',
            'Electricity bill payment',
            'Packaging materials',
            'Soft drink crate purchase',
            'Chicken & meat supply',
            'Internet & phone bills',
        ];

        for ($daysAgo = 30; $daysAgo >= 0; $daysAgo--) {
            $date = Carbon::now()->subDays($daysAgo)->toDateString();
            $numExpenses = rand(1, 3);
            for ($e = 0; $e < $numExpenses; $e++) {
                $cat = $expenseCategories[array_rand($expenseCategories)];
                Expense::create([
                    'title'        => $expenseTitles[array_rand($expenseTitles)],
                    'category_id'  => $cat->id,
                    'description'  => 'Daily operational expense for ' . $date,
                    'amount'       => rand(200, 2500) * 1.0,
                    'expense_date' => $date,
                ]);
            }
        }

        $this->command->info('✅ RichDemoDataSeeder: Created 30+ days of orders, invoices, payments, expenses, tables and spaces.');
    }
}
