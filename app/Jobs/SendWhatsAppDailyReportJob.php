<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Models\DailyReport;
use App\Models\WhatsAppReportSetting;
use App\Services\DailyReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppDailyReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $reportDate
    ) {}

    /**
     * Execute the job.
     * Safe: Exceptions are caught and logged, queue worker continues.
     */
    public function handle(): void
    {
        try {
            $tenant = Tenant::find($this->tenantId);

            if (!$tenant) {
                Log::warning("[WhatsApp Job] Tenant not found: {$this->tenantId}");
                return;
            }

            $tenant->run(function () {
                // Load WhatsApp settings
                $setting = WhatsAppReportSetting::first();

                // Check enabled
                $isEnabled = $setting ? ($setting->enabled ?? $setting->is_enabled ?? false) : false;

                if (!$isEnabled) {
                    Log::info("[WhatsApp Job] Tenant {$this->tenantId}: WhatsApp reports disabled, skipping.");
                    return;
                }

                // Prevent re-sending already-sent reports
                $existing = DailyReport::where('report_date', $this->reportDate)
                    ->where('whatsapp_status', 'sent')
                    ->first();

                if ($existing) {
                    Log::info("[WhatsApp Job] Tenant {$this->tenantId}: Report for {$this->reportDate} already sent, skipping.");
                    return;
                }

                // Generate or retrieve the report
                /** @var DailyReportService $service */
                $service = app(DailyReportService::class);
                $report = $service->generateForDate($this->reportDate);

                // Send via WhatsApp
                $result = $service->sendViaWhatsApp($report, $setting);

                Log::info("[WhatsApp Job] Tenant {$this->tenantId}: Send result for {$this->reportDate}", $result);
            });
        } catch (\Throwable $e) {
            // Log but do NOT re-throw – prevents crashing the entire queue worker
            Log::error("[WhatsApp Job] Failed for tenant {$this->tenantId} date {$this->reportDate}: " . $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("[WhatsApp Job] Job permanently failed for tenant {$this->tenantId}: " . $exception->getMessage());
    }
}
