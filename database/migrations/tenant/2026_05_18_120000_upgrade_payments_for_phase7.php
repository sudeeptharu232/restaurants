<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Alter invoice_id to be nullable and add new fields
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('invoice_id')->nullable()->change();
            
            // Add order_id and customer_id
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            
            // Add gateway, status, gateway_response, and notes
            $table->string('gateway')->default('cash')->index();
            $table->string('status')->default('pending')->index();
            $table->jsonb('gateway_response')->nullable();
            $table->text('notes')->nullable();

            // Clean up: drop old legacy column
            $table->dropColumn('payment_method');
        });

        // 2. Drop old check constraint if it exists (ignoring errors if not exists)
        try {
            DB::statement("ALTER TABLE payments DROP CONSTRAINT IF EXISTS chk_payment_method");
        } catch (\Exception $e) {
            // Ignore
        }

        // 3. Add new check constraints for PostgreSQL
        DB::statement("ALTER TABLE payments ADD CONSTRAINT chk_payment_gateway CHECK (gateway IN ('cash', 'bank', 'qr', 'credit', 'esewa', 'khalti', 'fonepay'))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT chk_payment_status CHECK (status IN ('pending', 'successful', 'failed', 'refunded'))");
    }

    public function down(): void
    {
        try {
            DB::statement("ALTER TABLE payments DROP CONSTRAINT IF EXISTS chk_payment_gateway");
            DB::statement("ALTER TABLE payments DROP CONSTRAINT IF EXISTS chk_payment_status");
        } catch (\Exception $e) {
            // Ignore
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropColumn(['order_id', 'customer_id', 'gateway', 'status', 'gateway_response', 'notes']);
            $table->unsignedBigInteger('invoice_id')->nullable(false)->change();
            
            // Recreate old column
            $table->string('payment_method')->default('cash')->index();
        });

        DB::statement("ALTER TABLE payments ADD CONSTRAINT chk_payment_method CHECK (payment_method IN ('cash', 'card', 'esewa', 'khalti', 'fonepay', 'other'))");
    }
};
