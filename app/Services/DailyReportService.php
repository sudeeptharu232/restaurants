<?php

namespace App\Services;

use App\Models\DailyReport;
use App\Models\WhatsAppReportSetting;
use App\Models\BusinessSetting;
use Carbon\Carbon;

class DailyReportService
{
    protected AnalyticsService $analyticsService;
    protected WhatsAppBusinessService $whatsAppService;

    public function __construct(
        AnalyticsService $analyticsService,
        WhatsAppBusinessService $whatsAppService
    ) {
        $this->analyticsService = $analyticsService;
        $this->whatsAppService = $whatsAppService;
    }

    /**
     * Generate (or retrieve existing) daily report for a given date.
     * Uses firstOrCreate to prevent duplicates.
     *
     * @param string $date Y-m-d
     * @param bool $regenerate Force regeneration even if already exists
     */
    public function generateForDate(string $date, bool $regenerate = false): DailyReport
    {
        if (!$regenerate) {
            $existing = DailyReport::where('report_date', $date)->first();
            if ($existing) {
                return $existing;
            }
        }

        // Collect analytics data scoped to the given date
        $data = $this->analyticsService->getDailyReportDataForDate($date);

        $report = DailyReport::updateOrCreate(
            ['report_date' => $date],
            [
                'total_sales'    => $data['total_sales'],
                'total_orders'   => $data['total_orders'],
                'total_payments' => $data['total_payments'],
                'total_due'      => $data['total_due'],
                'total_expenses' => $data['total_expenses'],
                'net_revenue'    => $data['net_revenue'],
                'top_products'   => $data['top_products'],
                'low_stock_items'=> $data['low_stock_items'] ?? null,
                // Reset WhatsApp status on regeneration
                'whatsapp_status'=> 'pending',
                'sent_at'        => null,
                'error_message'  => null,
            ]
        );

        return $report;
    }

    /**
     * Format the WhatsApp message text for a daily report.
     */
    public function formatMessage(DailyReport $report, string $businessName): string
    {
        $date = $report->report_date instanceof \Carbon\Carbon
            ? $report->report_date->format('Y-m-d')
            : (string) $report->report_date;

        $lines = [];
        $lines[] = "🌟 *Growstro Daily Report*";
        $lines[] = "Business: {$businessName}";
        $lines[] = "Date: {$date}";
        $lines[] = "";
        $lines[] = "📊 *Sales Summary:*";
        $lines[] = "- Total Orders: " . (int) $report->total_orders;
        $lines[] = "- Total Sales: NPR " . number_format((float) $report->total_sales, 2);
        $lines[] = "- Payments Received: NPR " . number_format((float) $report->total_payments, 2);
        $lines[] = "- Due Amount: NPR " . number_format((float) $report->total_due, 2);
        $lines[] = "- Expenses: NPR " . number_format((float) $report->total_expenses, 2);
        $lines[] = "- Net Revenue: NPR " . number_format((float) $report->net_revenue, 2);

        // Top Products section
        $topProducts = $report->top_products ?? [];
        if (!empty($topProducts)) {
            $lines[] = "";
            $lines[] = "🏆 *Top Products:*";
            foreach (array_slice($topProducts, 0, 3) as $i => $product) {
                $name = $product['name'] ?? 'Unknown';
                $qty = number_format((float) ($product['total_quantity'] ?? 0), 0);
                $lines[] = ($i + 1) . ". {$name} - {$qty} sold";
            }
        }

        // Low Stock section
        $lowStock = $report->low_stock_items ?? [];
        if (!empty($lowStock)) {
            $lines[] = "";
            $lines[] = "⚠️ *Low Stock Alert:*";
            foreach ($lowStock as $item) {
                $name = $item['name'] ?? 'Unknown';
                $qty = number_format((float) ($item['stock_quantity'] ?? 0), 0);
                $lines[] = "- {$name} - {$qty} left";
            }
        }

        $lines[] = "";
        $lines[] = "_Powered by Growstro_";

        return implode("\n", $lines);
    }

    /**
     * Send a daily report via WhatsApp.
     * Updates report whatsapp_status to 'sent' or 'failed'.
     */
    public function sendViaWhatsApp(DailyReport $report, WhatsAppReportSetting $setting): array
    {
        $phone = $setting->getActivePhoneAttribute();
        if (empty($phone)) {
            $report->update([
                'whatsapp_status' => 'failed',
                'error_message'   => 'No WhatsApp phone number configured in settings.',
            ]);
            return ['success' => false, 'error' => 'No phone number configured'];
        }

        // Fetch business name from settings
        $businessName = 'Your Business';
        try {
            $bs = \App\Models\BusinessSetting::first();
            if ($bs && !empty($bs->business_name)) {
                $businessName = $bs->business_name;
            }
        } catch (\Exception $e) {
            // Ignore – use default name
        }

        $message = $this->formatMessage($report, $businessName);
        $result = $this->whatsAppService->sendTextMessage($phone, $message);

        if ($result['success']) {
            $report->update([
                'whatsapp_status' => 'sent',
                'sent_at'         => now(),
                'error_message'   => null,
            ]);
        } else {
            $report->update([
                'whatsapp_status' => 'failed',
                'error_message'   => $result['error'] ?? 'Unknown error',
            ]);
        }

        return $result;
    }
}
