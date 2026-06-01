<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('cascade');
            $table->string('purchase_number')->unique()->index();
            $table->string('status')->default('pending')->index();
            $table->decimal('total_amount', 10, 2);
            $table->date('purchase_date')->index();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE purchases ADD CONSTRAINT chk_purchase_status CHECK (status IN ('pending', 'ordered', 'received', 'cancelled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
