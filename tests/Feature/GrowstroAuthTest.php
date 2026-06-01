<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tenant;
use App\Models\SubscriptionPlan;
use App\Models\Subscription;
use App\Models\BusinessSetting;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GrowstroAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 1. Force the database manager to the central connection at the start of setup
        app('db')->setDefaultConnection('central');

        if (tenancy()->initialized) {
            tenancy()->end();
        }

        // 2. Clean central database state manually to guarantee 100% isolation
        foreach (Tenant::all() as $tenant) {
            try {
                $tenant->delete(); // Triggers event to drop physical database schema
            } catch (\Exception $e) {
                // Fail-safe ignore
            }
        }

        // 3. Permanently purge central records including soft-deleted ones
        User::query()->delete();
        BusinessSetting::query()->delete();
        Subscription::query()->delete();
        SubscriptionPlan::withTrashed()->forceDelete();

        // 4. Clean up tenancy states completely and purge connection caches
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        app('db')->purge('tenant');
        app('db')->setDefaultConnection('central');

        // 5. Provision basic subscription plan centrally
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
        // 1. Terminate any active tenant context
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        // 2. Clean up all dynamically created test tenants and drop their physical schemas
        foreach (Tenant::all() as $tenant) {
            if (str_starts_with($tenant->id, 't-') || str_starts_with($tenant->id, 'manual-')) {
                try {
                    $tenant->delete();
                } catch (\Exception $e) {
                    // Fail-safe ignore
                }
            }
        }

        // 3. Purge the dynamic tenant connection from DatabaseManager to avoid caching issues
        app('db')->purge('tenant');
        app('db')->setDefaultConnection('central');

        parent::tearDown();
    }

    /**
     * Helper to generate unique tenant handles.
     */
    protected function makeTenantId(string $prefix): string
    {
        return 't-' . $prefix . '-' . bin2hex(random_bytes(4));
    }

    /**
     * 1. Test business registration success.
     */
    public function test_business_registration_succeeds(): void
    {
        $uniqueName = 'Biz ' . bin2hex(random_bytes(4));
        
        $response = $this->postJson('/api/auth/register-business', [
            'business_name' => $uniqueName,
            'owner_name' => 'John Doe',
            'phone' => '9876543210',
            'email' => 'john@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'address' => 'Kathmandu, Nepal',
            'business_type' => 'restaurant',
            'pan_or_vat_number' => '123456789',
            'is_vat_registered' => true,
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('success', true);

        // Reset tenancy so central model lookups function perfectly
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        app('db')->setDefaultConnection('central');

        $retrievedTenantId = $response->json('data.business.id');
        $tenant = Tenant::find($retrievedTenantId);
        $this->assertNotNull($tenant);

        $subscription = Subscription::where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($subscription);
        $this->assertEquals('trialing', $subscription->status);
    }

    /**
     * 2. Test business registration validation failure.
     */
    public function test_business_registration_fails_validation(): void
    {
        $response = $this->postJson('/api/auth/register-business', [
            'business_name' => '',
            'owner_name' => 'John Doe',
            'phone' => '',
            'email' => 'invalid-email',
            'password' => 'pass',
            'password_confirmation' => 'mismatch',
            'address' => 'Kathmandu',
            'business_type' => 'invalid-type',
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('success', false)
                 ->assertJsonStructure(['success', 'message', 'errors']);
    }

    /**
     * 3. Test login success.
     */
    public function test_login_succeeds(): void
    {
        $uniqueName = 'Biz ' . bin2hex(random_bytes(4));
        $email = 'login-' . bin2hex(random_bytes(4)) . '@test.com';

        // Register business first to build tenant and owner
        $reg = $this->postJson('/api/auth/register-business', [
            'business_name' => $uniqueName,
            'owner_name' => 'Sita Ram',
            'phone' => '9876543210',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'address' => 'Pokhara',
            'business_type' => 'retail',
        ]);

        $responseTenantId = $reg->json('data.business.id');

        if (tenancy()->initialized) {
            tenancy()->end();
        }
        app('db')->setDefaultConnection('central');

        // Attempt login scoped to the tenant
        $response = $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => 'password123',
            'tenant' => $responseTenantId
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('success', true);
    }

    /**
     * 4. Test login invalid credentials.
     */
    public function test_login_fails_invalid_credentials(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'none-auth@test.com',
            'password' => 'wrong-pass',
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('success', false);
    }

    /**
     * 5. Test logout deletes current token.
     */
    public function test_logout_deletes_current_token(): void
    {
        $uniqueName = 'Biz ' . bin2hex(random_bytes(4));
        $email = 'logout-' . bin2hex(random_bytes(4)) . '@test.com';

        $regResponse = $this->postJson('/api/auth/register-business', [
            'business_name' => $uniqueName,
            'owner_name' => 'Cafe Owner',
            'phone' => '9841112223',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'address' => 'Lalitpur',
            'business_type' => 'cafe',
        ]);

        $token = $regResponse->json('data.access_token');

        if (tenancy()->initialized) {
            tenancy()->end();
        }
        app('db')->setDefaultConnection('central');

        $tenantId = $regResponse->json('data.business.id');

        $response = $this->postJson("/api/{$tenantId}/auth/logout", [], [
            'Authorization' => 'Bearer ' . $token
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('success', true);
    }

    /**
     * 6. Test /api/me returns authenticated user.
     */
    public function test_me_returns_authenticated_user(): void
    {
        $uniqueName = 'Biz ' . bin2hex(random_bytes(4));
        $email = 'me-' . bin2hex(random_bytes(4)) . '@test.com';

        $regResponse = $this->postJson('/api/auth/register-business', [
            'business_name' => $uniqueName,
            'owner_name' => 'Cafe Owner',
            'phone' => '9841112223',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'address' => 'Lalitpur',
            'business_type' => 'cafe',
        ]);

        $token = $regResponse->json('data.access_token');
        $this->assertNotNull($token);

        if (tenancy()->initialized) {
            tenancy()->end();
        }
        app('db')->setDefaultConnection('central');

        $tenantId = $regResponse->json('data.business.id');

        $response = $this->getJson("/api/{$tenantId}/me", [
            'Authorization' => 'Bearer ' . $token
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.email', $email);
    }

    /**
     * 7. Test inactive user cannot login.
     */
    public function test_inactive_user_cannot_login(): void
    {
        $uniqueName = 'Biz ' . bin2hex(random_bytes(4));
        $email = 'inactive-' . bin2hex(random_bytes(4)) . '@test.com';

        $regResponse = $this->postJson('/api/auth/register-business', [
            'business_name' => $uniqueName,
            'owner_name' => 'Shop Keeper',
            'phone' => '9876543210',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'address' => 'Biratnagar',
            'business_type' => 'retail',
        ]);

        $tenantId = $regResponse->json('data.business.id');

        if (tenancy()->initialized) {
            tenancy()->end();
        }
        app('db')->setDefaultConnection('central');

        $tenant = Tenant::find($tenantId);
        $this->assertNotNull($tenant);

        // Swap connection context and deactivate owner
        $tenant->run(function () use ($email) {
            $owner = User::where('email', $email)->first();
            $owner->is_active = false;
            $owner->save();
        });

        if (tenancy()->initialized) {
            tenancy()->end();
        }
        app('db')->setDefaultConnection('central');

        // Attempt login
        $response = $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => 'password123',
            'tenant' => $tenantId
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('success', false)
                 ->assertJsonPath('message', 'Your user account is suspended or inactive');
    }

    /**
     * 8. Test suspended tenant cannot access tenant route.
     */
    public function test_suspended_tenant_cannot_access_tenant_route(): void
    {
        $uniqueName = 'Biz ' . bin2hex(random_bytes(4));
        $email = 'suspend-' . bin2hex(random_bytes(4)) . '@test.com';

        $regResponse = $this->postJson('/api/auth/register-business', [
            'business_name' => $uniqueName,
            'owner_name' => 'Spa Owner',
            'phone' => '9876543210',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'address' => 'Butwal',
            'business_type' => 'service',
        ]);

        $token = $regResponse->json('data.access_token');
        $tenantId = $regResponse->json('data.business.id');

        if (tenancy()->initialized) {
            tenancy()->end();
        }
        app('db')->setDefaultConnection('central');

        $tenant = Tenant::find($tenantId);
        $this->assertNotNull($tenant);

        // Suspend the tenant centrally
        $tenant->status = 'suspended';
        $tenant->save();

        // Query customers index route
        $response = $this->getJson("/api/{$tenantId}/customers", [
            'Authorization' => 'Bearer ' . $token
        ]);

        $response->assertStatus(403)
                 ->assertJsonPath('success', false)
                 ->assertJsonPath('message', 'This business account has been suspended');
    }

    /**
     * 9. Test owner cannot access another tenant.
     */
    public function test_owner_cannot_access_another_tenant(): void
    {
        $uniqueName1 = 'Biz ' . bin2hex(random_bytes(4));
        $email1 = 'owner1-' . bin2hex(random_bytes(4)) . '@test.com';

        // 1. Register first business via API
        $reg1 = $this->postJson('/api/auth/register-business', [
            'business_name' => $uniqueName1,
            'owner_name' => 'Owner One',
            'phone' => '9840001111',
            'email' => $email1,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'address' => 'Kathmandu',
            'business_type' => 'retail',
        ]);
        $token1 = $reg1->json('data.access_token');

        if (tenancy()->initialized) {
            tenancy()->end();
        }
        app('db')->setDefaultConnection('central');

        // 2. Manually provision the second tenant to bypass sub-request database caching errors
        $tenant2Id = 'manual-biz-' . bin2hex(random_bytes(4));
        $tenant2 = Tenant::create([
            'id' => $tenant2Id,
            'name' => 'Second Shop',
        ]);

        $tenant2->run(function () {
            User::create([
                'name' => 'Owner Two',
                'email' => 'owner2@test.com',
                'password' => Hash::make('password123'),
                'role' => 'owner',
                'is_active' => true,
            ]);
        });

        if (tenancy()->initialized) {
            tenancy()->end();
        }
        app('db')->setDefaultConnection('central');

        // Attempt to access Shop Two's customers list utilizing Shop One's bearer token!
        $response = $this->getJson("/api/{$tenant2Id}/customers", [
            'Authorization' => 'Bearer ' . $token1
        ]);

        $response->assertStatus(401); // Unauthenticated context boundary prevents cross-tenant access!
    }

    /**
     * 10. Test staff cannot access restricted route.
     */
    public function test_staff_cannot_access_restricted_route(): void
    {
        $uniqueName = 'Biz ' . bin2hex(random_bytes(4));
        $email = 'stafftest-' . bin2hex(random_bytes(4)) . '@test.com';

        $reg = $this->postJson('/api/auth/register-business', [
            'business_name' => $uniqueName,
            'owner_name' => 'Hotel Owner',
            'phone' => '9840001111',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'address' => 'Kathmandu',
            'business_type' => 'restaurant',
        ]);

        $tenantId = $reg->json('data.business.id');

        if (tenancy()->initialized) {
            tenancy()->end();
        }
        app('db')->setDefaultConnection('central');

        $tenant = Tenant::find($tenantId);
        $this->assertNotNull($tenant);
        $staffToken = null;

        // Provision a staff user inside the tenant connection scope
        $tenant->run(function () use (&$staffToken) {
            $staff = User::create([
                'name' => 'Cashier Ram',
                'email' => 'ram@test.com',
                'password' => Hash::make('password123'),
                'role' => 'staff',
                'is_active' => true,
            ]);

            $staffToken = $staff->createToken('auth_token')->plainTextToken;
        });

        if (tenancy()->initialized) {
            tenancy()->end();
        }
        app('db')->setDefaultConnection('central');

        // Query settings route which requires owner or manager roles
        $response = $this->getJson("/api/{$tenantId}/settings", [
            'Authorization' => 'Bearer ' . $staffToken
        ]);

        $response->assertStatus(403)
                 ->assertJsonPath('success', false)
                 ->assertJsonPath('message', 'Forbidden: You do not have the required access permissions');
    }

    /**
     * 11. Test super admin can access admin context.
     */
    public function test_super_admin_can_access_admin_context(): void
    {
        // Clean central database user to avoid conflicts
        User::where('email', 'admin-central@growstro.test')->delete();

        // Provision super admin centrally
        $superAdmin = User::create([
            'name' => 'System Administrator',
            'email' => 'admin-central@growstro.test',
            'password' => Hash::make('GrowstroSuperSecure2026!'),
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $token = $superAdmin->createToken('auth_token')->plainTextToken;

        // Query central super admin route
        $response = $this->getJson('/api/admin/dashboard', [
            'Authorization' => 'Bearer ' . $token
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('message', 'Platform dashboard metrics retrieved successfully');
    }
}
