<?php

namespace Tests\Feature;

use App\Models\StaffInvitation;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GrowstroStaffManagementTest extends TestCase
{
    /**
     * Create a fully isolated tenant workspace for testing.
     */
    protected function createTenantWorkspace(
        string $subdomain,
        string $email,
        string $role,
        bool $isActive = true
    ): array {
        $uniqueId = substr($subdomain . '-' . md5(uniqid((string)rand(), true)), 0, 30);

        $tenant = Tenant::create([
            'id'   => $uniqueId,
            'name' => ucfirst($uniqueId) . ' Store',
        ]);

        $tenant->domains()->create(['domain' => "{$uniqueId}.localhost"]);

        $token = null;
        $user  = null;

        $tenant->run(function () use ($email, $role, $isActive, &$token, &$user) {
            $user = User::create([
                'name'      => 'Test User',
                'email'     => $email,
                'password'  => bcrypt('password123'),
                'role'      => $role,
                'is_active' => $isActive,
            ]);
            $token = $user->createToken('test-token')->plainTextToken;
        });

        return ['tenant' => $tenant, 'user' => $user, 'token' => $token];
    }

    protected function setUp(): void
    {
        parent::setUp();
        app('db')->setDefaultConnection('central');
        foreach (Tenant::all() as $t) {
            try { $t->delete(); } catch (\Exception $e) {}
        }
    }

    // =========================================================
    // STAFF CRUD TESTS
    // =========================================================

    public function test_owner_can_list_staff(): void
    {
        $setup = $this->createTenantWorkspace('staff-list', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;

        $setup['tenant']->run(function () {
            User::create([
                'name'     => 'Staff Alpha',
                'email'    => 'alpha@test.com',
                'password' => bcrypt('password123'),
                'role'     => 'staff',
            ]);
        });

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->getJson("/api/{$tid}/staff");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'data' => [
                    '*' => ['id', 'name', 'email', 'phone', 'role', 'permissions', 'effective_permissions', 'is_active', 'created_at']
                ]
            ]
        ]);
        $this->assertCount(2, $response->json('data.data')); // Owner + Staff Alpha
    }

    public function test_owner_can_create_staff_directly(): void
    {
        $setup = $this->createTenantWorkspace('staff-create', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->postJson("/api/{$tid}/staff", [
                'name'        => 'Staff Beta',
                'email'       => 'beta@test.com',
                'password'    => 'password123',
                'phone'       => '9841112222',
                'role'        => 'staff',
                'permissions' => ['manage_orders', 'manage_kot'],
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Staff Beta');
        $response->assertJsonPath('data.role', 'staff');
        $response->assertJsonPath('data.permissions', ['manage_orders', 'manage_kot']);

        $setup['tenant']->run(function () {
            $this->assertTrue(User::where('email', 'beta@test.com')->exists());
        });
    }

    public function test_owner_can_create_manager(): void
    {
        $setup = $this->createTenantWorkspace('staff-mgr', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->postJson("/api/{$tid}/staff", [
                'name'     => 'Manager Gamma',
                'email'    => 'gamma@test.com',
                'password' => 'password123',
                'role'     => 'manager',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.role', 'manager');
    }

    public function test_owner_can_update_staff(): void
    {
        $setup = $this->createTenantWorkspace('staff-upd', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;

        $staffId = null;
        $setup['tenant']->run(function () use (&$staffId) {
            $u = User::create([
                'name'     => 'Staff Delta',
                'email'    => 'delta@test.com',
                'password' => bcrypt('password123'),
                'role'     => 'staff',
            ]);
            $staffId = $u->id;
        });

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->putJson("/api/{$tid}/staff/{$staffId}", [
                'name'  => 'Staff Delta Updated',
                'phone' => '9841234567',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Staff Delta Updated');
        $response->assertJsonPath('data.phone', '9841234567');
    }

    public function test_owner_can_deactivate_staff(): void
    {
        $setup = $this->createTenantWorkspace('staff-deact', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;

        $staffId = null;
        $setup['tenant']->run(function () use (&$staffId) {
            $u = User::create([
                'name'     => 'Staff Delta',
                'email'    => 'delta@test.com',
                'password' => bcrypt('password123'),
                'role'     => 'staff',
            ]);
            $staffId = $u->id;
        });

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->putJson("/api/{$tid}/staff/{$staffId}/deactivate");

        $response->assertStatus(200);
        $response->assertJsonPath('data.is_active', false);

        $setup['tenant']->run(function () use ($staffId) {
            $this->assertFalse(User::find($staffId)->is_active);
        });
    }

    public function test_owner_can_reactivate_staff(): void
    {
        $setup = $this->createTenantWorkspace('staff-react', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;

        $staffId = null;
        $setup['tenant']->run(function () use (&$staffId) {
            $u = User::create([
                'name'      => 'Staff Delta',
                'email'     => 'delta@test.com',
                'password'  => bcrypt('password123'),
                'role'      => 'staff',
                'is_active' => false,
            ]);
            $staffId = $u->id;
        });

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->putJson("/api/{$tid}/staff/{$staffId}/activate");

        $response->assertStatus(200);
        $response->assertJsonPath('data.is_active', true);
    }

    public function test_owner_can_update_permissions(): void
    {
        $setup = $this->createTenantWorkspace('staff-perms', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;

        $staffId = null;
        $setup['tenant']->run(function () use (&$staffId) {
            $u = User::create([
                'name'     => 'Staff Delta',
                'email'    => 'delta@test.com',
                'password' => bcrypt('password123'),
                'role'     => 'staff',
            ]);
            $staffId = $u->id;
        });

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->putJson("/api/{$tid}/staff/{$staffId}/permissions", [
                'permissions' => ['manage_orders', 'manage_kot'],
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.permissions', ['manage_orders', 'manage_kot']);
    }

    public function test_owner_can_update_role(): void
    {
        $setup = $this->createTenantWorkspace('staff-role', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;

        $staffId = null;
        $setup['tenant']->run(function () use (&$staffId) {
            $u = User::create([
                'name'     => 'Staff Delta',
                'email'    => 'delta@test.com',
                'password' => bcrypt('password123'),
                'role'     => 'staff',
            ]);
            $staffId = $u->id;
        });

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->putJson("/api/{$tid}/staff/{$staffId}/role", [
                'role' => 'manager',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.role', 'manager');
    }

    // =========================================================
    // RBAC & GUARD BOUNDARIES
    // =========================================================

    public function test_staff_without_manage_staff_is_blocked(): void
    {
        $setup = $this->createTenantWorkspace('staff-blocked', 'staff@test.com', 'staff');
        $tid   = $setup['tenant']->id;

        // Staff does not have manage_staff by default
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->getJson("/api/{$tid}/staff");

        $response->assertStatus(403);
    }

    public function test_manager_with_manage_staff_can_create_staff(): void
    {
        $setup = $this->createTenantWorkspace('mgr-create', 'manager@test.com', 'manager');
        $tid   = $setup['tenant']->id;

        // Give manager manage_staff permission
        $setup['tenant']->run(function () use ($setup) {
            $setup['user']->update(['permissions' => ['manage_staff', 'manage_customers']]);
        });

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->postJson("/api/{$tid}/staff", [
                'name'     => 'New Staff Under Manager',
                'email'    => 'under_mgr@test.com',
                'password' => 'password123',
                'role'     => 'staff',
            ]);

        $response->assertStatus(201);
    }

    public function test_manager_cannot_create_owner(): void
    {
        $setup = $this->createTenantWorkspace('mgr-owner', 'manager@test.com', 'manager');
        $tid   = $setup['tenant']->id;

        $setup['tenant']->run(function () use ($setup) {
            $setup['user']->update(['permissions' => ['manage_staff']]);
        });

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->postJson("/api/{$tid}/staff", [
                'name'     => 'Fake Owner',
                'email'    => 'fake_owner@test.com',
                'password' => 'password123',
                'role'     => 'owner',
            ]);

        $response->assertStatus(422);
    }

    public function test_staff_cannot_grant_permissions_they_do_not_have(): void
    {
        $setup = $this->createTenantWorkspace('staff-grant', 'manager@test.com', 'manager');
        $tid   = $setup['tenant']->id;

        // Give manager manage_staff but restrict from manage_settings
        $setup['tenant']->run(function () use ($setup) {
            $setup['user']->update(['permissions' => ['manage_staff']]);
        });

        // Try to create a staff member granting manage_settings
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->postJson("/api/{$tid}/staff", [
                'name'        => 'Escalated Staff',
                'email'       => 'escalated@test.com',
                'password'    => 'password123',
                'role'        => 'staff',
                'permissions' => ['manage_settings'], // Manager does not have manage_settings
            ]);

        $response->assertStatus(403);
    }

    public function test_user_cannot_deactivate_self(): void
    {
        $setup = $this->createTenantWorkspace('self-deact', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;
        $owner = $setup['user'];

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->putJson("/api/{$tid}/staff/{$owner->id}/deactivate");

        $response->assertStatus(409); // Conflict
    }

    public function test_owner_cannot_be_deleted(): void
    {
        $setup = $this->createTenantWorkspace('owner-del', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;
        $owner = $setup['user'];

        // Manager with manage_staff
        $managerToken = null;
        $setup['tenant']->run(function () use (&$managerToken) {
            $m = User::create([
                'name'        => 'Manager Alpha',
                'email'       => 'mgr@test.com',
                'password'    => bcrypt('password123'),
                'role'        => 'manager',
                'permissions' => ['manage_staff'],
            ]);
            $managerToken = $m->createToken('mgr-token')->plainTextToken;
        });

        // Attempt delete by manager
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $managerToken])
            ->deleteJson("/api/{$tid}/staff/{$owner->id}");

        $response->assertStatus(403);
    }

    // =========================================================
    // WORKSPACE ISOLATION TESTS
    // =========================================================

    public function test_tenant_a_cannot_access_tenant_b_staff(): void
    {
        $setupA = $this->createTenantWorkspace('ten-a', 'owner-a@test.com', 'owner');
        $setupB = $this->createTenantWorkspace('ten-b', 'owner-b@test.com', 'owner');
        $tidB   = $setupB['tenant']->id;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setupA['token']])
            ->getJson("/api/{$tidB}/staff");

        $response->assertStatus(401);
    }

    public function test_suspended_tenant_is_blocked(): void
    {
        $setup = $this->createTenantWorkspace('susp-ten', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;

        $setup['tenant']->update(['status' => 'suspended']);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->getJson("/api/{$tid}/staff");

        $response->assertStatus(403);
    }

    public function test_inactive_user_is_blocked(): void
    {
        $setup = $this->createTenantWorkspace('inact-usr', 'owner@test.com', 'owner', false);
        $tid   = $setup['tenant']->id;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->getJson("/api/{$tid}/staff");

        $response->assertStatus(403);
    }

    // =========================================================
    // INVITATION TESTS
    // =========================================================

    public function test_owner_can_create_staff_invitation(): void
    {
        $setup = $this->createTenantWorkspace('invite-cre', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->postJson("/api/{$tid}/staff-invitations", [
                'email'       => 'invitee@test.com',
                'phone'       => '9841999999',
                'role'        => 'staff',
                'permissions' => ['manage_orders'],
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => ['id', 'email', 'phone', 'role', 'permissions', 'status', 'is_accepted', 'expires_at'],
            'raw_token',
            'link',
        ]);
        $this->assertNotEmpty($response->json('raw_token'));

        $setup['tenant']->run(function () {
            $this->assertTrue(StaffInvitation::where('email', 'invitee@test.com')->exists());
        });
    }

    public function test_duplicate_invitation_is_blocked(): void
    {
        $setup = $this->createTenantWorkspace('invite-dup', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;

        $setup['tenant']->run(function () {
            StaffInvitation::create([
                'email'      => 'invitee@test.com',
                'token'      => hash('sha256', 'some-token'),
                'role'       => 'staff',
                'expires_at' => Carbon::now()->addDays(7),
                'status'     => 'pending',
            ]);
        });

        // Try to create again for the same email
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->postJson("/api/{$tid}/staff-invitations", [
                'email' => 'invitee@test.com',
                'role'  => 'staff',
            ]);

        $response->assertStatus(409); // Conflict
    }

    public function test_invitation_can_be_cancelled(): void
    {
        $setup = $this->createTenantWorkspace('invite-canc', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;

        $inviteId = null;
        $setup['tenant']->run(function () use (&$inviteId) {
            $inv = StaffInvitation::create([
                'email'      => 'invitee@test.com',
                'token'      => hash('sha256', 'some-token'),
                'role'       => 'staff',
                'expires_at' => Carbon::now()->addDays(7),
                'status'     => 'pending',
            ]);
            $inviteId = $inv->id;
        });

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->deleteJson("/api/{$tid}/staff-invitations/{$inviteId}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'cancelled');

        $setup['tenant']->run(function () use ($inviteId) {
            $this->assertEquals('cancelled', StaffInvitation::find($inviteId)->status);
        });
    }

    public function test_invitation_can_be_resent(): void
    {
        $setup = $this->createTenantWorkspace('invite-res', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;

        $inviteId = null;
        $setup['tenant']->run(function () use (&$inviteId) {
            $inv = StaffInvitation::create([
                'email'      => 'invitee@test.com',
                'token'      => hash('sha256', 'old-token'),
                'role'       => 'staff',
                'expires_at' => Carbon::now()->subDays(1), // already expired
                'status'     => 'pending',
            ]);
            $inviteId = $inv->id;
        });

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->postJson("/api/{$tid}/staff-invitations/{$inviteId}/resend");

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'pending');
        $this->assertNotEmpty($response->json('raw_token'));
    }

    public function test_invitation_can_be_accepted_and_creates_user(): void
    {
        $setup = $this->createTenantWorkspace('invite-acpt', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;

        $rawToken = 'my-super-secret-invite-token';
        $setup['tenant']->run(function () use ($rawToken) {
            StaffInvitation::create([
                'email'       => 'invitee@test.com',
                'token'       => hash('sha256', $rawToken),
                'role'        => 'staff',
                'permissions' => ['manage_orders'],
                'expires_at'  => Carbon::now()->addDays(7),
                'status'      => 'pending',
            ]);
        });

        // Call global accept endpoint
        $response = $this->postJson("/api/auth/accept-staff-invitation", [
            'tenant'   => $tid,
            'token'    => $rawToken,
            'name'     => 'Accepted Staff Name',
            'password' => 'securePassword123',
            'phone'    => '9841234567',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Accepted Staff Name');
        $response->assertJsonPath('data.role', 'staff');
        $response->assertJsonPath('data.permissions', ['manage_orders']);

        $setup['tenant']->run(function () {
            $this->assertTrue(User::where('email', 'invitee@test.com')->exists());
            $this->assertEquals('accepted', StaffInvitation::first()->status);
        });
    }

    public function test_expired_invitation_cannot_be_accepted(): void
    {
        $setup = $this->createTenantWorkspace('invite-exp', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;

        $rawToken = 'expired-token';
        $setup['tenant']->run(function () use ($rawToken) {
            StaffInvitation::create([
                'email'      => 'invitee@test.com',
                'token'      => hash('sha256', $rawToken),
                'role'       => 'staff',
                'expires_at' => Carbon::now()->subDays(1), // Expired yesterday
                'status'     => 'pending',
            ]);
        });

        $response = $this->postJson("/api/auth/accept-staff-invitation", [
            'tenant'   => $tid,
            'token'    => $rawToken,
            'name'     => 'Accepted Staff',
            'password' => 'securePassword123',
        ]);

        $response->assertStatus(422); // Expired or Unprocessable
    }

    public function test_accepted_invitation_token_hash_is_not_leaked(): void
    {
        $setup = $this->createTenantWorkspace('invite-leak', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->postJson("/api/{$tid}/staff-invitations", [
                'email' => 'invitee@test.com',
                'role'  => 'staff',
            ]);

        $response->assertStatus(201);
        $response->assertJsonMissing(['token']);
        $response->assertJsonMissing(['token_hash']);
    }

    public function test_unauthenticated_request_fails(): void
    {
        $setup = $this->createTenantWorkspace('unauth', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;

        $response = $this->getJson("/api/{$tid}/staff");
        $response->assertStatus(401);
    }

    public function test_validation_errors_return_consistent_json(): void
    {
        $setup = $this->createTenantWorkspace('val-err', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->postJson("/api/{$tid}/staff", [
                'name' => '', // blank name
            ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors']);
    }
}
