<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Customer;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\MenuItem;
use App\Models\Expense;
use App\Models\KitchenTicket;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /**
     * Resolve start and end datetimes in Asia/Kathmandu timezone.
     */
    public function resolveDateRange(array $filters): array
    {
        $timezone = new \DateTimeZone('Asia/Kathmandu');
        $now = new \DateTime('now', $timezone);

        $period = $filters['period'] ?? 'month';
        $dateFrom = null;
        $dateTo = null;

        if ($period === 'today') {
            $dateFrom = (clone $now)->setTime(0, 0, 0)->format('Y-m-d H:i:s');
            $dateTo = (clone $now)->setTime(23, 59, 59)->format('Y-m-d H:i:s');
        } elseif ($period === 'week') {
            $startOfWeek = clone $now;
            // Monday is start of ISO week
            $startOfWeek->setISODate((int)$now->format('o'), (int)$now->format('W'), 1)->setTime(0, 0, 0);
            $endOfWeek = clone $startOfWeek;
            $endOfWeek->modify('+6 days')->setTime(23, 59, 59);

            $dateFrom = $startOfWeek->format('Y-m-d H:i:s');
            $dateTo = $endOfWeek->format('Y-m-d H:i:s');
        } elseif ($period === 'month') {
            $dateFrom = (clone $now)->modify('first day of this month')->setTime(0, 0, 0)->format('Y-m-d H:i:s');
            $dateTo = (clone $now)->modify('last day of this month')->setTime(23, 59, 59)->format('Y-m-d H:i:s');
        } elseif ($period === 'year') {
            $dateFrom = (clone $now)->setDate((int)$now->format('Y'), 1, 1)->setTime(0, 0, 0)->format('Y-m-d H:i:s');
            $dateTo = (clone $now)->setDate((int)$now->format('Y'), 12, 31)->setTime(23, 59, 59)->format('Y-m-d H:i:s');
        } elseif ($period === 'custom') {
            $dateFrom = isset($filters['date_from']) 
                ? Carbon::parse($filters['date_from'])->setTimezone('Asia/Kathmandu')->startOfDay()->toDateTimeString() 
                : (clone $now)->modify('first day of this month')->setTime(0, 0, 0)->format('Y-m-d H:i:s');
            $dateTo = isset($filters['date_to']) 
                ? Carbon::parse($filters['date_to'])->setTimezone('Asia/Kathmandu')->endOfDay()->toDateTimeString() 
                : (clone $now)->setTime(23, 59, 59)->format('Y-m-d H:i:s');
        }

        // Explicit overrides if passed
        if (isset($filters['date_from']) && $period !== 'custom') {
            $dateFrom = Carbon::parse($filters['date_from'])->setTimezone('Asia/Kathmandu')->startOfDay()->toDateTimeString();
        }
        if (isset($filters['date_to']) && $period !== 'custom') {
            $dateTo = Carbon::parse($filters['date_to'])->setTimezone('Asia/Kathmandu')->endOfDay()->toDateTimeString();
        }

        return [$dateFrom, $dateTo];
    }

    /**
     * Executive Overview Dashboard Data
     */
    public function getOverviewData(): array
    {
        $timezone = new \DateTimeZone('Asia/Kathmandu');
        $now = new \DateTime('now', $timezone);

        $todayStart = (clone $now)->setTime(0, 0, 0)->format('Y-m-d H:i:s');
        $todayEnd = (clone $now)->setTime(23, 59, 59)->format('Y-m-d H:i:s');

        $weekStart = clone $now;
        $weekStart->setISODate((int)$now->format('o'), (int)$now->format('W'), 1)->setTime(0, 0, 0);
        $weekEnd = clone $weekStart;
        $weekEnd->modify('+6 days')->setTime(23, 59, 59);

        $monthStart = (clone $now)->modify('first day of this month')->setTime(0, 0, 0)->format('Y-m-d H:i:s');
        $monthEnd = (clone $now)->modify('last day of this month')->setTime(23, 59, 59)->format('Y-m-d H:i:s');

        $weekStartString = $weekStart->format('Y-m-d H:i:s');
        $weekEndString = $weekEnd->format('Y-m-d H:i:s');
        $monthStartDate = Carbon::parse($monthStart)->toDateString();
        $monthEndDate = Carbon::parse($monthEnd)->toDateString();

        $orderStats = Order::query()
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'completed') as completed_orders")
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'cancelled') as cancelled_orders")
            ->selectRaw("COALESCE(SUM(total) FILTER (WHERE status = 'completed' AND created_at BETWEEN ? AND ?), 0) as today_sales", [$todayStart, $todayEnd])
            ->selectRaw("COALESCE(SUM(total) FILTER (WHERE status = 'completed' AND created_at BETWEEN ? AND ?), 0) as week_sales", [$weekStartString, $weekEndString])
            ->selectRaw("COALESCE(SUM(total) FILTER (WHERE status = 'completed' AND created_at BETWEEN ? AND ?), 0) as month_sales", [$monthStart, $monthEnd])
            ->first();

        $todaySales = (float) $orderStats->today_sales;
        $weekSales = (float) $orderStats->week_sales;
        $monthSales = (float) $orderStats->month_sales;
        $totalOrders = (int) $orderStats->total_orders;
        $completedOrders = (int) $orderStats->completed_orders;
        $cancelledOrders = (int) $orderStats->cancelled_orders;

        $invoiceStats = Invoice::query()
            ->selectRaw('COUNT(*) as total_invoices')
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'paid') as paid_invoices")
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'partially_paid') as partially_paid_invoices")
            ->selectRaw('COUNT(*) FILTER (WHERE due_amount > 0) as due_invoices')
            ->selectRaw("COALESCE(SUM(due_amount) FILTER (WHERE status IN ('draft', 'issued', 'partially_paid', 'unpaid')), 0) as total_due_amount")
            ->first();

        $totalInvoices = (int) $invoiceStats->total_invoices;
        $paidInvoices = (int) $invoiceStats->paid_invoices;
        $partiallyPaidInvoices = (int) $invoiceStats->partially_paid_invoices;
        $dueInvoices = (int) $invoiceStats->due_invoices;
        $totalDueAmount = (float) $invoiceStats->total_due_amount;

        $customerStats = Customer::query()
            ->selectRaw('COUNT(*) as total_customers')
            ->selectRaw('COUNT(*) FILTER (WHERE created_at BETWEEN ? AND ?) as new_customers_this_month', [$monthStart, $monthEnd])
            ->first();

        $totalCustomers = (int) $customerStats->total_customers;
        $newCustomersThisMonth = (int) $customerStats->new_customers_this_month;

        $totalPaymentsReceived = (float) Payment::where('status', 'successful')
            ->whereBetween('payment_date', [$monthStart, $monthEnd])
            ->sum('amount');

        $totalExpenses = (float) Expense::whereBetween('expense_date', [$monthStartDate, $monthEndDate])
            ->sum('amount');

        $netRevenue = round($monthSales - $totalExpenses, 2);

        $lowStockItemsCount = Product::where('track_stock', true)
            ->where('stock_quantity', '<=', 5.00)
            ->count();

        $pendingKitchenTickets = KitchenTicket::whereIn('status', ['pending', 'preparing'])->count();

        $topSellingProducts = OrderItem::select('name')
            ->selectRaw('SUM(quantity) as total_quantity')
            ->selectRaw('SUM(total_amount) as total_revenue')
            ->whereNotNull('product_id')
            ->groupBy('name')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get()
            ->toArray();

        $recentOrders = Order::with('customer:id,name,phone')
            ->select(['id', 'customer_id', 'restaurant_table_id', 'order_number', 'type', 'status', 'payment_status', 'kitchen_status', 'total', 'created_at'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->toArray();

        $recentPayments = Payment::with([
                'invoice:id,invoice_number,status,total,due_amount',
                'order:id,order_number,status,total,due_amount',
                'customer:id,name,phone',
            ])
            ->select(['id', 'invoice_id', 'order_id', 'customer_id', 'amount', 'gateway', 'transaction_id', 'status', 'payment_date', 'created_at'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->toArray();

        return [
            'today_sales' => $todaySales,
            'this_week_sales' => $weekSales,
            'this_month_sales' => $monthSales,
            'total_orders' => $totalOrders,
            'completed_orders' => $completedOrders,
            'cancelled_orders' => $cancelledOrders,
            'total_invoices' => $totalInvoices,
            'paid_invoices' => $paidInvoices,
            'partially_paid_invoices' => $partiallyPaidInvoices,
            'due_invoices' => $dueInvoices,
            'total_customers' => $totalCustomers,
            'new_customers_this_month' => $newCustomersThisMonth,
            'total_payments_received' => $totalPaymentsReceived,
            'total_due_amount' => $totalDueAmount,
            'total_expenses' => $totalExpenses,
            'net_revenue' => $netRevenue,
            'low_stock_items_count' => $lowStockItemsCount,
            'pending_kitchen_tickets' => $pendingKitchenTickets,
            'top_selling_products' => $topSellingProducts,
            'recent_orders' => $recentOrders,
            'recent_payments' => $recentPayments,
        ];
    }

    /**
     * Sales Report and Trend Analytics
     */
    public function getSalesData(array $filters): array
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($filters);

        $totalSales = (float) Order::where('status', 'completed')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->sum('total');

        $totalOrders = Order::where('status', 'completed')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->count();

        $averageOrderValue = $totalOrders > 0 ? round($totalSales / $totalOrders, 2) : 0.00;

        // Sales Trend (PostgreSQL-optimized using date_trunc)
        $groupBy = $filters['group_by'] ?? 'day';
        $truncFormat = $groupBy === 'month' ? 'month' : ($groupBy === 'week' ? 'week' : 'day');

        $salesTrend = Order::where('status', 'completed')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw("DATE_TRUNC(?, created_at) as period_start", [$truncFormat])
            ->selectRaw("SUM(total) as sales")
            ->selectRaw("COUNT(*) as orders")
            ->groupBy('period_start')
            ->orderBy('period_start', 'asc')
            ->get()
            ->map(function ($row) {
                return [
                    'period' => Carbon::parse($row->period_start)->toDateString(),
                    'sales' => (float)$row->sales,
                    'orders' => (int)$row->orders
                ];
            })
            ->toArray();

        // Sales by Order Type
        $salesByOrderType = Order::where('status', 'completed')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw('type, SUM(total) as total_sales, COUNT(*) as count')
            ->groupBy('type')
            ->get()
            ->mapWithKeys(function ($row) {
                return [$row->type => [
                    'sales' => (float)$row->total_sales,
                    'count' => (int)$row->count
                ]];
            })
            ->toArray();

        // Standardize sales_by_order_type keys
        $defaultTypes = ['dine_in', 'delivery', 'takeaway', 'pickup', 'reservation', 'qr_menu', 'regular'];
        foreach ($defaultTypes as $type) {
            if (!isset($salesByOrderType[$type])) {
                $salesByOrderType[$type] = ['sales' => 0.00, 'count' => 0];
            }
        }

        // Sales by Status
        $salesByStatus = Order::whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw('status, SUM(total) as sales, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(function ($row) {
                return [$row->status => [
                    'sales' => (float)$row->sales,
                    'count' => (int)$row->count
                ]];
            })
            ->toArray();

        $cancelledOrderValue = (float) Order::where('status', 'cancelled')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->sum('total');

        $vatCollected = (float) Order::where('status', 'completed')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->sum('vat_amount');

        $serviceChargeCollected = (float) Order::where('status', 'completed')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->sum('service_charge_amount');

        return [
            'total_sales' => $totalSales,
            'total_orders' => $totalOrders,
            'average_order_value' => $averageOrderValue,
            'sales_trend' => $salesTrend,
            'sales_by_order_type' => $salesByOrderType,
            'sales_by_status' => $salesByStatus,
            'cancelled_order_value' => $cancelledOrderValue,
            'vat_collected' => $vatCollected,
            'service_charge_collected' => $serviceChargeCollected,
        ];
    }

    /**
     * Payments Analytics
     */
    public function getPaymentsData(array $filters): array
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($filters);

        $query = Payment::whereBetween('payment_date', [$dateFrom, $dateTo]);
        if (isset($filters['gateway'])) {
            $query->where('gateway', $filters['gateway']);
        }
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $totalPayments = (float) (clone $query)->where('status', 'successful')->sum('amount');
        $successfulPayments = (clone $query)->where('status', 'successful')->count();
        $pendingPayments = (clone $query)->where('status', 'pending')->count();
        $failedPayments = (clone $query)->where('status', 'failed')->count();
        $refundedPayments = (clone $query)->where('status', 'refunded')->count();

        // Method Breakdown
        $gateways = ['cash', 'bank', 'qr', 'credit', 'esewa', 'khalti', 'fonepay'];
        $paymentMethodBreakdown = [];
        foreach ($gateways as $gateway) {
            $paymentMethodBreakdown[$gateway] = (float) Payment::where('status', 'successful')
                ->where('gateway', $gateway)
                ->whereBetween('payment_date', [$dateFrom, $dateTo])
                ->sum('amount');
        }

        // Payment Trend
        $paymentTrend = Payment::where('status', 'successful')
            ->whereBetween('payment_date', [$dateFrom, $dateTo])
            ->selectRaw("DATE_TRUNC('day', payment_date) as period_start")
            ->selectRaw("SUM(amount) as payments")
            ->groupBy('period_start')
            ->orderBy('period_start', 'asc')
            ->get()
            ->map(function ($row) {
                return [
                    'period' => Carbon::parse($row->period_start)->toDateString(),
                    'amount' => (float)$row->payments
                ];
            })
            ->toArray();

        $averagePaymentAmount = $successfulPayments > 0 ? round($totalPayments / $successfulPayments, 2) : 0.00;
        $largestPayment = (float) Payment::where('status', 'successful')
            ->whereBetween('payment_date', [$dateFrom, $dateTo])
            ->max('amount');

        $recentPayments = Payment::with(['invoice', 'order', 'customer'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->toArray();

        return [
            'total_payments' => $totalPayments,
            'successful_payments' => $successfulPayments,
            'pending_payments' => $pendingPayments,
            'failed_payments' => $failedPayments,
            'refunded_payments' => $refundedPayments,
            'payment_method_breakdown' => $paymentMethodBreakdown,
            'payment_trend' => $paymentTrend,
            'average_payment_amount' => $averagePaymentAmount,
            'largest_payment' => $largestPayment,
            'recent_payments' => $recentPayments,
        ];
    }

    /**
     * Customer cohort analysis & demographics
     */
    public function getCustomersData(): array
    {
        $timezone = new \DateTimeZone('Asia/Kathmandu');
        $now = new \DateTime('now', $timezone);

        $todayStart = (clone $now)->setTime(0, 0, 0)->format('Y-m-d H:i:s');
        $todayEnd = (clone $now)->setTime(23, 59, 59)->format('Y-m-d H:i:s');

        $weekStart = clone $now;
        $weekStart->setISODate((int)$now->format('o'), (int)$now->format('W'), 1)->setTime(0, 0, 0);
        $weekEnd = clone $weekStart;
        $weekEnd->modify('+6 days')->setTime(23, 59, 59);

        $monthStart = (clone $now)->modify('first day of this month')->setTime(0, 0, 0)->format('Y-m-d H:i:s');
        $monthEnd = (clone $now)->modify('last day of this month')->setTime(23, 59, 59)->format('Y-m-d H:i:s');

        $totalCustomers = Customer::count();
        $newCustomersToday = Customer::whereBetween('created_at', [$todayStart, $todayEnd])->count();
        $newCustomersThisWeek = Customer::whereBetween('created_at', [$weekStart, $weekEnd])->count();
        $newCustomersThisMonth = Customer::whereBetween('created_at', [$monthStart, $monthEnd])->count();

        // Top Customers by spending
        $topCustomersBySpending = Customer::select('customers.id', 'customers.name', 'customers.phone')
            ->join('orders', 'orders.customer_id', '=', 'customers.id')
            ->where('orders.status', 'completed')
            ->selectRaw('SUM(orders.total) as total_spent')
            ->groupBy('customers.id', 'customers.name', 'customers.phone')
            ->orderByDesc('total_spent')
            ->limit(5)
            ->get()
            ->toArray();

        // Customers with due
        $customersWithDue = Customer::select('customers.id', 'customers.name', 'customers.phone')
            ->join('invoices', 'invoices.customer_id', '=', 'customers.id')
            ->where('invoices.due_amount', '>', 0.00)
            ->selectRaw('SUM(invoices.due_amount) as total_due')
            ->groupBy('customers.id', 'customers.name', 'customers.phone')
            ->orderByDesc('total_due')
            ->limit(5)
            ->get()
            ->toArray();

        // Average Customer spend
        $spendingCustomersCount = Customer::whereHas('orders', function ($q) {
            $q->where('status', 'completed');
        })->count();

        $totalCompletedSales = (float) Order::where('status', 'completed')->sum('total');
        $averageCustomerSpend = $spendingCustomersCount > 0 ? round($totalCompletedSales / $spendingCustomersCount, 2) : 0.00;

        // Customer Growth Trend
        $customerGrowthTrend = Customer::selectRaw("DATE_TRUNC('month', created_at) as month_start")
            ->selectRaw("COUNT(*) as count")
            ->groupBy('month_start')
            ->orderBy('month_start', 'asc')
            ->get()
            ->map(function ($row) {
                return [
                    'period' => Carbon::parse($row->month_start)->toDateString(),
                    'count' => (int)$row->count
                ];
            })
            ->toArray();

        // Repeat customers (more than 1 completed order)
        $repeatCustomers = Customer::whereHas('orders', function ($q) {
            $q->where('status', 'completed');
        }, '>', 1)->count();

        // Inactive customers (no orders in the last 30 days)
        $inactiveCustomers = Customer::whereDoesntHave('orders', function ($q) {
            $q->where('created_at', '>=', now()->subDays(30));
        })->count();

        return [
            'total_customers' => $totalCustomers,
            'new_customers_today' => $newCustomersToday,
            'new_customers_this_week' => $newCustomersThisWeek,
            'new_customers_this_month' => $newCustomersThisMonth,
            'top_customers_by_spending' => $topCustomersBySpending,
            'customers_with_due' => $customersWithDue,
            'average_customer_spend' => $averageCustomerSpend,
            'customer_growth_trend' => $customerGrowthTrend,
            'repeat_customers' => $repeatCustomers,
            'inactive_customers' => $inactiveCustomers,
        ];
    }

    /**
     * Product and Menu Popularity Analytics
     */
    public function getProductsData(): array
    {
        // Top selling products (from order items product_id)
        $topSellingProducts = OrderItem::select('name')
            ->selectRaw('SUM(quantity) as total_quantity')
            ->selectRaw('SUM(total_amount) as total_revenue')
            ->whereNotNull('product_id')
            ->groupBy('name')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get()
            ->toArray();

        // Top selling menu items (from order items menu_item_id)
        $topSellingMenuItems = OrderItem::select('name')
            ->selectRaw('SUM(quantity) as total_quantity')
            ->selectRaw('SUM(total_amount) as total_revenue')
            ->whereNotNull('menu_item_id')
            ->groupBy('name')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get()
            ->toArray();

        // Top selling services (from order items service_id)
        $topSellingServices = OrderItem::select('name')
            ->selectRaw('SUM(quantity) as total_quantity')
            ->selectRaw('SUM(total_amount) as total_revenue')
            ->whereNotNull('service_id')
            ->groupBy('name')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get()
            ->toArray();

        // Lowest selling products
        $lowestSellingProducts = OrderItem::select('name')
            ->selectRaw('SUM(quantity) as total_quantity')
            ->selectRaw('SUM(total_amount) as total_revenue')
            ->whereNotNull('product_id')
            ->groupBy('name')
            ->orderBy('total_quantity', 'asc')
            ->limit(5)
            ->get()
            ->toArray();

        $quantitySold = (float) OrderItem::sum('quantity');

        // Revenue by Product snapshot
        $revenueByProduct = OrderItem::select('name')
            ->selectRaw('SUM(total_amount) as total_revenue')
            ->whereNotNull('product_id')
            ->groupBy('name')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get()
            ->toArray();

        // Revenue by Category (joins products/menu items categories)
        $revenueByCategory = OrderItem::select('categories.name as category_name')
            ->selectRaw('SUM(order_items.total_amount) as revenue')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('menu_items', 'menu_items.id', '=', 'order_items.menu_item_id')
            ->leftJoin('categories', function ($join) {
                $join->on('categories.id', '=', 'products.category_id')
                     ->orOn('categories.id', '=', 'menu_items.category_id');
            })
            ->whereNotNull('categories.name')
            ->groupBy('categories.name')
            ->get()
            ->map(function($row) {
                return [
                    'category_name' => $row->category_name,
                    'revenue' => (float)$row->revenue
                ];
            })
            ->toArray();

        // Low stock products
        $lowStockProducts = Product::where('track_stock', true)
            ->where('stock_quantity', '<=', 5.00)
            ->get()
            ->toArray();

        // Unavailable menu items
        $unavailableMenuItems = MenuItem::where('is_available', false)
            ->get()
            ->toArray();

        // Product Sales Trend
        $productSalesTrend = OrderItem::select('name')
            ->selectRaw("DATE_TRUNC('month', order_items.created_at) as month_start")
            ->selectRaw('SUM(quantity) as quantity')
            ->groupBy('name', 'month_start')
            ->orderBy('month_start', 'asc')
            ->limit(10)
            ->get()
            ->toArray();

        return [
            'top_selling_products' => $topSellingProducts,
            'top_selling_menu_items' => $topSellingMenuItems,
            'top_selling_services' => $topSellingServices,
            'lowest_selling_products' => $lowestSellingProducts,
            'quantity_sold' => $quantitySold,
            'revenue_by_product' => $revenueByProduct,
            'revenue_by_category' => $revenueByCategory,
            'low_stock_products' => $lowStockProducts,
            'unavailable_menu_items' => $unavailableMenuItems,
            'product_sales_trend' => $productSalesTrend,
        ];
    }

    /**
     * Expense Analytics
     */
    public function getExpensesData(): array
    {
        $timezone = new \DateTimeZone('Asia/Kathmandu');
        $now = new \DateTime('now', $timezone);

        $today = $now->format('Y-m-d');
        $monthStart = (clone $now)->modify('first day of this month')->format('Y-m-d');
        $monthEnd = (clone $now)->modify('last day of this month')->format('Y-m-d');

        $totalExpenses = (float) Expense::sum('amount');
        $expensesToday = (float) Expense::where('expense_date', $today)->sum('amount');
        $expensesThisMonth = (float) Expense::whereBetween('expense_date', [$monthStart, $monthEnd])->sum('amount');

        // Grouped by Category
        $expensesByCategory = Expense::select('categories.name as category_name')
            ->selectRaw('SUM(expenses.amount) as total_amount')
            ->leftJoin('categories', 'categories.id', '=', 'expenses.category_id')
            ->groupBy('categories.name')
            ->get()
            ->map(function($row) {
                return [
                    'category_name' => $row->category_name ?? 'Uncategorized',
                    'total_amount' => (float)$row->total_amount
                ];
            })
            ->toArray();

        // Grouped by Payment Method (assumed cash default as per migration limits)
        $expensesByPaymentMethod = [
            'cash' => $totalExpenses,
            'bank' => 0.00,
            'qr' => 0.00,
            'credit' => 0.00
        ];

        // Trend
        $expenseTrend = Expense::selectRaw("DATE_TRUNC('day', expense_date) as day_start")
            ->selectRaw('SUM(amount) as amount')
            ->groupBy('day_start')
            ->orderBy('day_start', 'asc')
            ->get()
            ->map(function ($row) {
                return [
                    'period' => Carbon::parse($row->day_start)->toDateString(),
                    'amount' => (float)$row->amount
                ];
            })
            ->toArray();

        $totalSales = (float) Order::where('status', 'completed')->sum('total');
        $netRevenue = round($totalSales - $totalExpenses, 2);

        return [
            'total_expenses' => $totalExpenses,
            'expenses_today' => $expensesToday,
            'expenses_this_month' => $expensesThisMonth,
            'expenses_by_category' => $expensesByCategory,
            'expenses_by_payment_method' => $expensesByPaymentMethod,
            'expense_trend' => $expenseTrend,
            'net_revenue' => $netRevenue,
        ];
    }

    /**
     * Outstanding Receivables Due Summary
     */
    public function getDueSummaryData(): array
    {
        $totalDueFromInvoices = (float) Invoice::whereIn('status', ['draft', 'issued', 'partially_paid', 'unpaid'])->sum('due_amount');
        $totalDueFromOrders = (float) Order::whereIn('status', ['completed', 'pending', 'partially_paid'])->sum('due_amount');

        // Overdue invoices (draft, issued, partially paid)
        $overdueInvoices = Invoice::whereIn('status', ['issued', 'partially_paid', 'unpaid'])
            ->where('due_amount', '>', 0.00)
            ->count();

        // Due by Customer
        $dueByCustomer = Customer::select('customers.id', 'customers.name', 'customers.phone')
            ->join('invoices', 'invoices.customer_id', '=', 'customers.id')
            ->where('invoices.due_amount', '>', 0.00)
            ->selectRaw('SUM(invoices.due_amount) as total_due')
            ->groupBy('customers.id', 'customers.name', 'customers.phone')
            ->get()
            ->toArray();

        // Largest due customers
        $largestDueCustomers = Customer::select('customers.id', 'customers.name', 'customers.phone')
            ->join('invoices', 'invoices.customer_id', '=', 'customers.id')
            ->where('invoices.due_amount', '>', 0.00)
            ->selectRaw('SUM(invoices.due_amount) as total_due')
            ->groupBy('customers.id', 'customers.name', 'customers.phone')
            ->orderByDesc('total_due')
            ->limit(5)
            ->get()
            ->toArray();

        // Due trend (Outstanding dues grouped by month)
        $dueTrend = Invoice::whereIn('status', ['issued', 'partially_paid', 'unpaid'])
            ->selectRaw("DATE_TRUNC('month', invoice_date) as month_start")
            ->selectRaw('SUM(due_amount) as due')
            ->groupBy('month_start')
            ->orderBy('month_start', 'asc')
            ->get()
            ->map(function ($row) {
                return [
                    'period' => Carbon::parse($row->month_start)->toDateString(),
                    'due' => (float)$row->due
                ];
            })
            ->toArray();

        $partiallyPaidInvoices = Invoice::where('status', 'partially_paid')->count();
        $unpaidInvoices = Invoice::where('status', 'unpaid')->orWhere(function($q) {
            $q->where('due_amount', '>', 0.00)->where('paid_amount', 0.00);
        })->count();

        return [
            'total_due_from_invoices' => $totalDueFromInvoices,
            'total_due_from_orders' => $totalDueFromOrders,
            'overdue_invoices' => $overdueInvoices,
            'due_by_customer' => $dueByCustomer,
            'largest_due_customers' => $largestDueCustomers,
            'due_trend' => $dueTrend,
            'partially_paid_invoices' => $partiallyPaidInvoices,
            'unpaid_invoices' => $unpaidInvoices,
        ];
    }

    /**
     * Daily Report Data
     */
    public function getDailyReportData(): array
    {
        $timezone = new \DateTimeZone('Asia/Kathmandu');
        $now = new \DateTime('now', $timezone);

        $todayStart = (clone $now)->setTime(0, 0, 0)->format('Y-m-d H:i:s');
        $todayEnd = (clone $now)->setTime(23, 59, 59)->format('Y-m-d H:i:s');

        $totalSales = (float) Order::where('status', 'completed')
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->sum('total');

        $totalOrders = Order::where('status', 'completed')
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->count();

        $totalPayments = (float) Payment::where('status', 'successful')
            ->whereBetween('payment_date', [$todayStart, $todayEnd])
            ->sum('amount');

        $totalDue = (float) Invoice::whereBetween('created_at', [$todayStart, $todayEnd])->sum('due_amount');

        $totalExpenses = (float) Expense::where('expense_date', $now->format('Y-m-d'))->sum('amount');

        $netRevenue = round($totalSales - $totalExpenses, 2);

        // Top products today
        $topProducts = OrderItem::select('name')
            ->selectRaw('SUM(quantity) as total_quantity')
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->groupBy('name')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get()
            ->toArray();

        // Payment gateway breakdown today
        $gateways = ['cash', 'bank', 'qr', 'credit', 'esewa', 'khalti', 'fonepay'];
        $paymentBreakdown = [];
        foreach ($gateways as $gateway) {
            $paymentBreakdown[$gateway] = (float) Payment::where('status', 'successful')
                ->where('gateway', $gateway)
                ->whereBetween('payment_date', [$todayStart, $todayEnd])
                ->sum('amount');
        }

        $lowStockItems = Product::where('track_stock', true)
            ->where('stock_quantity', '<=', 5.00)
            ->count();

        $pendingKitchenTickets = KitchenTicket::whereIn('status', ['pending', 'preparing'])->count();

        $cancelledOrders = Order::where('status', 'cancelled')
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->count();

        return [
            'report_date' => $now->format('Y-m-d'),
            'total_sales' => $totalSales,
            'total_orders' => $totalOrders,
            'total_payments' => $totalPayments,
            'total_due' => $totalDue,
            'total_expenses' => $totalExpenses,
            'net_revenue' => $netRevenue,
            'top_products' => $topProducts,
            'payment_breakdown' => $paymentBreakdown,
            'low_stock_items' => $lowStockItems,
            'pending_kitchen_tickets' => $pendingKitchenTickets,
            'cancelled_orders' => $cancelledOrders,
        ];
    }

    /**
     * Daily Report Data for a specific date (used by DailyReportService).
     */
    public function getDailyReportDataForDate(string $date): array
    {
        $timezone = new \DateTimeZone('Asia/Kathmandu');
        $carbon = \Carbon\Carbon::createFromFormat('Y-m-d', $date, $timezone);

        $dayStart = $carbon->copy()->startOfDay()->format('Y-m-d H:i:s');
        $dayEnd   = $carbon->copy()->endOfDay()->format('Y-m-d H:i:s');

        $totalSales = (float) Order::where('status', 'completed')
            ->whereBetween('created_at', [$dayStart, $dayEnd])
            ->sum('total');

        $totalOrders = Order::where('status', 'completed')
            ->whereBetween('created_at', [$dayStart, $dayEnd])
            ->count();

        $totalPayments = (float) Payment::where('status', 'successful')
            ->whereBetween('payment_date', [$dayStart, $dayEnd])
            ->sum('amount');

        $totalDue = (float) Invoice::whereBetween('created_at', [$dayStart, $dayEnd])->sum('due_amount');

        $totalExpenses = (float) Expense::where('expense_date', $date)->sum('amount');

        $netRevenue = round($totalSales - $totalExpenses, 2);

        // Top products for the day (from order item snapshots)
        $topProducts = OrderItem::select('name')
            ->selectRaw('SUM(quantity) as total_quantity')
            ->selectRaw('SUM(total_amount) as total_revenue')
            ->whereBetween('created_at', [$dayStart, $dayEnd])
            ->groupBy('name')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get()
            ->toArray();

        // Low stock items (current snapshot, not date-specific)
        $lowStockItems = Product::where('track_stock', true)
            ->where('stock_quantity', '<=', 5.00)
            ->select('id', 'name', 'stock_quantity')
            ->get()
            ->toArray();

        return [
            'report_date'    => $date,
            'total_sales'    => $totalSales,
            'total_orders'   => $totalOrders,
            'total_payments' => $totalPayments,
            'total_due'      => $totalDue,
            'total_expenses' => $totalExpenses,
            'net_revenue'    => $netRevenue,
            'top_products'   => $topProducts,
            'low_stock_items'=> $lowStockItems,
        ];
    }
}
