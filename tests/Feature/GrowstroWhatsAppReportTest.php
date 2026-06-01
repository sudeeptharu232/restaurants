<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppDailyReportJob;
use App\Models\DailyReport;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsAppReportSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GrowstroWhatsAppReportTest extends TestCase
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
    // WHATSAPP SETTINGS TESTS
    // =========================================================

    public function test_owner_can_view_whatsapp_settings(): void
    {
        $setup = $this->createTenantWorkspace('wa-view', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->getJson("/api/{$tid}/whatsapp-settings");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'data' => [
                'enabled', 'owner_whatsapp_number', 'send_time', 'timezone',
                'include_sales_summary', 'include_payment_summary',
                'include_due_summary', 'include_top_products', 'include_inventory_alerts',
            ]]);
    }

    public function test_owner_can_update_whatsapp_settings(): void
    {
        $setup = $this->createTenantWorkspace('wa-update', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->putJson("/api/{$tid}/whatsapp-settings", [
                'enabled'               => true,
                'owner_whatsapp_number' => '9841234567',
                'send_time'             => '22:00',
                'timezone'              => 'Asia/Kathmandu',
                'include_sales_summary' => true,
                'include_top_products'  => true,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.send_time', '22:00:00');
    }

    public function test_invalid_phone_number_fails_validation(): void
    {
        $setup = $this->createTenantWorkspace('wa-phone', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->putJson("/api/{$tid}/whatsapp-settings", [
                'enabled'               => true,
                // missing owner_whatsapp_number when enabled=true
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['owner_whatsapp_number']);
    }

    public function test_invalid_send_time_fails_validation(): void
    {
        $setup = $this->createTenantWorkspace('wa-time', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->putJson("/api/{$tid}/whatsapp-settings", [
                'enabled'               => true,
                'owner_whatsapp_number' => '9841234567',
                'send_time'             => 'not-a-time',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['send_time']);
    }

    public function test_staff_without_manage_settings_is_blocked(): void
    {
        $setup = $this->createTenantWorkspace('wa-staff', 'staff@test.com', 'staff');
        $tid   = $setup['tenant']->id;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->getJson("/api/{$tid}/whatsapp-settings");

        $response->assertStatus(403);
    }

    public function test_manager_with_manage_settings_can_update_settings(): void
    {
        $setup = $this->createTenantWorkspace('wa-mgr', 'manager@test.com', 'manager');
        $tid   = $setup['tenant']->id;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->putJson("/api/{$tid}/whatsapp-settings", [
                'enabled'               => false,
                'owner_whatsapp_number' => null,
            ]);

        $response->assertStatus(200);
    }

    public function test_unauthenticated_request_fails(): void
    {
        $setup = $this->createTenantWorkspace('wa-unauth', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;

        $response = $this->getJson("/api/{$tid}/whatsapp-settings");
        $response->assertStatus(401);
    }

    public function test_suspended_user_is_blocked(): void
    {
        $setup = $this->createTenantWorkspace('wa-susp', 'owner@test.com', 'owner', false);
        $tid   = $setup['tenant']->id;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->getJson("/api/{$tid}/whatsapp-settings");

        $response->assertStatus(403);
    }

    // =========================================================
    // DAILY REPORT GENERATION TESTS
    // =========================================================

    public function test_owner_can_generate_daily_report(): void
    {
        $setup = $this->createTenantWorkspace('rpt-gen', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;

        $date = now()->toDateString();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->postJson("/api/{$tid}/daily-reports/generate", ['report_date' => $date]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => [
                'id', 'report_date', 'total_sales', 'total_orders',
                'total_payments', 'total_due', 'total_expenses', 'net_revenue',
                'top_products', 'low_stock_items', 'whatsapp_status',
            ]]);

        $response->assertJsonPath('data.whatsapp_status', 'pending');
    }

    public function test_daily_report_uses_analytics_totals_correctly(): void
    {
        $setup = $this->createTenantWorkspace('rpt-totals', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;

        $setup['tenant']->run(function () {
            // Seed a completed order
            $order = Order::create([
                'order_number' => 'ORD-WA-001',
                'type'         => 'dine_in',
                'status'       => 'completed',
                'subtotal'     => 200.00,
                'total'        => 200.00,
            ]);
            OrderItem::create([
                'order_id'     => $order->id,
                'name'         => 'Dal Bhat',
                'quantity'     => 2,
                'unit_price'   => 100.00,
                'total_amount' => 200.00,
            ]);
            Expense::create([
                'title'        => 'Vegetables',
                'amount'       => 50.00,
                'expense_date' => now()->toDateString(),
            ]);
        });

        $date     = now()->toDateString();
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->postJson("/api/{$tid}/daily-reports/generate", ['report_date' => $date]);

        $response->assertStatus(200);
        $this->assertEquals(200.00, $response->json('data.total_sales'));
        $this->assertEquals(50.00, $response->json('data.total_expenses'));
        $this->assertEquals(150.00, $response->json('data.net_revenue'));
    }

    public function test_empty_day_report_generates_safely_with_zeros(): void
    {
        $setup = $this->createTenantWorkspace('rpt-empty', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;

        $date     = '2020-01-01'; // Past date with no data
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->postJson("/api/{$tid}/daily-reports/generate", ['report_date' => $date]);

        $response->assertStatus(200);
        $this->assertEquals(0.00, $response->json('data.total_sales'));
        $this->assertEquals(0, $response->json('data.total_orders'));
        $this->assertEmpty($response->json('data.top_products'));
    }

    public function test_duplicate_daily_report_for_same_date_is_reused(): void
    {
        $setup = $this->createTenantWorkspace('rpt-dup', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;
        $date  = now()->toDateString();

        // Generate once
        $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->postJson("/api/{$tid}/daily-reports/generate", ['report_date' => $date]);

        // Generate again – must return same record
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->postJson("/api/{$tid}/daily-reports/generate", ['report_date' => $date]);

        $response->assertStatus(200);

        // Verify only one record in DB
        $setup['tenant']->run(function () use ($date) {
            $count = DailyReport::where('report_date', $date)->count();
            $this->assertEquals(1, $count);
        });
    }

    public function test_regenerate_flag_forces_new_report(): void
    {
        $setup = $this->createTenantWorkspace('rpt-regen', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;
        $date  = now()->toDateString();

        // Generate once
        $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->postJson("/api/{$tid}/daily-reports/generate", ['report_date' => $date]);

        // Regenerate
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->postJson("/api/{$tid}/daily-reports/generate", ['report_date' => $date, 'regenerate' => true]);

        $response->assertStatus(200)
            ->assertJsonPath('data.whatsapp_status', 'pending');
    }

    public function test_owner_can_list_daily_reports(): void
    {
        $setup = $this->createTenantWorkspace('rpt-list', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;

        // Generate a report first
        $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->postJson("/api/{$tid}/daily-reports/generate", ['report_date' => now()->toDateString()]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->getJson("/api/{$tid}/daily-reports");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['data']]);
    }

    // =========================================================
    // WHATSAPP SEND TESTS (HTTP MOCKED)
    // =========================================================

    public function test_whatsapp_api_success_marks_report_sent(): void
    {
        // Mock WhatsApp Cloud API to return success
        Http::fake([
            '*graph.facebook.com*' => Http::response([
                'messages' => [['id' => 'wamid.test123']]
            ], 200),
        ]);

        // Set real credentials in config so sandbox mode is disabled
        config([
            'services.whatsapp.api_url'         => 'https://graph.facebook.com/v19.0',
            'services.whatsapp.phone_number_id'  => 'test_phone_id',
            'services.whatsapp.access_token'     => 'test_access_token',
        ]);

        $setup = $this->createTenantWorkspace('wa-success', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;

        // Create report and settings
        $reportId = null;
        $setup['tenant']->run(function () use (&$reportId) {
            $report = DailyReport::create([
                'report_date'     => now()->toDateString(),
                'total_sales'     => 500.00,
                'total_orders'    => 5,
                'total_payments'  => 500.00,
                'total_due'       => 0.00,
                'total_expenses'  => 100.00,
                'net_revenue'     => 400.00,
                'whatsapp_status' => 'pending',
            ]);
            $reportId = $report->id;

            WhatsAppReportSetting::create([
                'owner_whatsapp_number' => '9841234567',
                'enabled'               => true,
                'send_time'             => '22:00:00',
                'timezone'              => 'Asia/Kathmandu',
            ]);
        });

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->postJson("/api/{$tid}/daily-reports/{$reportId}/send-whatsapp");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $setup['tenant']->run(function () use ($reportId) {
            $report = DailyReport::find($reportId);
            $this->assertEquals('sent', $report->whatsapp_status);
            $this->assertNotNull($report->sent_at);
        });
    }

    public function test_whatsapp_api_failure_marks_report_failed(): void
    {
        Http::fake([
            '*graph.facebook.com*' => Http::response([
                'error' => ['message' => 'Invalid token']
            ], 401),
        ]);

        config([
            'services.whatsapp.api_url'         => 'https://graph.facebook.com/v19.0',
            'services.whatsapp.phone_number_id'  => 'test_phone_id',
            'services.whatsapp.access_token'     => 'bad_token',
        ]);

        $setup = $this->createTenantWorkspace('wa-fail', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;

        $reportId = null;
        $setup['tenant']->run(function () use (&$reportId) {
            $report = DailyReport::create([
                'report_date'     => now()->subDay()->toDateString(),
                'total_sales'     => 0.00,
                'total_orders'    => 0,
                'whatsapp_status' => 'pending',
            ]);
            $reportId = $report->id;

            WhatsAppReportSetting::create([
                'owner_whatsapp_number' => '9841234567',
                'enabled'               => true,
                'send_time'             => '22:00:00',
            ]);
        });

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->postJson("/api/{$tid}/daily-reports/{$reportId}/send-whatsapp");

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $setup['tenant']->run(function () use ($reportId) {
            $report = DailyReport::find($reportId);
            $this->assertEquals('failed', $report->whatsapp_status);
            $this->assertNotNull($report->error_message);
        });
    }

    public function test_failed_api_stores_error_message(): void
    {
        Http::fake([
            '*graph.facebook.com*' => Http::response([
                'error' => ['message' => 'Rate limit exceeded']
            ], 429),
        ]);

        config([
            'services.whatsapp.api_url'         => 'https://graph.facebook.com/v19.0',
            'services.whatsapp.phone_number_id'  => 'test_phone_id',
            'services.whatsapp.access_token'     => 'test_token',
        ]);

        $setup = $this->createTenantWorkspace('wa-errmsg', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;

        $reportId = null;
        $setup['tenant']->run(function () use (&$reportId) {
            $report = DailyReport::create([
                'report_date'     => now()->subDays(2)->toDateString(),
                'total_sales'     => 0.00,
                'total_orders'    => 0,
                'whatsapp_status' => 'pending',
            ]);
            $reportId = $report->id;

            WhatsAppReportSetting::create([
                'owner_whatsapp_number' => '9841234567',
                'enabled'               => true,
                'send_time'             => '22:00:00',
            ]);
        });

        $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->postJson("/api/{$tid}/daily-reports/{$reportId}/send-whatsapp");

        $setup['tenant']->run(function () use ($reportId) {
            $report = DailyReport::find($reportId);
            $this->assertEquals('failed', $report->whatsapp_status);
            $this->assertStringContainsString('Rate limit', $report->error_message);
        });
    }

    public function test_already_sent_report_cannot_be_resent(): void
    {
        $setup = $this->createTenantWorkspace('wa-nosend', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;

        $reportId = null;
        $setup['tenant']->run(function () use (&$reportId) {
            $report = DailyReport::create([
                'report_date'     => now()->toDateString(),
                'total_sales'     => 0.00,
                'total_orders'    => 0,
                'whatsapp_status' => 'sent',
                'sent_at'         => now(),
            ]);
            $reportId = $report->id;

            WhatsAppReportSetting::create([
                'owner_whatsapp_number' => '9841234567',
                'enabled'               => true,
                'send_time'             => '22:00:00',
            ]);
        });

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setup['token']])
            ->postJson("/api/{$tid}/daily-reports/{$reportId}/send-whatsapp");

        $response->assertStatus(409); // Conflict
    }

    // =========================================================
    // QUEUE JOB TESTS
    // =========================================================

    public function test_queue_job_dispatches_in_tenant_context(): void
    {
        Queue::fake();

        $setup = $this->createTenantWorkspace('wa-queue', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;

        // Directly dispatch the job
        SendWhatsAppDailyReportJob::dispatch($tid, now()->toDateString());

        Queue::assertPushed(SendWhatsAppDailyReportJob::class, function ($job) use ($tid) {
            return $job->tenantId === $tid;
        });
    }

    public function test_queue_job_skips_disabled_tenant(): void
    {
        $setup = $this->createTenantWorkspace('wa-disabled', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;

        // WhatsApp not enabled – no settings created
        $date = now()->toDateString();

        // Run the job directly (should not throw)
        $job = new SendWhatsAppDailyReportJob($tid, $date);
        $job->handle();

        // Verify no report was created
        $setup['tenant']->run(function () use ($date) {
            $count = DailyReport::where('report_date', $date)->count();
            $this->assertEquals(0, $count);
        });
    }

    // =========================================================
    // TENANT ISOLATION TESTS
    // =========================================================

    public function test_tenant_a_cannot_access_tenant_b_reports(): void
    {
        $setupA = $this->createTenantWorkspace('wa-iso-a', 'owner-a@test.com', 'owner');
        $setupB = $this->createTenantWorkspace('wa-iso-b', 'owner-b@test.com', 'owner');

        $tidB = $setupB['tenant']->id;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $setupA['token']])
            ->getJson("/api/{$tidB}/daily-reports");

        $response->assertStatus(401); // Tenant B DB doesn't know about Tenant A's token
    }

    // =========================================================
    // CREDENTIALS NOT HARDCODED TEST
    // =========================================================

    public function test_whatsapp_credentials_not_hardcoded(): void
    {
        // In sandbox mode (no env vars), the service must return sandbox=true and success=false
        config([
            'services.whatsapp.api_url'         => '',
            'services.whatsapp.phone_number_id'  => '',
            'services.whatsapp.access_token'     => '',
        ]);

        $service = new \App\Services\WhatsAppBusinessService();

        $this->assertTrue($service->isSandbox());

        $result = $service->sendTextMessage('9841234567', 'Test message');
        $this->assertFalse($result['success']);
        $this->assertTrue($result['sandbox']);
        $this->assertNotNull($result['error']);
    }

    // =========================================================
    // PHONE FORMATTING TEST
    // =========================================================

    public function test_phone_number_formatting_is_correct(): void
    {
        $service = new \App\Services\WhatsAppBusinessService();

        // Nepal 10-digit
        $this->assertStringStartsWith('977', $service->formatPhone('9841234567'));

        // Already has country code
        $this->assertEquals('9779841234567', $service->formatPhone('+977-984-1234567'));
    }

    // =========================================================
    // ARTISAN COMMAND TESTS
    // =========================================================

    public function test_artisan_command_dispatches_job_on_matching_time(): void
    {
        Queue::fake();

        $setup = $this->createTenantWorkspace('cmd-disp', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;

        // Set the current time to exactly send_time
        $sendTime = '22:00:00';
        $currentTime = '2026-05-18 22:01:00'; // within the 5-minute dispatch window

        \Carbon\Carbon::setTestNow(\Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $currentTime, 'Asia/Kathmandu'));

        $setup['tenant']->run(function () use ($sendTime) {
            WhatsAppReportSetting::create([
                'owner_whatsapp_number' => '9841234567',
                'enabled'               => true,
                'send_time'             => $sendTime,
                'timezone'              => 'Asia/Kathmandu',
            ]);
        });

        // Run the console command
        $this->artisan('reports:send-daily-whatsapp', [
            '--tenant' => $tid,
        ])->assertExitCode(0);

        // Verify that the job was dispatched
        Queue::assertPushed(SendWhatsAppDailyReportJob::class, function ($job) use ($tid) {
            return $job->tenantId === $tid;
        });

        \Carbon\Carbon::setTestNow(); // Clean up test now
    }

    public function test_artisan_command_skips_when_not_in_time_window(): void
    {
        Queue::fake();

        $setup = $this->createTenantWorkspace('cmd-skip', 'owner@test.com', 'owner');
        $tid   = $setup['tenant']->id;

        // Set the current time way outside the send_time
        $sendTime = '22:00:00';
        $currentTime = '2026-05-18 10:00:00'; // completely outside the dispatch window

        \Carbon\Carbon::setTestNow(\Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $currentTime, 'Asia/Kathmandu'));

        $setup['tenant']->run(function () use ($sendTime) {
            WhatsAppReportSetting::create([
                'owner_whatsapp_number' => '9841234567',
                'enabled'               => true,
                'send_time'             => $sendTime,
                'timezone'              => 'Asia/Kathmandu',
            ]);
        });

        // Run the console command
        $this->artisan('reports:send-daily-whatsapp', [
            '--tenant' => $tid,
        ])->assertExitCode(0);

        // Verify that no job was dispatched
        Queue::assertNotPushed(SendWhatsAppDailyReportJob::class);

        \Carbon\Carbon::setTestNow(); // Clean up test now
    }
}

