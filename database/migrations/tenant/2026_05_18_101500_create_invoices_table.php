<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->string('invoice_number')->unique()->index();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('vat_amount', 10, 2)->default(0);
            $table->decimal('service_charge', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('payment_status')->default('unpaid')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("ALTER TABLE invoices ADD CONSTRAINT chk_invoice_payment_status CHECK (payment_status IN ('unpaid', 'paid', 'partially_paid'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
