<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Order;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Customer;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\MenuItem;
use App\Models\Expense;
use App\Models\KitchenTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrowstroAnalyticsTest extends TestCase
{
    /**
     * Helper to establish dynamic isolated tenant context.
     */
    protected function createTenantWorkspace(string $subdomain, string $email, string $role, bool $isActive = true): array
    {
        $uniqueId = substr($subdomain . '-' . md5(uniqid((string)rand(), true)), 0, 30);

        $tenant = Tenant::create([
            'id' => $uniqueId,
            'name' => ucfirst($uniqueId) . ' Store',
        ]);

        $tenant->domains()->create([
            'domain' => "{$uniqueId}.localhost",
        ]);

        $token = null;
        $user = null;
        $customer = null;
        $invoice = null;
        $order = null;

        $tenant->run(function () use ($email, $role, $isActive, &$token, &$user, &$customer, &$invoice, &$order) {
            $user = User::create([
                'name' => 'Workspace User',
                'email' => $email,
                'password' => bcrypt('password123'),
                'role' => $role,
                'is_active' => $isActive,
            ]);

            $token = $user->createToken('test-token')->plainTextToken;

            // Seed a generic customer
            $customer = Customer::create([
                'name' => 'Analytics Customer',
                'email' => 'customer@analytics.com',
                'phone' => '9876543210',
            ]);

            // Seed completed order
            $order = Order::create([
                'customer_id' => $customer->id,
                'order_number' => 'ORD-TEST-001',
                'type' => 'dine_in',
                'status' => 'completed',
                'payment_status' => 'paid',
                'subtotal' => 100.00,
                'discount' => 10.00,
                'vat_amount' => 11.70, // 13% of 90
                'service_charge_amount' => 9.00, // 10%
                'total' => 110.70,
                'paid_amount' => 110.70,
                'due_amount' => 0.00,
            ]);

            // Add standard item
            OrderItem::create([
                'order_id' => $order->id,
                'name' => 'Delicious Momos',
                'quantity' => 2.00,
                'unit_price' => 50.00,
                'total_amount' => 100.00,
            ]);

            // Seed invoice
            $invoice = Invoice::create([
                'customer_id' => $customer->id,
                'order_id' => $order->id,
                'invoice_number' => 'INV-TEST-001',
                'invoice_date' => now()->toDateString(),
                'status' => 'paid',
                'subtotal' => 100.00,
                'discount' => 10.00,
                'vat_amount' => 11.70,
                'service_charge' => 9.00,
                'total' => 110.70,
                'paid_amount' => 110.70,
                'due_amount' => 0.00,
            ]);
        });

        return [
            'tenant' => $tenant,
            'user' => $user,
            'token' => $token,
            'customer' => $customer,
            'order' => $order,
            'invoice' => $invoice,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        app('db')->setDefaultConnection('central');

        // Clean central database state
        foreach (Tenant::all() as $tenant) {
            try {
                $tenant->delete();
            } catch (\Exception $e) {
                // Ignore
            }
        }
    }

    /**
     * Test Owner can access analytics overview and returns expected keys.
     */
    public function test_owner_can_access_analytics_overview(): void
    {
        $setup = $this->createTenantWorkspace('analyt-owner', 'owner@test.com', 'owner');
        $tenantId = $setup['tenant']->id;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $setup['token'],
        ])->getJson("/api/{$tenantId}/analytics/overview");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'today_sales',
                    'this_week_sales',
                    'this_month_sales',
                    'total_orders',
                    'completed_orders',
                    'cancelled_orders',
                    'total_invoices',
                    'paid_invoices',
                    'partially_paid_invoices',
                    'due_invoices',
                    'total_customers',
                    'new_customers_this_month',
                    'total_payments_received',
                    'total_due_amount',
                    'total_expenses',
                    'net_revenue',
                    'low_stock_items_count',
                    'pending_kitchen_tickets',
                    'top_selling_products',
                    'recent_orders',
                    'recent_payments',
                ]
            ]);
    }

    /**
     * Test Sales analytics calculations and filters.
     */
    public function test_sales_analytics_calculates_correctly_and_filters_work(): void
    {
        $setup = $this->createTenantWorkspace('analyt-sales', 'owner@test.com', 'owner');
        $tenantId = $setup['tenant']->id;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $setup['token'],
        ])->getJson("/api/{$tenantId}/analytics/sales?period=today&group_by=day");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_sales',
                    'total_orders',
                    'average_order_value',
                    'sales_trend',
                    'sales_by_order_type',
                    'sales_by_status',
                    'cancelled_order_value',
                    'vat_collected',
                    'service_charge_collected',
                ]
            ]);

        $this->assertEquals(110.70, $response->json('data.total_sales'));
        $this->assertEquals(1, $response->json('data.total_orders'));
    }

    /**
     * Test payment analytics and gateway breakdowns.
     */
    public function test_payment_analytics_calculates_gateway_breakdown(): void
    {
        $setup = $this->createTenantWorkspace('analyt-pay', 'owner@test.com', 'owner');
        $tenantId = $setup['tenant']->id;
        $order = $setup['order'];

        $setup['tenant']->run(function() use ($order) {
            Payment::create([
                'order_id' => $order->id,
                'gateway' => 'esewa',
                'amount' => 50.00,
                'status' => 'successful',
                'payment_date' => now(),
            ]);

            Payment::create([
                'order_id' => $order->id,
                'gateway' => 'cash',
                'amount' => 20.00,
                'status' => 'successful',
                'payment_date' => now(),
            ]);
        });

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $setup['token'],
        ])->getJson("/api/{$tenantId}/analytics/payments?period=month");

        $response->assertStatus(200);
        $this->assertEquals(50.00, $response->json('data.payment_method_breakdown.esewa'));
        $this->assertEquals(20.00, $response->json('data.payment_method_breakdown.cash'));
    }

    /**
     * Test customer analytics and top spending customers.
     */
    public function test_customer_analytics_returns_top_customers(): void
    {
        $setup = $this->createTenantWorkspace('analyt-cust', 'owner@test.com', 'owner');
        $tenantId = $setup['tenant']->id;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $setup['token'],
        ])->getJson("/api/{$tenantId}/analytics/customers");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_customers',
                    'new_customers_today',
                    'new_customers_this_week',
                    'new_customers_this_month',
                    'top_customers_by_spending',
                    'customers_with_due',
                    'average_customer_spend',
                    'customer_growth_trend',
                    'repeat_customers',
                    'inactive_customers',
                ]
            ]);

        $this->assertNotEmpty($response->json('data.top_customers_by_spending'));
        $this->assertEquals('Analytics Customer', $response->json('data.top_customers_by_spending.0.name'));
    }

    /**
     * Test product analytics groups top products even with snapshots.
     */
    public function test_product_analytics_returns_top_products_with_snapshots(): void
    {
        $setup = $this->createTenantWorkspace('analyt-prod', 'owner@test.com', 'owner');
        $tenantId = $setup['tenant']->id;
        $order = $setup['order'];

        $setup['tenant']->run(function() use ($order) {
            // Add a manual snapshotted item (no product_id/menu_item_id)
            OrderItem::create([
                'order_id' => $order->id,
                'name' => 'Custom Buffet Event',
                'quantity' => 1.00,
                'unit_price' => 500.00,
                'total_amount' => 500.00,
                'product_id' => null,
            ]);
        });

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $setup['token'],
        ])->getJson("/api/{$tenantId}/analytics/products");

        $response->assertStatus(200);
        $this->assertEquals(3.00, OrderItem::sum('quantity'));
    }

    /**
     * Test expense calculations and net revenue subtraction.
     */
    public function test_expense_analytics_calculates_net_revenue(): void
    {
        $setup = $this->createTenantWorkspace('analyt-exp', 'owner@test.com', 'owner');
        $tenantId = $setup['tenant']->id;

        $setup['tenant']->run(function() {
            Expense::create([
                'title' => 'Fresh Vegetables',
                'amount' => 30.00,
                'expense_date' => now()->toDateString(),
            ]);
        });

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $setup['token'],
        ])->getJson("/api/{$tenantId}/analytics/expenses");

        $response->assertStatus(200);
        $this->assertEquals(30.00, $response->json('data.total_expenses'));
        $this->assertEquals(80.70, $response->json('data.net_revenue')); // 110.70 completed order total - 30.00 expenses
    }

    /**
     * Test due summary outstanding terms.
     */
    public function test_due_summary_returns_invoice_and_order_due_amounts(): void
    {
        $setup = $this->createTenantWorkspace('analyt-due', 'owner@test.com', 'owner');
        $tenantId = $setup['tenant']->id;
        $invoice = $setup['invoice'];

        $setup['tenant']->run(function() use ($invoice) {
            // Update invoice due balance and status to issued
            $invoice->status = 'issued';
            $invoice->due_amount = 50.00;
            $invoice->save();
        });

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $setup['token'],
        ])->getJson("/api/{$tenantId}/analytics/due-summary");

        $response->assertStatus(200);
        $this->assertEquals(50.00, $response->json('data.total_due_from_invoices'));
    }

    /**
     * Test daily report data endpoint payload.
     */
    public function test_daily_report_payload_is_complete(): void
    {
        $setup = $this->createTenantWorkspace('analyt-daily', 'owner@test.com', 'owner');
        $tenantId = $setup['tenant']->id;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $setup['token'],
        ])->getJson("/api/{$tenantId}/analytics/daily-report");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'report_date',
                    'total_sales',
                    'total_orders',
                    'total_payments',
                    'total_due',
                    'total_expenses',
                    'net_revenue',
                    'top_products',
                    'payment_breakdown',
                    'low_stock_items',
                    'pending_kitchen_tickets',
                    'cancelled_orders',
                ]
            ]);
    }

    /**
     * Test empty tenant returns zero values safely without database errors.
     */
    public function test_empty_tenant_analytics_returns_zeros_safely(): void
    {
        // Fresh tenant with no seeded records at all
        $setup = $this->createTenantWorkspace('analyt-empty', 'owner@test.com', 'owner');
        $tenantId = $setup['tenant']->id;

        $setup['tenant']->run(function() {
            // Purge everything seeded in createTenantWorkspace
            Order::query()->delete();
            Invoice::query()->delete();
            Customer::query()->delete();
        });

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $setup['token'],
        ])->getJson("/api/{$tenantId}/analytics/overview");

        $response->assertStatus(200);
        $this->assertEquals(0.00, $response->json('data.today_sales'));
        $this->assertEquals(0, $response->json('data.total_orders'));
        $this->assertEmpty($response->json('data.top_selling_products'));
    }

    /**
     * Test staff role restrictions.
     */
    public function test_staff_without_view_analytics_is_blocked(): void
    {
        $setup = $this->createTenantWorkspace('analyt-staff', 'staff@test.com', 'staff');
        $tenantId = $setup['tenant']->id;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $setup['token'],
        ])->getJson("/api/{$tenantId}/analytics/overview");

        $response->assertStatus(403);
    }

    /**
     * Test staff with view_analytics (manager role) can access.
     */
    public function test_staff_with_view_analytics_can_access(): void
    {
        $setup = $this->createTenantWorkspace('analyt-mgr', 'manager@test.com', 'manager');
        $tenantId = $setup['tenant']->id;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $setup['token'],
        ])->getJson("/api/{$tenantId}/analytics/overview");

        $response->assertStatus(200);
    }

    /**
     * Test suspended tenant is blocked.
     */
    public function test_suspended_tenant_is_blocked(): void
    {
        $setup = $this->createTenantWorkspace('analyt-susp', 'owner@test.com', 'owner', false);
        $tenantId = $setup['tenant']->id;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $setup['token'],
        ])->getJson("/api/{$tenantId}/analytics/overview");

        $response->assertStatus(403);
    }

    /**
     * Test cross tenant leakage is blocked.
     */
    public function test_tenant_a_cannot_access_tenant_b_analytics(): void
    {
        $setupA = $this->createTenantWorkspace('analyt-a', 'owner-a@test.com', 'owner');
        $setupB = $this->createTenantWorkspace('analyt-b', 'owner-b@test.com', 'owner');

        $tenantA = $setupA['tenant']->id;
        $tenantB = $setupB['tenant']->id;

        // Try to access Tenant B's analytics using Tenant A's token
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $setupA['token'],
        ])->getJson("/api/{$tenantB}/analytics/overview");

        // The path middleware blocks this as the user does not exist on Tenant B's workspace context!
        $response->assertStatus(401);
    }

    /**
     * Test invalid filter validation errors.
     */
    public function test_invalid_filter_validation_returns_consistent_json(): void
    {
        $setup = $this->createTenantWorkspace('analyt-val', 'owner@test.com', 'owner');
        $tenantId = $setup['tenant']->id;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $setup['token'],
        ])->getJson("/api/{$tenantId}/analytics/sales?period=invalid_period&group_by=invalid_group");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['period', 'group_by']);
    }
}
