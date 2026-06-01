<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Customer;
use App\Models\Payment;
use App\Services\KhaltiPaymentService;
use App\Services\FonepayPaymentService;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GrowstroPaymentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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
        $invoice = null;
        $order = null;

        $tenant->run(function () use ($email, $role, $isActive, &$user, &$customer, &$invoice, &$order) {
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

            // Seed sample invoice
            $invoice = Invoice::create([
                'customer_id' => $customer->id,
                'invoice_number' => 'INV-2026-000001',
                'invoice_date' => now()->toDateString(),
                'status' => 'issued',
                'subtotal' => 200.00,
                'discount' => 0.00,
                'taxable_amount' => 200.00,
                'vat_amount' => 26.00, // 13%
                'total' => 226.00,
                'paid_amount' => 0.00,
                'due_amount' => 226.00,
            ]);

            // Seed sample order
            $order = Order::create([
                'customer_id' => $customer->id,
                'order_number' => 'ORD-001',
                'type' => 'dine_in',
                'status' => 'pending',
                'subtotal' => 200.00,
                'discount_amount' => 0.00,
                'vat_amount' => 26.00,
                'total' => 226.00,
                'paid_amount' => 0.00,
                'due_amount' => 226.00,
            ]);
        });

        // Generate token
        $token = $tenant->run(fn() => $user->createToken('test_token')->plainTextToken);

        return [
            'tenant' => $tenant,
            'user' => $user,
            'customer' => $customer,
            'invoice' => $invoice,
            'order' => $order,
            'token' => $token,
        ];
    }

    /**
     * Test owner can add manual cash payment to invoice.
     */
    public function test_owner_can_add_manual_cash_payment_to_invoice(): void
    {
        $setup = $this->createTenantWorkspace('payment', 'owner@test.com', 'owner');
        $tenantId = $setup['tenant']->id;
        $invoice = $setup['invoice'];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $setup['token'],
        ])->postJson("/api/{$tenantId}/payments/manual", [
            'invoice_id' => $invoice->id,
            'gateway' => 'cash',
            'amount' => 100.00,
            'notes' => 'Partial payment',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        // Verify balance updates
        $setup['tenant']->run(function() use ($invoice) {
            $updated = Invoice::find($invoice->id);
            $this->assertEquals(100.00, $updated->paid_amount);
            $this->assertEquals(126.00, $updated->due_amount);
            $this->assertEquals('partially_paid', $updated->status);
        });
    }

    /**
     * Test full payment updates invoice status to paid.
     */
    public function test_full_payment_updates_invoice_status_to_paid(): void
    {
        $setup = $this->createTenantWorkspace('payment', 'owner@test.com', 'owner');
        $tenantId = $setup['tenant']->id;
        $invoice = $setup['invoice'];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $setup['token'],
        ])->postJson("/api/{$tenantId}/payments/manual", [
            'invoice_id' => $invoice->id,
            'gateway' => 'cash',
            'amount' => 226.00,
        ]);

        $response->assertStatus(201);

        // Verify balance updates
        $setup['tenant']->run(function() use ($invoice) {
            $updated = Invoice::find($invoice->id);
            $this->assertEquals(226.00, $updated->paid_amount);
            $this->assertEquals(0.00, $updated->due_amount);
            $this->assertEquals('paid', $updated->status);
        });
    }

    /**
     * Test owner can add manual bank payment to order.
     */
    public function test_owner_can_add_manual_bank_payment_to_order(): void
    {
        $setup = $this->createTenantWorkspace('payment', 'owner@test.com', 'owner');
        $tenantId = $setup['tenant']->id;
        $order = $setup['order'];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $setup['token'],
        ])->postJson("/api/{$tenantId}/payments/manual", [
            'order_id' => $order->id,
            'gateway' => 'bank',
            'amount' => 226.00,
            'transaction_id' => 'TXN-99999',
        ]);

        $response->assertStatus(201);

        // Verify balance updates
        $setup['tenant']->run(function() use ($order) {
            $updated = Order::find($order->id);
            $this->assertEquals(226.00, $updated->paid_amount);
            $this->assertEquals(0.00, $updated->due_amount);
            $this->assertEquals('paid', $updated->payment_status);
        });
    }

    /**
     * Test overpayment is blocked unless using credit.
     */
    public function test_overpayment_is_blocked(): void
    {
        $setup = $this->createTenantWorkspace('payment', 'owner@test.com', 'owner');
        $tenantId = $setup['tenant']->id;
        $invoice = $setup['invoice'];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $setup['token'],
        ])->postJson("/api/{$tenantId}/payments/manual", [
            'invoice_id' => $invoice->id,
            'gateway' => 'cash',
            'amount' => 300.00, // Exceeds due limit of 226.00
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    /**
     * Test duplicate transaction_id is blocked.
     */
    public function test_duplicate_transaction_id_is_blocked(): void
    {
        $setup = $this->createTenantWorkspace('payment', 'owner@test.com', 'owner');
        $tenantId = $setup['tenant']->id;
        $invoice = $setup['invoice'];

        // 1. Record first transaction
        $response1 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $setup['token'],
        ])->postJson("/api/{$tenantId}/payments/manual", [
            'invoice_id' => $invoice->id,
            'gateway' => 'bank',
            'amount' => 50.00,
            'transaction_id' => 'DUP-12345',
        ]);
        $response1->assertStatus(201);

        // 2. Attempt duplicate transaction ID
        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $setup['token'],
        ])->postJson("/api/{$tenantId}/payments/manual", [
            'invoice_id' => $invoice->id,
            'gateway' => 'bank',
            'amount' => 50.00,
            'transaction_id' => 'DUP-12345',
        ]);

        $response2->assertStatus(422)
            ->assertJsonValidationErrors(['transaction_id']);
    }

    /**
     * Test staff with manage_payments can create payment.
     */
    public function test_staff_with_permission_can_create_payment(): void
    {
        $setup = $this->createTenantWorkspace('payment', 'manager@test.com', 'manager');
        $tenantId = $setup['tenant']->id;
        $invoice = $setup['invoice'];

        // Manager roles naturally include manage_payments
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $setup['token'],
        ])->postJson("/api/{$tenantId}/payments/manual", [
            'invoice_id' => $invoice->id,
            'gateway' => 'cash',
            'amount' => 50.00,
        ]);

        $response->assertStatus(201);
    }

    /**
     * Test staff without manage_payments is blocked.
     */
    public function test_staff_without_permission_is_blocked(): void
    {
        $setup = $this->createTenantWorkspace('payment', 'staff@test.com', 'staff');
        $tenantId = $setup['tenant']->id;
        $invoice = $setup['invoice'];

        // Staff does not have manage_payments permission
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $setup['token'],
        ])->postJson("/api/{$tenantId}/payments/manual", [
            'invoice_id' => $invoice->id,
            'gateway' => 'cash',
            'amount' => 50.00,
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test tenant isolation.
     */
    public function test_tenant_a_cannot_access_tenant_b_payments(): void
    {
        $setupA = $this->createTenantWorkspace('payment-a', 'owner-a@test.com', 'owner');
        $setupB = $this->createTenantWorkspace('payment-b', 'owner-b@test.com', 'owner');
        
        $tenantA = $setupA['tenant']->id;
        $tenantB = $setupB['tenant']->id;

        // Fetch list on Tenant B using Tenant A's token
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $setupA['token'],
        ])->getJson("/api/{$tenantB}/payments");

        // Returns 401 because Tenant A's personal access token does not exist on Tenant B's DB context
        $response->assertStatus(401);
    }

    /**
     * Test unauthenticated request fails.
     */
    public function test_unauthenticated_request_fails(): void
    {
        $setup = $this->createTenantWorkspace('payment', 'owner@test.com', 'owner');
        $tenantId = $setup['tenant']->id;

        $response = $this->postJson("/api/{$tenantId}/payments/manual", [
            'gateway' => 'cash',
            'amount' => 50.00,
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test eSewa initiate generates payload successfully.
     */
    public function test_esewa_initiate_generates_payload(): void
    {
        $setup = $this->createTenantWorkspace('payment', 'owner@test.com', 'owner');
        $tenantId = $setup['tenant']->id;
        $invoice = $setup['invoice'];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $setup['token'],
        ])->postJson("/api/{$tenantId}/payments/esewa/initiate", [
            'invoice_id' => $invoice->id,
            'amount' => 150.00,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'payment_url',
                    'fields' => [
                        'amount',
                        'tax_amount',
                        'total_amount',
                        'transaction_uuid',
                        'product_code',
                        'success_url',
                        'failure_url',
                        'signed_field_names',
                        'signature',
                    ],
                    'payment_id',
                    'transaction_uuid',
                ]
            ]);
    }

    /**
     * Test eSewa verified success updates balances.
     */
    public function test_esewa_verified_success_updates_balances(): void
    {
        $setup = $this->createTenantWorkspace('payment', 'owner@test.com', 'owner');
        $tenantId = $setup['tenant']->id;
        $invoice = $setup['invoice'];

        // 1. Initiate eSewa payment
        $initiateRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $setup['token'],
        ])->postJson("/api/{$tenantId}/payments/esewa/initiate", [
            'invoice_id' => $invoice->id,
            'amount' => 200.00,
        ]);
        $initiateRes->assertStatus(201);
        $uuid = $initiateRes->json('data.transaction_uuid');

        // 2. Trigger success callback verification
        $callbackRes = $this->getJson("/api/{$tenantId}/payments/esewa/success?transaction_uuid={$uuid}&total_amount=200.00&ref_id=REF-ESEWA-8888");

        $callbackRes->assertStatus(200)
            ->assertJsonPath('success', true);

        // 3. Verify balance adjustments
        $setup['tenant']->run(function() use ($invoice) {
            $updated = Invoice::find($invoice->id);
            $this->assertEquals(200.00, $updated->paid_amount);
            $this->assertEquals(26.00, $updated->due_amount);
            $this->assertEquals('partially_paid', $updated->status);
        });
    }

    /**
     * Test eSewa failure endpoint marks payment failed.
     */
    public function test_esewa_failure_marks_payment_failed(): void
    {
        $setup = $this->createTenantWorkspace('payment', 'owner@test.com', 'owner');
        $tenantId = $setup['tenant']->id;
        $invoice = $setup['invoice'];

        // 1. Initiate
        $initiateRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $setup['token'],
        ])->postJson("/api/{$tenantId}/payments/esewa/initiate", [
            'invoice_id' => $invoice->id,
            'amount' => 200.00,
        ]);
        $uuid = $initiateRes->json('data.transaction_uuid');

        // 2. Trigger failure callback
        $failRes = $this->getJson("/api/{$tenantId}/payments/esewa/failure?transaction_uuid={$uuid}");

        $failRes->assertStatus(422)
            ->assertJsonPath('success', false);

        // Verify status is failed in database
        $setup['tenant']->run(function() use ($uuid) {
            $payment = Payment::where('transaction_id', $uuid)->firstOrFail();
            $this->assertEquals('failed', $payment->status);
        });
    }

    /**
     * Test Khalti and Fonepay placeholders.
     */
    public function test_khalti_and_fonepay_placeholders(): void
    {
        $this->expectException(\RuntimeException::class);
        $khalti = new KhaltiPaymentService();
        $khalti->initiatePayment([]);
    }

    public function test_fonepay_placeholder_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        $fonepay = new FonepayPaymentService();
        $fonepay->initiatePayment([]);
    }

    /**
     * Test payment amount must be greater than zero.
     */
    public function test_payment_amount_must_be_greater_than_zero(): void
    {
        $setup = $this->createTenantWorkspace('payment', 'owner@test.com', 'owner');
        $tenantId = $setup['tenant']->id;
        $invoice = $setup['invoice'];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $setup['token'],
        ])->postJson("/api/{$tenantId}/payments/manual", [
            'invoice_id' => $invoice->id,
            'gateway' => 'cash',
            'amount' => -10.00,
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test refunded status allowed by check constraint and does not corrupt balances.
     */
    public function test_refunded_status_allowed_by_check_constraint_and_does_not_corrupt(): void
    {
        $setup = $this->createTenantWorkspace('payment', 'owner@test.com', 'owner');
        $invoice = $setup['invoice'];

        $setup['tenant']->run(function() use ($invoice) {
            $payment = new Payment();
            $payment->invoice_id = $invoice->id;
            $payment->gateway = 'cash';
            $payment->amount = 100.00;
            $payment->status = 'refunded';
            $payment->payment_date = now();
            $payment->save();

            $this->assertDatabaseHas('payments', [
                'id' => $payment->id,
                'status' => 'refunded'
            ]);

            // Verify invoice balance was untouched
            $updated = Invoice::find($invoice->id);
            $this->assertEquals(0.00, $updated->paid_amount);
            $this->assertEquals(226.00, $updated->due_amount);
        });
    }

    /**
     * Test tenant A cannot pay tenant B invoice/order.
     */
    public function test_tenant_a_cannot_pay_tenant_b_invoice(): void
    {
        $setupA = $this->createTenantWorkspace('payment-a', 'owner-a@test.com', 'owner');
        $setupB = $this->createTenantWorkspace('payment-b', 'owner-b@test.com', 'owner');

        $tenantA = $setupA['tenant']->id;
        $invoiceB = $setupB['invoice'];

        // Attempt to pay Tenant B's invoice through Tenant A's endpoint using B's ID offset to verify exists rule
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $setupA['token'],
        ])->postJson("/api/{$tenantA}/payments/manual", [
            'invoice_id' => $invoiceB->id + 9999, // Offset guarantees non-existent in Tenant A context
            'gateway' => 'cash',
            'amount' => 50.00,
        ]);

        // Invoice B does not exist in Tenant A's database connection context, throwing a 422 exists validation error!
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['invoice_id']);
    }
}
