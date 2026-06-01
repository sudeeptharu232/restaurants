<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\SubscriptionPlan;
use App\Models\Subscription;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GrowstroSuperAdminTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app('db')->setDefaultConnection('central');
        
        // Clean up data to avoid isolation leaks
        foreach (Tenant::all() as $t) {
            try { $t->delete(); } catch (\Exception $e) {}
        }
        
        foreach (Subscription::all() as $s) {
            try { $s->delete(); } catch (\Exception $e) {}
        }

        SubscriptionPlan::query()->forceDelete();
        User::where('role', 'super_admin')->delete();
        AuditLog::query()->delete();
    }

    /**
     * Helper to create a central Super Admin user.
     */
    protected function createSuperAdmin(bool $isActive = true): User
    {
        return User::create([
            'name' => 'Test Super Admin',
            'email' => 'admin-' . uniqid() . '@growstro.test',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'is_active' => $isActive,
        ]);
    }

    /**
     * Helper to create a plan.
     */
    protected function createPlan(string $name, float $price = 1000.00): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name),
            'description' => 'Test plan',
            'price' => $price,
            'billing_interval' => 'monthly',
            'duration_days' => 30,
            'max_staff' => 5,
            'max_products' => 100,
            'max_invoices_per_month' => 200,
            'whatsapp_reports_enabled' => true,
            'analytics_enabled' => true,
            'is_active' => true,
        ]);
    }

    /**
     * Helper to create a tenant.
     */
    protected function createTenantWorkspace(string $subdomain): array
    {
        $uniqueId = substr($subdomain . '-' . md5(uniqid((string)rand(), true)), 0, 30);

        $tenant = Tenant::create([
            'id'   => $uniqueId,
            'name' => ucfirst($uniqueId) . ' Store',
            'status' => 'active',
            'email' => $uniqueId . '@growstro.test',
            'phone' => '9800000000',
        ]);

        $tenant->domains()->create(['domain' => "{$uniqueId}.localhost"]);

        $owner = null;
        $token = null;

        $tenant->run(function () use ($uniqueId, &$owner, &$token) {
            $owner = User::create([
                'name'      => 'Tenant Owner',
                'email'     => "owner@{$uniqueId}.com",
                'password'  => Hash::make('password'),
                'role'      => 'owner',
                'is_active' => true,
            ]);

            $token = $owner->createToken('auth_token')->plainTextToken;
        });

        return [
            'tenant' => $tenant,
            'owner'  => $owner,
            'token'  => $token,
        ];
    }

    public function test_unauthenticated_cannot_access_dashboard(): void
    {
        $response = $this->getJson('/api/admin/dashboard');
        $response->assertStatus(401);
    }

    public function test_tenant_user_cannot_access_dashboard(): void
    {
        $tenantData = $this->createTenantWorkspace('sajilo');
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $tenantData['token'],
        ])->getJson('/api/admin/dashboard');

        $response->assertStatus(401);
    }

    public function test_inactive_super_admin_cannot_access_dashboard(): void
    {
        $admin = $this->createSuperAdmin(false);
        $token = $admin->createToken('admin_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/admin/dashboard');

        $response->assertStatus(403);
        $response->assertJsonFragment([
            'success' => false,
            'message' => 'Your user account is suspended or inactive',
        ]);
    }

    public function test_active_super_admin_can_access_dashboard_metrics(): void
    {
        $admin = $this->createSuperAdmin();
        $token = $admin->createToken('admin_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/admin/dashboard');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'total_tenants',
                'active_tenants',
                'inactive_tenants',
                'suspended_tenants',
                'new_tenants_today',
                'new_tenants_this_week',
                'new_tenants_this_month',
                'active_subscriptions',
                'trial_subscriptions',
                'expired_subscriptions',
                'recent_tenants',
                'platform_health_summary' => [
                    'healthy_databases',
                    'offline_databases',
                ]
            ]
        ]);
    }

    public function test_super_admin_can_create_and_list_tenants(): void
    {
        $admin = $this->createSuperAdmin();
        $token = $admin->createToken('admin_token')->plainTextToken;
        
        $plan = $this->createPlan('Free Trial', 0);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/admin/tenants', [
            'business_name' => 'Growstro Fast Food',
            'owner_name' => 'Hari Prasad',
            'email' => 'hari@growstro.test',
            'password' => 'password123',
            'phone' => '9841234567',
            'business_type' => 'restaurant',
            'address' => 'Kathmandu, Nepal',
            'is_vat_registered' => true,
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment([
            'success' => true,
            'message' => 'Tenant created successfully',
        ]);

        // Assert listing
        $listResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/admin/tenants');

        $listResponse->assertStatus(200);
        $this->assertCount(1, $listResponse->json('data.data'));
    }

    public function test_super_admin_can_suspend_and_restore_tenant(): void
    {
        $admin = $this->createSuperAdmin();
        $token = $admin->createToken('admin_token')->plainTextToken;

        $tenantData = $this->createTenantWorkspace('sajilo');
        $tenantId = $tenantData['tenant']->id;

        // Suspend
        $suspendResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/admin/tenants/{$tenantId}/suspend");

        $suspendResponse->assertStatus(200);
        $this->assertEquals('suspended', Tenant::find($tenantId)->status);

        // Assert that the suspended tenant's staff can no longer hit tenant endpoints
        $tenantResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $tenantData['token'],
        ])->getJson("/api/{$tenantId}/me");

        $tenantResponse->assertStatus(403);
        $tenantResponse->assertJsonFragment([
            'success' => false,
            'message' => 'This business account has been suspended',
        ]);

        // Restore
        $restoreResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/admin/tenants/{$tenantId}/restore");

        $restoreResponse->assertStatus(200);
        $this->assertEquals('active', Tenant::find($tenantId)->status);

        // Assert they can access again
        $tenantResponseAfter = $this->withHeaders([
            'Authorization' => 'Bearer ' . $tenantData['token'],
        ])->getJson("/api/{$tenantId}/me");

        $tenantResponseAfter->assertStatus(200);
        
        // Assert audit log was recorded
        $this->assertTrue(AuditLog::where('event', 'tenant.status_change')->exists());
    }

    public function test_super_admin_can_fetch_tenant_summary(): void
    {
        $admin = $this->createSuperAdmin();
        $token = $admin->createToken('admin_token')->plainTextToken;

        $tenantData = $this->createTenantWorkspace('testshop');
        $tenant = $tenantData['tenant'];

        // Seed some data inside tenant workspace
        $tenant->run(function () {
            Customer::create([
                'name' => 'Hari Bahadur',
                'phone' => '9840112233',
            ]);
            Order::create([
                'order_number' => 'ORD-101',
                'type' => 'dine_in',
                'status' => 'completed',
                'payment_status' => 'paid',
                'total' => 1500.00,
                'subtotal' => 1500.00,
            ]);
        });

        $summaryResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson("/api/admin/tenants/{$tenant->id}/summary");

        $summaryResponse->assertStatus(200);
        $summaryResponse->assertJsonFragment([
            'database_healthy' => true,
            'total_customers' => 1,
            'total_orders' => 1,
            'total_sales' => 1500.00,
        ]);
    }

    public function test_tenant_summary_graceful_missing_db_failover(): void
    {
        $admin = $this->createSuperAdmin();
        $token = $admin->createToken('admin_token')->plainTextToken;

        // Create tenant model but DO NOT run migrations (or drop DB manually)
        $tenant = Tenant::create([
            'id' => 'brokendb',
            'name' => 'Broken DB Tenant',
        ]);
        
        // Drop the actual database directly to simulate a corrupt/missing DB
        try {
            \Illuminate\Support\Facades\DB::statement("DROP DATABASE IF EXISTS tenantbrokendb");
        } catch (\Exception $e) {}

        $summaryResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson("/api/admin/tenants/{$tenant->id}/summary");

        $summaryResponse->assertStatus(200);
        $summaryResponse->assertJsonFragment([
            'database_healthy' => false,
        ]);
        
        // Clean up
        try {
            $tenant->delete();
        } catch (\Exception $e) {}
    }

    public function test_subscription_plan_crud_and_safety_checks(): void
    {
        $admin = $this->createSuperAdmin();
        $token = $admin->createToken('admin_token')->plainTextToken;

        // 1. Create plan
        $planResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/admin/subscription-plans', [
            'name' => 'Premium Plan',
            'price' => 3000.00,
            'billing_interval' => 'monthly',
            'duration_days' => 30,
            'max_staff' => 20,
            'max_products' => 1000,
            'max_invoices_per_month' => 5000,
            'whatsapp_reports_enabled' => true,
            'analytics_enabled' => true,
        ]);

        $planResponse->assertStatus(201);
        $planId = $planResponse->json('data.id');

        // 2. Assign plan to tenant
        $tenantData = $this->createTenantWorkspace('sajilo');
        $tenantId = $tenantData['tenant']->id;

        $assignResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/admin/tenants/{$tenantId}/subscriptions", [
            'subscription_plan_id' => $planId,
        ]);

        $assignResponse->assertStatus(201);
        $this->assertTrue(Subscription::where('tenant_id', $tenantId)->where('subscription_plan_id', $planId)->exists());

        // 3. Attempting to delete plan with active subscriptions must fail
        $deleteResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->deleteJson("/api/admin/subscription-plans/{$planId}");

        $deleteResponse->assertStatus(409); // Conflict status due to active subscriptions
    }

    public function test_platform_analytics_aggregates_usage_and_handles_failures(): void
    {
        $admin = $this->createSuperAdmin();
        $token = $admin->createToken('admin_token')->plainTextToken;

        // Tenant 1
        $tenantData1 = $this->createTenantWorkspace('t1');
        $tenantData1['tenant']->run(function () {
            Customer::create(['name' => 'C1', 'phone' => '9800000001']);
        });

        // Tenant 2
        $tenantData2 = $this->createTenantWorkspace('t2');
        $tenantData2['tenant']->run(function () {
            Customer::create(['name' => 'C2', 'phone' => '9800000002']);
        });

        // Broken Tenant DB
        $tenant3 = Tenant::create([
            'id' => 't3offline',
            'name' => 'Offline Tenant',
        ]);
        try {
            \Illuminate\Support\Facades\DB::statement("DROP DATABASE IF EXISTS tenantt3offline");
        } catch (\Exception $e) {}

        // Fetch aggregates
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/admin/platform-analytics/usage');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'total_customers' => 2,
        ]);
        
        $details = $response->json('data.tenant_details');
        $this->assertCount(3, $details);
        
        // Assert that the offline tenant is marked healthy = false but did not crash the response!
        $offlineDetail = collect($details)->firstWhere('tenant_id', 't3offline');
        $this->assertFalse($offlineDetail['database_healthy']);

        // Clean up offline tenant
        try {
            $tenant3->delete();
        } catch (\Exception $e) {}
    }
}
