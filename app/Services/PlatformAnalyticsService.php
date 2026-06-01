<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Carbon\Carbon;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;

class PlatformAnalyticsService
{
    /**
     * Fetch central dashboard metrics for the platform.
     */
    public function getDashboardMetrics(): array
    {
        $tenants = Tenant::all();

        $active = 0;
        $inactive = 0;
        $suspended = 0;

        foreach ($tenants as $t) {
            $status = $t->status ?? 'active';
            if ($status === 'active') {
                $active++;
            } elseif ($status === 'inactive') {
                $inactive++;
            } elseif ($status === 'suspended') {
                $suspended++;
            }
        }

        $today = Carbon::today();
        $startOfWeek = Carbon::now()->startOfWeek();
        $startOfMonth = Carbon::now()->startOfMonth();

        $newToday = Tenant::where('created_at', '>=', $today)->count();
        $newWeek = Tenant::where('created_at', '>=', $startOfWeek)->count();
        $newMonth = Tenant::where('created_at', '>=', $startOfMonth)->count();

        $activeSubs = Subscription::where('status', 'active')->count();
        $trialSubs = Subscription::where('status', 'trialing')->count();
        $expiredSubs = Subscription::where('status', 'expired')->count();

        $recent = Tenant::latest()->take(5)->get();

        // Safe database health counts
        $healthyCount = 0;
        $offlineCount = 0;
        foreach ($tenants as $t) {
            try {
                tenancy()->initialize($t);
                // Ping a query to verify health
                User::first();
                $healthyCount++;
            } catch (\Exception $e) {
                $offlineCount++;
            } finally {
                try {
                    tenancy()->end();
                } catch (\Exception $e) {}
            }
        }

        return [
            'total_tenants' => $tenants->count(),
            'active_tenants' => $active,
            'inactive_tenants' => $inactive,
            'suspended_tenants' => $suspended,
            'new_tenants_today' => $newToday,
            'new_tenants_this_week' => $newWeek,
            'new_tenants_this_month' => $newMonth,
            'active_subscriptions' => $activeSubs,
            'trial_subscriptions' => $trialSubs,
            'expired_subscriptions' => $expiredSubs,
            'recent_tenants' => $recent,
            'platform_health_summary' => [
                'healthy_databases' => $healthyCount,
                'offline_databases' => $offlineCount,
            ]
        ];
    }

    /**
     * Safely aggregate usage metrics across all isolated tenant databases.
     */
    public function getUsageMetrics(): array
    {
        $tenants = Tenant::all();

        $usage = [
            'total_customers' => 0,
            'total_orders' => 0,
            'total_invoices' => 0,
            'total_payments' => 0.00,
            'total_staff' => 0,
            'tenant_details' => []
        ];

        $salesRank = [];
        $orderRank = [];
        $dueRank = [];

        foreach ($tenants as $t) {
            $tStats = [
                'tenant_id' => $t->id,
                'name' => $t->name,
                'database_healthy' => false,
                'customers' => 0,
                'orders' => 0,
                'sales' => 0.00,
                'invoices' => 0,
                'due' => 0.00,
                'payments' => 0.00,
                'staff' => 0,
            ];

            try {
                // Initialize dynamic tenant context
                tenancy()->initialize($t);

                $custCount = Customer::count();
                $ordCount = Order::count();
                $salesSum = (double) Order::where('status', 'completed')->sum('total');
                $invCount = Invoice::count();
                $dueSum = (double) Invoice::where('status', '!=', 'paid')->sum('due_amount');
                $paySum = (double) Payment::where('status', 'completed')->sum('amount');
                $staffCount = User::count();

                $tStats['customers'] = $custCount;
                $tStats['orders'] = $ordCount;
                $tStats['sales'] = $salesSum;
                $tStats['invoices'] = $invCount;
                $tStats['due'] = $dueSum;
                $tStats['payments'] = $paySum;
                $tStats['staff'] = $staffCount;
                $tStats['database_healthy'] = true;

                // Central accumulators
                $usage['total_customers'] += $custCount;
                $usage['total_orders'] += $ordCount;
                $usage['total_invoices'] += $invCount;
                $usage['total_payments'] += $paySum;
                $usage['total_staff'] += $staffCount;

                // Push rankings
                $salesRank[] = ['tenant_id' => $t->id, 'name' => $t->name, 'sales' => $salesSum];
                $orderRank[] = ['tenant_id' => $t->id, 'name' => $t->name, 'orders' => $ordCount];
                $dueRank[] = ['tenant_id' => $t->id, 'name' => $t->name, 'due' => $dueSum];

            } catch (\Exception $e) {
                $tStats['database_healthy'] = false;
                $tStats['database_error'] = $e->getMessage();
            } finally {
                try {
                    tenancy()->end();
                } catch (\Exception $e) {}
            }

            $usage['tenant_details'][] = $tStats;
        }

        // Sort rankings
        usort($salesRank, fn($a, $b) => $b['sales'] <=> $a['sales']);
        usort($orderRank, fn($a, $b) => $b['orders'] <=> $a['orders']);
        usort($dueRank, fn($a, $b) => $b['due'] <=> $a['due']);

        $usage['top_tenants_by_sales'] = array_slice($salesRank, 0, 5);
        $usage['top_tenants_by_orders'] = array_slice($orderRank, 0, 5);
        $usage['tenants_with_highest_due'] = array_slice($dueRank, 0, 5);

        return $usage;
    }
}
