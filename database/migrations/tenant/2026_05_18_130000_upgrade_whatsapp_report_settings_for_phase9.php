<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_report_settings', function (Blueprint $table) {
            // Add owner_whatsapp_number if not present
            if (!Schema::hasColumn('whatsapp_report_settings', 'owner_whatsapp_number')) {
                $table->string('owner_whatsapp_number')->nullable()->after('id');
            }
            // Add enabled (separate from is_enabled for clarity)
            if (!Schema::hasColumn('whatsapp_report_settings', 'enabled')) {
                $table->boolean('enabled')->default(false)->after('owner_whatsapp_number');
            }
            // Add include flags
            if (!Schema::hasColumn('whatsapp_report_settings', 'include_sales_summary')) {
                $table->boolean('include_sales_summary')->default(true);
            }
            if (!Schema::hasColumn('whatsapp_report_settings', 'include_payment_summary')) {
                $table->boolean('include_payment_summary')->default(true);
            }
            if (!Schema::hasColumn('whatsapp_report_settings', 'include_due_summary')) {
                $table->boolean('include_due_summary')->default(true);
            }
            if (!Schema::hasColumn('whatsapp_report_settings', 'include_top_products')) {
                $table->boolean('include_top_products')->default(true);
            }
            if (!Schema::hasColumn('whatsapp_report_settings', 'include_inventory_alerts')) {
                $table->boolean('include_inventory_alerts')->default(true);
            }
            // Add timezone
            if (!Schema::hasColumn('whatsapp_report_settings', 'timezone')) {
                $table->string('timezone')->default('Asia/Kathmandu');
            }
        });

        // Ensure send_time default is 22:00 not 20:00
        DB::statement("ALTER TABLE whatsapp_report_settings ALTER COLUMN send_time SET DEFAULT '22:00:00'");
        DB::statement("ALTER TABLE whatsapp_report_settings ALTER COLUMN phone_number DROP NOT NULL");
    }

    public function down(): void
    {
        Schema::table('whatsapp_report_settings', function (Blueprint $table) {
            $table->dropColumn([
                'owner_whatsapp_number', 'enabled',
                'include_sales_summary', 'include_payment_summary',
                'include_due_summary', 'include_top_products',
                'include_inventory_alerts', 'timezone',
            ]);
        });
    }
};
