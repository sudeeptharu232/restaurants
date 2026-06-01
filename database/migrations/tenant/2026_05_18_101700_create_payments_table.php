<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade');
            $table->string('payment_method')->default('cash')->index();
            $table->decimal('amount', 10, 2);
            $table->string('transaction_id')->nullable()->index();
            $table->dateTime('payment_date');
            $table->timestamps();
        });

        DB::statement("ALTER TABLE payments ADD CONSTRAINT chk_payment_method CHECK (payment_method IN ('cash', 'card', 'esewa', 'khalti', 'fonepay', 'other'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
