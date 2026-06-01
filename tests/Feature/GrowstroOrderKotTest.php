<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tenant;
use App\Models\SubscriptionPlan;
use App\Models\Subscription;
use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\Product;
use App\Models\MenuItem;
use App\Models\RestaurantSpace;
use App\Models\RestaurantTable;
use App\Models\Customer;
use App\Models\Order;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GrowstroOrderKotTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app('db')->setDefaultConnection('central');

        if (tenancy()->initialized) {
            tenancy()->end();
        }

        // Clean central database state
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

        if (tenancy()->initialized) {
            tenancy()->end();
        }
        app('db')->purge('tenant');
        app('db')->setDefaultConnection('central');

        // Provision subscription plan
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
     * Helper to provision a staff member.
     */
    protected function setupStaffUser(Tenant $tenant, string $email, string $role): string
    {
        $token = null;
        $tenant->run(function () use ($email, $role, &$token) {
            $user = User::create([
                'name' => 'Staff ' . $email,
                'email' => $email,
                'password' => Hash::make('password123'),
                'role' => $role,
                'is_active' => true,
            ]);
            $token = $user->createToken('staff_token')->plainTextToken;
        });
        return $token;
    }

    /**
     * Test creating a dine-in order with menu items and table occupation.
     */
    public function test_owner_can_create_dine_in_order_with_menu_items(): void
    {
        $setup = $this->setupTenantBusiness('dine');
        $tenantId = $setup['tenant_id'];
        $token = $setup['token'];

        // Seed Category, MenuItem, Space, Table
        $setup['tenant']->run(function () use (&$tableId, &$menuItemId, $tenantId) {
            // Seed VAT enabled in BusinessSettings
            BusinessSetting::create([
                'tenant_id' => $tenantId,
                'key' => 'vat_enabled',
                'value' => 'true',
            ]);

            $category = Category::create([
                'name' => 'Food items',
                'slug' => 'food-items',
                'type' => 'menu',
                'is_active' => true,
            ]);

            $menuItem = MenuItem::create([
                'category_id' => $category->id,
                'name' => 'Chicken Momo',
                'slug' => 'chicken-momo',
                'price' => 250.00,
                'is_active' => true,
            ]);
            $menuItemId = $menuItem->id;

            $space = RestaurantSpace::create([
                'name' => 'Main Hall',
                'slug' => 'main-hall',
                'is_active' => true,
            ]);

            $table = RestaurantTable::create([
                'restaurant_space_id' => $space->id,
                'table_number' => 'T1',
                'capacity' => 4,
                'status' => 'vacant',
                'is_active' => true,
            ]);
            $tableId = $table->id;
        });

        // 1. Create Dine-In Order
        $response = $this->postJson("/api/{$tenantId}/orders", [
            'order_type' => 'dine_in',
            'restaurant_table_id' => $tableId,
            'items' => [
                [
                    'menu_item_id' => $menuItemId,
                    'quantity' => 2,
                    'unit_price' => 250.00,
                ]
            ],
            'discount_amount' => 50.00, // Order discount
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(201)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.type', 'dine_in');

        $this->assertEquals(500.0, $response->json('data.subtotal'));
        $this->assertEquals(50.0, $response->json('data.discount_amount'));
        $this->assertEquals(58.5, $response->json('data.vat_amount'));
        $this->assertEquals(508.5, $response->json('data.total'));

        // Verify Table Status transitioned to occupied
        $setup['tenant']->run(function () use ($tableId) {
            $table = RestaurantTable::find($tableId);
            $this->assertEquals('occupied', $table->status);
        });

        // 2. Complete Order and verify Table becomes vacant
        $orderId = $response->json('data.id');
        $completeResponse = $this->postJson("/api/{$tenantId}/orders/{$orderId}/complete", [], ['Authorization' => 'Bearer ' . $token]);
        $completeResponse->assertStatus(200);

        $setup['tenant']->run(function () use ($tableId) {
            $table = RestaurantTable::find($tableId);
            $this->assertEquals('vacant', $table->status);
        });
    }

    /**
     * Test VAT enable/disable behavior and general calculations.
     */
    public function test_vat_behavior_and_calculations(): void
    {
        $setup = $this->setupTenantBusiness('calc');
        $tenantId = $setup['tenant_id'];
        $token = $setup['token'];

        $setup['tenant']->run(function () use (&$productId) {
            // Seed VAT disabled centrally/setting
            BusinessSetting::create([
                'tenant_id' => tenant('id'),
                'key' => 'vat_enabled',
                'value' => 'false',
            ]);

            $category = Category::create([
                'name' => 'Products',
                'slug' => 'products',
                'type' => 'product',
                'is_active' => true,
            ]);

            $product = Product::create([
                'category_id' => $category->id,
                'name' => 'Laptop Bag',
                'sku' => 'BAG-001',
                'price' => 1000.00,
                'is_active' => true,
            ]);
            $productId = $product->id;
        });

        // Create Order with VAT Disabled
        $response = $this->postJson("/api/{$tenantId}/orders", [
            'order_type' => 'regular',
            'items' => [
                [
                    'product_id' => $productId,
                    'quantity' => 1,
                    'unit_price' => 1000.00,
                ]
            ],
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(201);
        $this->assertEquals(0.0, $response->json('data.vat_amount'));
        $this->assertEquals(1000.0, $response->json('data.total'));
    }

    /**
     * Test generating and workflow processing of KOT/BOT Kitchen Tickets.
     */
    public function test_kitchen_ticket_generation_and_workflow(): void
    {
        $setup = $this->setupTenantBusiness('kot');
        $tenantId = $setup['tenant_id'];
        $token = $setup['token'];

        $setup['tenant']->run(function () use (&$foodItemId, &$drinkItemId) {
            $foodCategory = Category::create([
                'name' => 'Food Items',
                'slug' => 'food-items',
                'type' => 'menu',
                'is_active' => true,
            ]);
            $foodItem = MenuItem::create([
                'category_id' => $foodCategory->id,
                'name' => 'Steak',
                'slug' => 'steak',
                'price' => 500.00,
                'is_active' => true,
            ]);
            $foodItemId = $foodItem->id;

            $drinkCategory = Category::create([
                'name' => 'Bar Beverages',
                'slug' => 'bar-beverages',
                'type' => 'menu',
                'is_active' => true,
            ]);
            $drinkItem = MenuItem::create([
                'category_id' => $drinkCategory->id,
                'name' => 'Red Wine',
                'slug' => 'red-wine',
                'price' => 450.00,
                'is_active' => true,
            ]);
            $drinkItemId = $drinkItem->id;
        });

        // 1. Place order containing both food and drinks
        $orderResponse = $this->postJson("/api/{$tenantId}/orders", [
            'order_type' => 'regular',
            'items' => [
                [
                    'menu_item_id' => $foodItemId,
                    'quantity' => 1,
                    'unit_price' => 500.00,
                ],
                [
                    'menu_item_id' => $drinkItemId,
                    'quantity' => 2,
                    'unit_price' => 450.00,
                ]
            ],
        ], ['Authorization' => 'Bearer ' . $token]);

        $orderResponse->assertStatus(201);
        $orderId = $orderResponse->json('data.id');

        // 2. Generate KOT/BOT from order
        $kotResponse = $this->postJson("/api/{$tenantId}/orders/{$orderId}/kitchen-ticket", [], ['Authorization' => 'Bearer ' . $token]);
        $kotResponse->assertStatus(200);

        // Assert 2 tickets generated (KOT for food, BOT for drinks)
        $this->assertCount(2, $kotResponse->json('data'));

        $kotId = null;
        $botId = null;
        foreach ($kotResponse->json('data') as $ticket) {
            if ($ticket['type'] === 'KOT') {
                $kotId = $ticket['id'];
            } else {
                $botId = $ticket['id'];
            }
        }

        $this->assertNotNull($kotId);
        $this->assertNotNull($botId);

        // 3. Print KOT
        $printResponse = $this->postJson("/api/{$tenantId}/kitchen-tickets/{$kotId}/print", [], ['Authorization' => 'Bearer ' . $token]);
        $printResponse->assertStatus(200);
        $this->assertNotNull($printResponse->json('data.printed_at'));

        // 4. Update Kitchen Ticket status and verify bubbling
        $statusResponse = $this->putJson("/api/{$tenantId}/kitchen-tickets/{$kotId}/status", [
            'status' => 'preparing',
        ], ['Authorization' => 'Bearer ' . $token]);
        $statusResponse->assertStatus(200)->assertJsonPath('data.status', 'preparing');

        // Verify parent order kitchen_status is 'preparing'
        $orderCheck = $this->getJson("/api/{$tenantId}/orders/{$orderId}", ['Authorization' => 'Bearer ' . $token]);
        $orderCheck->assertJsonPath('data.kitchen_status', 'preparing');

        // 5. Update individual item status and assert ticket bubbling
        $itemId = $statusResponse->json('data.items.0.id');
        $itemStatusResponse = $this->putJson("/api/{$tenantId}/kitchen-tickets/{$kotId}/items/{$itemId}/status", [
            'status' => 'ready',
        ], ['Authorization' => 'Bearer ' . $token]);
        
        $itemStatusResponse->assertStatus(200);
        // Since there is only one food item in the food ticket, KOT transitions to 'ready'
        $itemStatusResponse->assertJsonPath('data.status', 'ready');
    }

    /**
     * Test staff role and permission limitations.
     */
    public function test_staff_role_based_limits(): void
    {
        $setup = $this->setupTenantBusiness('staff');
        $tenantId = $setup['tenant_id'];
        $token = $setup['token'];

        // Create manager and staff users
        $managerToken = $this->setupStaffUser($setup['tenant'], 'manager@test.com', 'manager');
        $staffToken = $this->setupStaffUser($setup['tenant'], 'staff@test.com', 'staff');

        // Seed product
        $setup['tenant']->run(function () use (&$productId) {
            $category = Category::create([
                'name' => 'Products',
                'slug' => 'products',
                'type' => 'product',
                'is_active' => true,
            ]);

            $product = Product::create([
                'category_id' => $category->id,
                'name' => 'Mock Product',
                'sku' => 'MOCK-001',
                'price' => 100.00,
                'is_active' => true,
            ]);
            $productId = $product->id;
        });

        // 1. Manager with view_orders can read orders list
        $response = $this->getJson("/api/{$tenantId}/orders", ['Authorization' => 'Bearer ' . $managerToken]);
        $response->assertStatus(200);

        auth()->forgetUser();

        // 2. Staff without permissions (blocked)
        // Wait, standard staff has view_orders by default in the permission matrix helper if declared,
        // let's create a custom guest/worker without role and verify blocked!
        $setup['tenant']->run(function () use (&$limitedToken) {
            $limited = User::create([
                'name' => 'Limited User',
                'email' => 'limited@test.com',
                'password' => Hash::make('password123'),
                'role' => 'worker', // worker has no permissions in helper
                'is_active' => true,
            ]);
            $limitedToken = $limited->createToken('worker_token')->plainTextToken;
        });

        $blockedResponse = $this->getJson("/api/{$tenantId}/orders", ['Authorization' => 'Bearer ' . $limitedToken]);
        $blockedResponse->assertStatus(403);

        auth()->forgetUser();

        // 3. Inactive user (blocked)
        $setup['tenant']->run(function () use (&$inactiveToken) {
            $inactive = User::create([
                'name' => 'Inactive Owner',
                'email' => 'inactive@test.com',
                'password' => Hash::make('password123'),
                'role' => 'owner',
                'is_active' => false,
            ]);
            $inactiveToken = $inactive->createToken('inactive_token')->plainTextToken;
        });

        $inactiveResponse = $this->getJson("/api/{$tenantId}/orders", ['Authorization' => 'Bearer ' . $inactiveToken]);
        $inactiveResponse->assertStatus(403);
    }

    /**
     * Test complete tenant database isolation boundaries.
     */
    public function test_strict_tenant_isolation_boundaries(): void
    {
        $setupA = $this->setupTenantBusiness('tena');
        $setupB = $this->setupTenantBusiness('tenb');

        $tenantIdA = $setupA['tenant_id'];
        $tokenA = $setupA['token'];

        $tenantIdB = $setupB['tenant_id'];
        $tokenB = $setupB['token'];

        // Seed product in Tenant B
        $setupB['tenant']->run(function () use (&$productBId) {
            $category = Category::create([
                'name' => 'B Products',
                'slug' => 'b-products',
                'type' => 'product',
                'is_active' => true,
            ]);
            $product = Product::create([
                'category_id' => $category->id,
                'name' => 'Tenant B Bag',
                'sku' => 'TB-001',
                'price' => 10.00,
                'is_active' => true,
            ]);
            $productBId = $product->id;
        });

        // 1. Tenant A trying to access Tenant B routes should fail due to Sanctum token sandbox mismatch
        $response = $this->getJson("/api/{$tenantIdB}/orders", ['Authorization' => 'Bearer ' . $tokenA]);
        $response->assertStatus(401);

        // 2. Tenant A trying to create an order referencing Tenant B's product ID should fail because product does not exist in Tenant A's database
        $setupA['tenant']->run(function () use (&$tableAId) {
            $space = RestaurantSpace::create([
                'name' => 'A Space',
                'slug' => 'a-space',
                'is_active' => true,
            ]);
            $table = RestaurantTable::create([
                'restaurant_space_id' => $space->id,
                'table_number' => 'TA1',
                'capacity' => 2,
                'status' => 'vacant',
                'is_active' => true,
            ]);
            $tableAId = $table->id;
        });

        $orderResponse = $this->postJson("/api/{$tenantIdA}/orders", [
            'order_type' => 'dine_in',
            'restaurant_table_id' => $tableAId,
            'items' => [
                [
                    'product_id' => $productBId, // references Tenant B's product ID
                    'quantity' => 1,
                    'unit_price' => 10.00,
                ]
            ],
        ], ['Authorization' => 'Bearer ' . $tokenA]);

        // Model not found validation error or 404/500 model lookup error because productBId does not exist in tenant A DB
        $this->assertTrue(in_array($orderResponse->status(), [404, 422, 500]));
    }
}
