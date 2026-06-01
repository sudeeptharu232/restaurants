<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tenant;
use App\Models\SubscriptionPlan;
use App\Models\Subscription;
use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use App\Models\MenuItem;
use App\Models\RestaurantSpace;
use App\Models\RestaurantTable;
use App\Models\Customer;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GrowstroTenantCrudTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 1. Force central database connection at setup start
        app('db')->setDefaultConnection('central');

        if (tenancy()->initialized) {
            tenancy()->end();
        }

        // 2. Clean central database state
        foreach (Tenant::all() as $tenant) {
            try {
                $tenant->delete();
            } catch (\Exception $e) {
                // Ignore
            }
        }

        User::query()->delete();
        BusinessSetting::query()->delete();
        Subscription::query()->delete();
        SubscriptionPlan::withTrashed()->forceDelete();

        // 3. Clean up tenancy states completely
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        app('db')->purge('tenant');
        app('db')->setDefaultConnection('central');

        // 4. Provision subscription plan centrally
        SubscriptionPlan::updateOrCreate(
            ['slug' => 'basic-plan'],
            [
                'name' => 'Basic Plan',
                'description' => 'Standard Growstro access tier',
                'price' => 1500.00,
                'billing_interval' => 'monthly',
                'features' => ['billing', 'inventory', 'reports'],
                'is_active' => true,
            ]
        );
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        foreach (Tenant::all() as $tenant) {
            if (str_starts_with($tenant->id, 't-') || str_starts_with($tenant->id, 'manual-')) {
                try {
                    $tenant->delete();
                } catch (\Exception $e) {
                    // Ignore
                }
            }
        }

        app('db')->purge('tenant');
        app('db')->setDefaultConnection('central');

        parent::tearDown();
    }

    /**
     * Helper to provision a tenant business along with its owner credentials.
     */
    protected function setupTenantBusiness(string $prefix, string $email = 'owner@test.com'): array
    {
        // Must run centrally to create tenant
        app('db')->setDefaultConnection('central');

        $tenantId = 't-' . $prefix . '-' . bin2hex(random_bytes(4));
        $tenant = Tenant::create([
            'id' => $tenantId,
            'name' => 'Tenant ' . $prefix,
        ]);

        Subscription::create([
            'tenant_id' => $tenantId,
            'subscription_plan_id' => SubscriptionPlan::first()->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $ownerToken = null;
        $tenant->run(function () use ($email, &$ownerToken) {
            $owner = User::create([
                'name' => 'Owner ' . $email,
                'email' => $email,
                'password' => Hash::make('password123'),
                'role' => 'owner',
                'is_active' => true,
            ]);

            $ownerToken = $owner->createToken('auth_token')->plainTextToken;
        });

        // Ensure we end tenancy resolution at the end of setup helper
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        app('db')->setDefaultConnection('central');

        return [
            'tenant_id' => $tenantId,
            'tenant' => $tenant,
            'token' => $ownerToken,
        ];
    }

    /**
     * Test full Customer CRUD operations by the owner.
     */
    public function test_owner_can_perform_customer_crud(): void
    {
        $setup = $this->setupTenantBusiness('cust');
        $tenantId = $setup['tenant_id'];
        $token = $setup['token'];

        // 1. Create Customer
        $response = $this->postJson("/api/{$tenantId}/customers", [
            'name' => 'Hari Bahadur',
            'phone' => '9841234567',
            'email' => 'hari@bahadur.com',
            'address' => 'Kathmandu, Nepal',
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(201)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.name', 'Hari Bahadur');

        $customerId = $response->json('data.id');

        // 2. Index Customers
        $response = $this->getJson("/api/{$tenantId}/customers", ['Authorization' => 'Bearer ' . $token]);
        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonStructure(['success', 'message', 'data' => ['data']]);

        // 3. Search Customers
        $response = $this->getJson("/api/{$tenantId}/customers?search=Hari", ['Authorization' => 'Bearer ' . $token]);
        $response->assertStatus(200)
                 ->assertJsonPath('success', true);

        // 4. Update Customer
        $response = $this->putJson("/api/{$tenantId}/customers/{$customerId}", [
            'name' => 'Hari Bahadur Updated',
            'phone' => '9841234567', // keep same unique phone
            'email' => 'hari-updated@bahadur.com',
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.name', 'Hari Bahadur Updated');

        // 5. Delete Customer
        $response = $this->deleteJson("/api/{$tenantId}/customers/{$customerId}", [], ['Authorization' => 'Bearer ' . $token]);
        $response->assertStatus(200)
                 ->assertJsonPath('success', true);
    }

    /**
     * Test full Category, Product, Service, MenuItem, Space, and Table creations.
     */
    public function test_owner_can_create_all_isolated_resources(): void
    {
        $setup = $this->setupTenantBusiness('res');
        $tenantId = $setup['tenant_id'];
        $token = $setup['token'];

        // 1. Create Product Category
        $response = $this->postJson("/api/{$tenantId}/categories", [
            'name' => 'Drinks',
            'slug' => 'drinks',
            'type' => 'product',
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(201)->assertJsonPath('success', true);
        $prodCatId = $response->json('data.id');

        // 2. Create Product
        $response = $this->postJson("/api/{$tenantId}/products", [
            'category_id' => $prodCatId,
            'name' => 'Coca Cola',
            'sku' => 'COKE-250',
            'price' => 80.00,
            'cost_price' => 60.00,
            'stock_quantity' => 100,
            'track_stock' => true,
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(201)->assertJsonPath('success', true);

        // 3. Create Service Category
        $response = $this->postJson("/api/{$tenantId}/categories", [
            'name' => 'Spa Treatments',
            'slug' => 'spa-treatments',
            'type' => 'service',
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(201)->assertJsonPath('success', true);
        $srvCatId = $response->json('data.id');

        // 4. Create Service
        $response = $this->postJson("/api/{$tenantId}/services", [
            'category_id' => $srvCatId,
            'name' => 'Massage',
            'duration_minutes' => 60,
            'price' => 2000.00,
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(201)->assertJsonPath('success', true);

        // 5. Create Menu Category
        $response = $this->postJson("/api/{$tenantId}/categories", [
            'name' => 'Momo Items',
            'slug' => 'momo-items',
            'type' => 'menu',
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(201)->assertJsonPath('success', true);
        $menuCatId = $response->json('data.id');

        // 6. Create Menu Item
        $response = $this->postJson("/api/{$tenantId}/menu-items", [
            'category_id' => $menuCatId,
            'name' => 'Buff Momo',
            'description' => 'Buff momo steamed',
            'price' => 150.00,
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(201)->assertJsonPath('success', true);

        // 7. Create Restaurant Space
        $response = $this->postJson("/api/{$tenantId}/spaces", [
            'name' => 'First Floor',
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(201)->assertJsonPath('success', true);
        $spaceId = $response->json('data.id');

        // 8. Create Restaurant Table
        $response = $this->postJson("/api/{$tenantId}/tables", [
            'restaurant_space_id' => $spaceId,
            'table_number' => 'T1',
            'capacity' => 4,
            'status' => 'vacant',
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(201)->assertJsonPath('success', true);
    }

    /**
     * Test staff permission handling.
     */
    public function test_staff_permissions_access_control(): void
    {
        $setup = $this->setupTenantBusiness('perm');
        $tenantId = $setup['tenant_id'];
        $tenant = $setup['tenant'];

        $permittedToken = null;
        $unpermittedToken = null;

        // Provision staff users inside the tenant connection scope
        $tenant->run(function () use (&$permittedToken, &$unpermittedToken) {
            // Manager role has manage_customers permission
            $manager = User::create([
                'name' => 'Manager Sita',
                'email' => 'sita@test.com',
                'password' => Hash::make('password123'),
                'role' => 'manager',
                'is_active' => true,
            ]);
            $permittedToken = $manager->createToken('auth_token')->plainTextToken;

            // Staff role does not have manage_customers permission (only view)
            $staff = User::create([
                'name' => 'Staff Gita',
                'email' => 'gita@test.com',
                'password' => Hash::make('password123'),
                'role' => 'staff',
                'is_active' => true,
            ]);
            $unpermittedToken = $staff->createToken('auth_token')->plainTextToken;
        });

        // 1. Manager with permission creates customer -> Succeeds
        $response = $this->postJson("/api/{$tenantId}/customers", [
            'name' => 'Customer A',
            'phone' => '9800000001',
        ], ['Authorization' => 'Bearer ' . $permittedToken]);

        $response->assertStatus(201);

        // Clear the authenticated user cache in memory for the next request in the same process thread
        auth()->forgetUser();

        // 2. Staff without write permission creates customer -> Forbidden (403)
        $response = $this->postJson("/api/{$tenantId}/customers", [
            'name' => 'Customer B',
            'phone' => '9800000002',
        ], ['Authorization' => 'Bearer ' . $unpermittedToken]);

        $response->assertStatus(403)
                 ->assertJsonPath('success', false);
    }

    /**
     * Test Tenant A cannot access Tenant B data.
     */
    public function test_tenant_a_cannot_access_tenant_b_resources(): void
    {
        // Setup Tenant A
        $setupA = $this->setupTenantBusiness('tna', 'ownerA@test.com');
        $tenantAId = $setupA['tenant_id'];
        $tokenA = $setupA['token'];

        // Setup Tenant B
        $setupB = $this->setupTenantBusiness('tnb', 'ownerB@test.com');
        $tenantBId = $setupB['tenant_id'];
        $tokenB = $setupB['token'];

        // Tenant B creates a customer
        $customerId = null;
        $setupB['tenant']->run(function () use (&$customerId) {
            $cust = Customer::create([
                'name' => 'Isolated Cust',
                'phone' => '9811111111',
            ]);
            $customerId = $cust->id;
        });

        // Tenant A owner attempts to access Tenant B customer list using Tenant A credentials -> Unauthenticated (401)
        // This is because Sanctum tokens are isolated in their respective tenant databases!
        $response = $this->getJson("/api/{$tenantBId}/customers", ['Authorization' => 'Bearer ' . $tokenA]);
        $response->assertStatus(401);
    }

    /**
     * Test suspended tenant cannot access CRUD routes.
     */
    public function test_suspended_tenant_is_blocked(): void
    {
        $setup = $this->setupTenantBusiness('susp');
        $tenantId = $setup['tenant_id'];
        $token = $setup['token'];

        // Suspend the tenant centrally
        app('db')->setDefaultConnection('central');
        $tenant = Tenant::find($tenantId);
        $tenant->status = 'suspended';
        $tenant->save();

        // Attempt CRUD route
        $response = $this->getJson("/api/{$tenantId}/customers", ['Authorization' => 'Bearer ' . $token]);
        $response->assertStatus(403)
                 ->assertJsonPath('success', false)
                 ->assertJsonPath('message', 'This business account has been suspended');
    }

    /**
     * Test guest unauthenticated requests fail with 401.
     */
    public function test_unauthenticated_request_fails(): void
    {
        $setup = $this->setupTenantBusiness('unauth');
        $tenantId = $setup['tenant_id'];

        $response = $this->getJson("/api/{$tenantId}/customers");
        $response->assertStatus(401);
    }

    /**
     * Test validation validation formatting.
     */
    public function test_validation_errors_return_consistent_json(): void
    {
        $setup = $this->setupTenantBusiness('val');
        $tenantId = $setup['tenant_id'];
        $token = $setup['token'];

        $response = $this->postJson("/api/{$tenantId}/customers", [
            'name' => '', // missing name
            'phone' => '', // missing phone
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(422)
                 ->assertJsonPath('success', false)
                 ->assertJsonStructure(['success', 'message', 'errors']);
    }
}
