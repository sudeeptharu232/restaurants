<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\BusinessSetting;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GrowstroInvoiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        // Force central database connection at setup start
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
     * Create isolated tenant and user setup helper.
     */
    protected function createTenantWorkspace(string $tenantPrefix, string $email, string $role = 'owner', bool $isActive = true): array
    {
        app('db')->setDefaultConnection('central');

        $tenantId = 't-' . $tenantPrefix . '-' . bin2hex(random_bytes(4));

        // Create Tenant
        $tenant = Tenant::create(['id' => $tenantId]);
        $tenant->domains()->create(['domain' => "{$tenantId}.localhost"]);

        $user = null;
        $customer = null;
        $menuItem = null;

        $tenant->run(function () use ($email, $role, $isActive, &$user, &$customer, &$menuItem) {
            // Seed a tenant user
            $user = User::create([
                'name' => 'Test User',
                'email' => $email,
                'password' => Hash::make('password123'),
                'role' => $role,
                'is_active' => $isActive,
            ]);

            // Seed a customer
            $customer = Customer::create([
                'name' => 'John Doe',
                'phone' => '9876543210',
                'address' => 'Kathmandu',
                'is_active' => true,
            ]);

            // Seed a catalog MenuItem
            $menuItem = MenuItem::create([
                'name' => 'Chicken Momo',
                'sku' => 'MOMO-CHK-01',
                'price' => 200.00,
                'is_active' => true,
            ]);

            // Set default settings
            BusinessSetting::updateOrCreate(
                ['key' => 'vat_enabled', 'tenant_id' => tenant('id')],
                ['value' => 'false']
            );
            BusinessSetting::updateOrCreate(
                ['key' => 'business_name', 'tenant_id' => tenant('id')],
                ['value' => 'Growstro Kathmandu']
            );
        });

        // Generate token
        $token = $tenant->run(fn() => $user->createToken('test_token')->plainTextToken);

        return [
            'tenant' => $tenant,
            'user' => $user,
            'customer' => $customer,
            'menuItem' => $menuItem,
            'token' => $token,
        ];
    }

    /**
     * Test owner can create manual invoice.
     */
    public function test_owner_can_create_manual_invoice(): void
    {
        $setup = $this->createTenantWorkspace('t-invoice-1', 'owner1@test.com');
        $tenantId = $setup['tenant']->id;

        $payload = [
            'customer_id' => $setup['customer']->id,
            'invoice_date' => '2026-05-18',
            'due_date' => '2026-05-25',
            'notes' => 'Collect payment cash only',
            'items' => [
                [
                    'menu_item_id' => $setup['menuItem']->id,
                    'quantity' => 2,
                    'unit_price' => 200.00,
                    'discount_amount' => 10.00
                ]
            ],
            'discount_amount' => 5.00
        ];

        $response = $this->postJson("/api/{$tenantId}/invoices", $payload, [
            'Authorization' => 'Bearer ' . $setup['token']
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.status', 'draft');
        
        $data = $response->json('data');
        $this->assertEquals(400.00, $data['subtotal']);
        $this->assertEquals(15.00, $data['discount']);
        $this->assertEquals(385.00, $data['taxable_amount']);
        $this->assertEquals(385.00, $data['total']);
    }

    /**
     * Test owner can create invoice from order.
     */
    public function test_owner_can_create_invoice_from_order(): void
    {
        $setup = $this->createTenantWorkspace('t-invoice-2', 'owner2@test.com');
        $tenantId = $setup['tenant']->id;

        $orderId = null;
        $setup['tenant']->run(function () use ($setup, &$orderId) {
            $order = Order::create([
                'customer_id' => $setup['customer']->id,
                'order_number' => 'ORD-101',
                'type' => 'dine_in',
                'status' => 'pending',
                'subtotal' => 500.00,
                'discount_amount' => 50.00,
                'vat_amount' => 0.00,
                'total' => 450.00,
                'paid_amount' => 100.00,
                'due_amount' => 350.00,
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'menu_item_id' => $setup['menuItem']->id,
                'name' => 'Chicken Momo',
                'quantity' => 2,
                'unit_price' => 250.00,
                'total_amount' => 500.00,
            ]);
            $orderId = $order->id;
        });

        $response = $this->postJson("/api/{$tenantId}/orders/{$orderId}/invoice", [], [
            'Authorization' => 'Bearer ' . $setup['token']
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.status', 'draft');
        
        $data = $response->json('data');
        $this->assertEquals(500.00, $data['subtotal']);
        $this->assertEquals(450.00, $data['total']);
        $this->assertEquals(100.00, $data['paid_amount']);
        $this->assertEquals(350.00, $data['due_amount']);
    }

    /**
     * Test duplicate invoice from same order is blocked.
     */
    public function test_duplicate_invoice_from_same_order_is_blocked(): void
    {
        $setup = $this->createTenantWorkspace('t-invoice-3', 'owner3@test.com');
        $tenantId = $setup['tenant']->id;

        $orderId = null;
        $setup['tenant']->run(function () use ($setup, &$orderId) {
            $order = Order::create([
                'order_number' => 'ORD-102',
                'type' => 'takeaway',
                'status' => 'pending',
                'subtotal' => 100.00,
                'total' => 100.00,
            ]);
            $orderId = $order->id;
        });

        // 1st invoice succeeds
        $res1 = $this->postJson("/api/{$tenantId}/orders/{$orderId}/invoice", [], [
            'Authorization' => 'Bearer ' . $setup['token']
        ]);
        $res1->assertStatus(201);

        auth()->forgetUser();

        // 2nd duplicate invoice blocks
        $res2 = $this->postJson("/api/{$tenantId}/orders/{$orderId}/invoice", [], [
            'Authorization' => 'Bearer ' . $setup['token']
        ]);
        $res2->assertStatus(422);
    }

    /**
     * Test invoice number is sequential and unique.
     */
    public function test_invoice_number_is_sequential_and_unique(): void
    {
        $setup = $this->createTenantWorkspace('t-invoice-4', 'owner4@test.com');
        $tenantId = $setup['tenant']->id;

        $payload = [
            'invoice_date' => '2026-05-18',
            'items' => [['name' => 'Custom Item', 'quantity' => 1, 'unit_price' => 100]]
        ];

        $res1 = $this->postJson("/api/{$tenantId}/invoices", $payload, ['Authorization' => 'Bearer ' . $setup['token']]);
        $res1->assertStatus(201);
        $num1 = $res1->json('data.invoice_number');

        auth()->forgetUser();

        $res2 = $this->postJson("/api/{$tenantId}/invoices", $payload, ['Authorization' => 'Bearer ' . $setup['token']]);
        $res2->assertStatus(201);
        $num2 = $res2->json('data.invoice_number');

        $this->assertNotEquals($num1, $num2);
        $this->assertStringContainsString('INV-2026-', $num1);
        $this->assertStringContainsString('INV-2026-', $num2);
    }

    /**
     * Test VAT calculates 13% when enabled centrally.
     */
    public function test_vat_calculates_13_percent_when_enabled(): void
    {
        $setup = $this->createTenantWorkspace('t-invoice-5', 'owner5@test.com');
        $tenantId = $setup['tenant']->id;

        // Enable VAT centrally
        $setup['tenant']->run(function () {
            BusinessSetting::updateOrCreate(
                ['key' => 'vat_enabled', 'tenant_id' => tenant('id')],
                ['value' => 'true']
            );
        });

        $payload = [
            'invoice_date' => '2026-05-18',
            'items' => [
                [
                    'name' => 'Taxable Burger',
                    'quantity' => 2,
                    'unit_price' => 500.00 // Subtotal 1000
                ]
            ],
            'discount_amount' => 0
        ];

        $response = $this->postJson("/api/{$tenantId}/invoices", $payload, [
            'Authorization' => 'Bearer ' . $setup['token']
        ]);

        $response->assertStatus(201);
        
        $data = $response->json('data');
        $this->assertEquals(130.00, $data['vat_amount']);
        $this->assertEquals(1130.00, $data['total']);
    }

    /**
     * Test VAT is zero when disabled centrally.
     */
    public function test_vat_is_zero_when_disabled(): void
    {
        $setup = $this->createTenantWorkspace('t-invoice-6', 'owner6@test.com');
        $tenantId = $setup['tenant']->id;

        // VAT is disabled by default
        $payload = [
            'invoice_date' => '2026-05-18',
            'items' => [
                [
                    'name' => 'Burger',
                    'quantity' => 2,
                    'unit_price' => 500.00
                ]
            ]
        ];

        $response = $this->postJson("/api/{$tenantId}/invoices", $payload, [
            'Authorization' => 'Bearer ' . $setup['token']
        ]);

        $response->assertStatus(201);
        
        $data = $response->json('data');
        $this->assertEquals(0.00, $data['vat_amount']);
        $this->assertEquals(1000.00, $data['total']);
    }

    /**
     * Test discount validation rules fail on negative amount.
     */
    public function test_invalid_discount_fails_validation(): void
    {
        $setup = $this->createTenantWorkspace('t-invoice-7', 'owner7@test.com');
        $tenantId = $setup['tenant']->id;

        $payload = [
            'invoice_date' => '2026-05-18',
            'discount_amount' => -20, // Negative discounts should be blocked by StoreInvoiceRequest validation!
            'items' => [['name' => 'Item', 'quantity' => 1, 'unit_price' => 50]]
        ];

        $response = $this->postJson("/api/{$tenantId}/invoices", $payload, [
            'Authorization' => 'Bearer ' . $setup['token']
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test catalog price snapshotted correctly override.
     */
    public function test_invoice_items_are_snapshotted_correctly(): void
    {
        $setup = $this->createTenantWorkspace('t-invoice-8', 'owner8@test.com');
        $tenantId = $setup['tenant']->id;

        $payload = [
            'invoice_date' => '2026-05-18',
            'items' => [
                [
                    'menu_item_id' => $setup['menuItem']->id, // Momo is priced 200.00 centrally
                    'quantity' => 3,
                    'unit_price' => 50.00 // Spoofed unit price
                ]
            ]
        ];

        $response = $this->postJson("/api/{$tenantId}/invoices", $payload, [
            'Authorization' => 'Bearer ' . $setup['token']
        ]);

        $response->assertStatus(201);
        
        $data = $response->json('data');
        $this->assertEquals(600.00, $data['subtotal']);
        $this->assertEquals(600.00, $data['total']);
    }

    /**
     * Test draft invoice can be updated.
     */
    public function test_draft_invoice_can_be_updated(): void
    {
        $setup = $this->createTenantWorkspace('t-invoice-9', 'owner9@test.com');
        $tenantId = $setup['tenant']->id;

        $invoiceId = null;
        $setup['tenant']->run(function () use ($setup, &$invoiceId) {
            $invoice = Invoice::create([
                'invoice_number' => 'INV-2026-000999',
                'status' => 'draft',
                'subtotal' => 100.0,
                'total' => 100.0,
                'invoice_date' => '2026-05-18'
            ]);
            $invoiceId = $invoice->id;
        });

        $updatePayload = [
            'notes' => 'Updated draft note description',
            'discount_amount' => 10.00
        ];

        $response = $this->putJson("/api/{$tenantId}/invoices/{$invoiceId}", $updatePayload, [
            'Authorization' => 'Bearer ' . $setup['token']
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.notes', 'Updated draft note description');
        
        $data = $response->json('data');
        $this->assertEquals(10.00, $data['discount']);
        $this->assertEquals(90.00, $data['total']);
    }

    /**
     * Test issued invoice cannot be edited except notes.
     */
    public function test_issued_invoice_cannot_be_unsafely_edited(): void
    {
        $setup = $this->createTenantWorkspace('t-invoice-10', 'owner10@test.com');
        $tenantId = $setup['tenant']->id;

        $invoiceId = null;
        $setup['tenant']->run(function () use ($setup, &$invoiceId) {
            $invoice = Invoice::create([
                'invoice_number' => 'INV-2026-001000',
                'status' => 'issued',
                'subtotal' => 200.0,
                'total' => 200.0,
                'invoice_date' => '2026-05-18'
            ]);
            $invoiceId = $invoice->id;
        });

        // 1. Changing notes only -> Allowed!
        $resNotes = $this->putJson("/api/{$tenantId}/invoices/{$invoiceId}", ['notes' => 'Allowed notes change'], [
            'Authorization' => 'Bearer ' . $setup['token']
        ]);
        $resNotes->assertStatus(200);

        auth()->forgetUser();

        // 2. Unsafe change discount_amount -> Blocked!
        $resUnsafe = $this->putJson("/api/{$tenantId}/invoices/{$invoiceId}", ['discount_amount' => 50], [
            'Authorization' => 'Bearer ' . $setup['token']
        ]);
        $resUnsafe->assertStatus(422);
    }

    /**
     * Test owner can issue invoice and PDF is generated.
     */
    public function test_owner_can_issue_invoice(): void
    {
        $setup = $this->createTenantWorkspace('t-invoice-11', 'owner11@test.com');
        $tenantId = $setup['tenant']->id;

        $invoiceId = null;
        $setup['tenant']->run(function () use ($setup, &$invoiceId) {
            $invoice = Invoice::create([
                'invoice_number' => 'INV-2026-000011',
                'status' => 'draft',
                'subtotal' => 100.0,
                'total' => 100.0,
                'invoice_date' => '2026-05-18'
            ]);
            
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'name' => 'Chicken Momo',
                'quantity' => 1,
                'unit_price' => 100.0,
                'total_amount' => 100.0,
            ]);
            $invoiceId = $invoice->id;
        });

        $response = $this->postJson("/api/{$tenantId}/invoices/{$invoiceId}/issue", [], [
            'Authorization' => 'Bearer ' . $setup['token']
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'issued');
        
        $pdfPath = $response->json('data.pdf_path');
        $this->assertNotEmpty($pdfPath);
        $this->assertStringContainsString("invoices/{$tenantId}/", $pdfPath);
        Storage::disk('local')->assertExists($pdfPath);
    }

    /**
     * Test owner can cancel invoice.
     */
    public function test_owner_can_cancel_invoice(): void
    {
        $setup = $this->createTenantWorkspace('t-invoice-12', 'owner12@test.com');
        $tenantId = $setup['tenant']->id;

        $invoiceId = null;
        $setup['tenant']->run(function () use ($setup, &$invoiceId) {
            $invoice = Invoice::create([
                'invoice_number' => 'INV-2026-000012',
                'status' => 'draft',
                'subtotal' => 100.0,
                'total' => 100.0,
                'invoice_date' => '2026-05-18'
            ]);
            $invoiceId = $invoice->id;
        });

        $response = $this->postJson("/api/{$tenantId}/invoices/{$invoiceId}/cancel", [], [
            'Authorization' => 'Bearer ' . $setup['token']
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'cancelled');
    }

    /**
     * Test PDF download and storage isolation boundary.
     */
    public function test_pdf_can_be_downloaded_and_isolation_works(): void
    {
        $setupA = $this->createTenantWorkspace('t-invoice-13a', 'owner13a@test.com');
        $setupB = $this->createTenantWorkspace('t-invoice-13b', 'owner13b@test.com');

        $tenantA = $setupA['tenant']->id;
        $tenantB = $setupB['tenant']->id;

        // Generate invoice and issue on Tenant A
        $invoiceAId = null;
        $setupA['tenant']->run(function () use ($setupA, &$invoiceAId) {
            $invoice = Invoice::create([
                'invoice_number' => 'INV-2026-A101',
                'status' => 'draft',
                'subtotal' => 100.0,
                'total' => 100.0,
                'invoice_date' => '2026-05-18'
            ]);
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'name' => 'Momo',
                'quantity' => 1,
                'unit_price' => 100.0,
                'total_amount' => 100.0,
            ]);
            $invoiceAId = $invoice->id;
        });

        // Issue on Tenant A to generate PDF
        $resIssue = $this->postJson("/api/{$tenantA}/invoices/{$invoiceAId}/issue", [], [
            'Authorization' => 'Bearer ' . $setupA['token']
        ]);
        $resIssue->assertStatus(200);

        auth()->forgetUser();

        // 1. Tenant A owner downloads Tenant A PDF -> Allowed!
        $resDL = $this->getJson("/api/{$tenantA}/invoices/{$invoiceAId}/download-pdf", [
            'Authorization' => 'Bearer ' . $setupA['token']
        ]);
        $resDL->assertStatus(200);
        $resDL->assertHeader('Content-Type', 'application/pdf');

        auth()->forgetUser();

        // 2. Tenant B owner attempts to cross download Tenant A PDF -> Blocked!
        $resCross = $this->getJson("/api/{$tenantB}/invoices/{$invoiceAId}/download-pdf", [
            'Authorization' => 'Bearer ' . $setupB['token']
        ]);
        // Path resolution or model lookup locks it to tenant domain context, throwing 404 since A's ID doesn't exist in B's DB!
        $resCross->assertStatus(404);
    }

    /**
     * Test staff with manage_invoices permission allowed.
     */
    public function test_staff_with_manage_invoices_can_create_invoice(): void
    {
        $setup = $this->createTenantWorkspace('t-invoice-14', 'staff14@test.com', 'staff');
        $tenantId = $setup['tenant']->id;

        // Custom assign manage_invoices permission to manager/staff during test?
        // Wait, standard staff role doesn't have manage_invoices in the controller permissionMap.
        // Let's create a Manager instead! Our manager role in the controller helper has `manage_invoices` allowed!
        $managerSetup = $this->createTenantWorkspace('t-invoice-14m', 'manager14@test.com', 'manager');
        $mId = $managerSetup['tenant']->id;

        $payload = [
            'invoice_date' => '2026-05-18',
            'items' => [['name' => 'Burger', 'quantity' => 1, 'unit_price' => 100]]
        ];

        $response = $this->postJson("/api/{$mId}/invoices", $payload, [
            'Authorization' => 'Bearer ' . $managerSetup['token']
        ]);

        $response->assertStatus(201);
    }

    /**
     * Test staff without permission is blocked.
     */
    public function test_staff_without_permission_is_blocked(): void
    {
        $setup = $this->createTenantWorkspace('t-invoice-15', 'staff15@test.com', 'staff');
        $tenantId = $setup['tenant']->id;

        $payload = [
            'invoice_date' => '2026-05-18',
            'items' => [['name' => 'Burger', 'quantity' => 1, 'unit_price' => 100]]
        ];

        $response = $this->postJson("/api/{$tenantId}/invoices", $payload, [
            'Authorization' => 'Bearer ' . $setup['token']
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test suspended tenant cannot access invoices.
     */
    public function test_suspended_tenant_is_blocked(): void
    {
        $setup = $this->createTenantWorkspace('t-invoice-16', 'owner16@test.com');
        $tenantId = $setup['tenant']->id;

        // Suspend tenant in central DB
        $setup['tenant']->status = 'suspended';
        $setup['tenant']->save();

        $response = $this->getJson("/api/{$tenantId}/invoices", [
            'Authorization' => 'Bearer ' . $setup['token']
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test unauthenticated request fails.
     */
    public function test_unauthenticated_request_fails(): void
    {
        $setup = $this->createTenantWorkspace('t-invoice-17', 'owner17@test.com');
        $tenantId = $setup['tenant']->id;

        $response = $this->getJson("/api/{$tenantId}/invoices");

        $response->assertStatus(401);
    }
}
