<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->foreignId('restaurant_table_id')->nullable()->constrained('restaurant_tables')->onDelete('set null');
            $table->string('order_number')->unique()->index();
            $table->string('type')->default('dine_in')->index();
            $table->string('status')->default('pending')->index();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("ALTER TABLE orders ADD CONSTRAINT chk_order_type CHECK (type IN ('dine_in', 'takeaway', 'delivery'))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT chk_order_status CHECK (status IN ('pending', 'preparing', 'ready', 'completed', 'cancelled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
