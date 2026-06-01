<?php

namespace App\Console\Commands;

use App\Jobs\SendWhatsAppDailyReportJob;
use App\Models\Tenant;
use App\Models\DailyReport;
use App\Models\WhatsAppReportSetting;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendDailyWhatsAppReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'reports:send-daily-whatsapp
                            {--date= : Override the report date (Y-m-d). Defaults to today.}
                            {--tenant= : Run for a specific tenant ID only.}';

    /**
     * The console command description.
     */
    protected $description = 'Dispatch SendWhatsAppDailyReportJob for all enabled tenants whose send_time matches current time.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $reportDate = $this->option('date') ?? Carbon::now('Asia/Kathmandu')->toDateString();
        $specificTenant = $this->option('tenant');

        $this->info("[WhatsApp Scheduler] Running for date: {$reportDate}");

        $tenantQuery = Tenant::query();
        if ($specificTenant) {
            $tenantQuery->where('id', $specificTenant);
        }

        $tenants = $tenantQuery->get();

        if ($tenants->isEmpty()) {
            $this->warn('[WhatsApp Scheduler] No tenants found.');
            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            try {
                $this->processTenant($tenant, $reportDate);
            } catch (\Throwable $e) {
                // One tenant failure must NOT affect others
                $this->error("[WhatsApp Scheduler] Error for tenant {$tenant->id}: " . $e->getMessage());
                Log::error("[WhatsApp Scheduler] Tenant {$tenant->id} error: " . $e->getMessage());
            }
        }

        $this->info('[WhatsApp Scheduler] Done.');
        return self::SUCCESS;
    }

    /**
     * Process a single tenant: check settings, time window, and dispatch job.
     */
    protected function processTenant(Tenant $tenant, string $reportDate): void
    {
        $tenant->run(function () use ($tenant, $reportDate) {
            $setting = WhatsAppReportSetting::first();

            if (!$setting) {
                return; // No settings configured yet
            }

            $isEnabled = $setting->enabled ?? $setting->is_enabled ?? false;
            if (!$isEnabled) {
                return; // Disabled for this tenant
            }

            // Determine timezone for this tenant
            $timezone = $setting->timezone ?? 'Asia/Kathmandu';

            // Get current time in tenant's timezone
            try {
                $now = Carbon::now($timezone);
            } catch (\Exception $e) {
                $now = Carbon::now('Asia/Kathmandu');
            }

            // Parse configured send_time
            $sendTime = $setting->send_time ?? '22:00:00';
            [$sendHour, $sendMinute] = array_pad(explode(':', $sendTime), 2, '00');

            $currentHour   = (int) $now->format('H');
            $currentMinute = (int) $now->format('i');

            // Check if current time is within the 5-minute dispatch window
            $inWindow = ($currentHour === (int) $sendHour)
                && ($currentMinute >= (int) $sendMinute)
                && ($currentMinute < ((int) $sendMinute + 5));

            if (!$inWindow) {
                return; // Not time yet
            }

            // Prevent duplicate dispatch: skip if already sent for this date
            $alreadySent = DailyReport::where('report_date', $reportDate)
                ->where('whatsapp_status', 'sent')
                ->exists();

            if ($alreadySent) {
                $this->line("[WhatsApp Scheduler] Tenant {$tenant->id}: already sent for {$reportDate}, skipping.");
                return;
            }

            // Dispatch the queue job
            SendWhatsAppDailyReportJob::dispatch($tenant->id, $reportDate);
            $this->info("[WhatsApp Scheduler] Dispatched job for tenant {$tenant->id} date {$reportDate}");
            Log::info("[WhatsApp Scheduler] Dispatched job", ['tenant' => $tenant->id, 'date' => $reportDate]);
        });
    }
}
