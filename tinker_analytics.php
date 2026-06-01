<?php
// tinker_analytics.php
use App\Services\AnalyticsService;
use App\Models\Tenant;

$tenant = Tenant::find('sajilo');
if (!$tenant) { echo "Tenant not found\n"; exit; }

$tenant->run(function () {
    $service = new AnalyticsService();
    
    echo "=== OVERVIEW ===\n";
    $overview = $service->getOverviewData();
    echo "today_sales: " . $overview['today_sales'] . "\n";
    echo "this_week_sales: " . $overview['this_week_sales'] . "\n";
    echo "this_month_sales: " . $overview['this_month_sales'] . "\n";
    echo "total_orders: " . $overview['total_orders'] . "\n";
    echo "net_revenue: " . $overview['net_revenue'] . "\n";
    
    echo "\n=== SALES TREND ===\n";
    $sales = $service->getSalesData(['period' => 'month', 'group_by' => 'day']);
    print_r($sales['sales_trend']);
    
    echo "\n=== PAYMENT BREAKDOWN ===\n";
    $payments = $service->getPaymentsData(['period' => 'month']);
    print_r($payments['payment_method_breakdown']);
    
    echo "\n=== TOP PRODUCTS ===\n";
    $products = $service->getProductsData();
    print_r($products['top_selling_products']);
    
    echo "\n=== DUE SUMMARY ===\n";
    $dues = $service->getDueSummaryData();
    echo "total_due_from_invoices: " . $dues['total_due_from_invoices'] . "\n";
    echo "overdue_invoices: " . $dues['overdue_invoices'] . "\n";
    
    echo "\n=== DAILY REPORT ===\n";
    $daily = $service->getDailyReportData();
    echo "total_sales: " . $daily['total_sales'] . "\n";
    echo "total_expenses: " . $daily['total_expenses'] . "\n";
});
