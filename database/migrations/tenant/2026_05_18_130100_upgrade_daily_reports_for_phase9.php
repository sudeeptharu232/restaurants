<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_reports', function (Blueprint $table) {
            if (!Schema::hasColumn('daily_reports', 'total_payments')) {
                $table->decimal('total_payments', 10, 2)->default(0)->after('total_orders');
            }
            if (!Schema::hasColumn('daily_reports', 'total_due')) {
                $table->decimal('total_due', 10, 2)->default(0)->after('total_payments');
            }
            if (!Schema::hasColumn('daily_reports', 'net_revenue')) {
                $table->decimal('net_revenue', 10, 2)->default(0)->after('total_due');
            }
            if (!Schema::hasColumn('daily_reports', 'top_products')) {
                $table->jsonb('top_products')->nullable()->after('net_revenue');
            }
            if (!Schema::hasColumn('daily_reports', 'low_stock_items')) {
                $table->jsonb('low_stock_items')->nullable()->after('top_products');
            }
            if (!Schema::hasColumn('daily_reports', 'whatsapp_status')) {
                $table->string('whatsapp_status')->default('pending')->index()->after('low_stock_items');
            }
            if (!Schema::hasColumn('daily_reports', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('whatsapp_status');
            }
            if (!Schema::hasColumn('daily_reports', 'error_message')) {
                $table->text('error_message')->nullable()->after('sent_at');
            }
        });

        // Add CHECK constraint for whatsapp_status
        DB::statement("ALTER TABLE daily_reports DROP CONSTRAINT IF EXISTS chk_daily_report_whatsapp_status");
        DB::statement("ALTER TABLE daily_reports ADD CONSTRAINT chk_daily_report_whatsapp_status CHECK (whatsapp_status IN ('pending', 'sent', 'failed'))");
    }

    public function down(): void
    {
        Schema::table('daily_reports', function (Blueprint $table) {
            $table->dropColumn([
                'total_payments', 'total_due', 'net_revenue',
                'top_products', 'low_stock_items',
                'whatsapp_status', 'sent_at', 'error_message',
            ]);
        });
    }
};
