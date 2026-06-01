<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bind strictly to central database connection.
     */
    protected $connection = 'central';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->integer('duration_days')->default(30);
            $table->integer('max_staff')->nullable();
            $table->integer('max_products')->nullable();
            $table->integer('max_invoices_per_month')->nullable();
            $table->boolean('whatsapp_reports_enabled')->default(false);
            $table->boolean('analytics_enabled')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn([
                'duration_days',
                'max_staff',
                'max_products',
                'max_invoices_per_month',
                'whatsapp_reports_enabled',
                'analytics_enabled',
            ]);
        });
    }
};
