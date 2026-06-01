<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('daybook_closings', function (Blueprint $table) {
            $table->id();
            $table->date('closing_date')->unique()->index();
            $table->decimal('opening_balance', 10, 2)->default(0);
            $table->decimal('cash_sales', 10, 2)->default(0);
            $table->decimal('digital_sales', 10, 2)->default(0);
            $table->decimal('expenses', 10, 2)->default(0);
            $table->decimal('expected_balance', 10, 2)->default(0);
            $table->decimal('actual_balance', 10, 2)->default(0);
            $table->decimal('discrepancy', 10, 2)->default(0);
            $table->foreignId('closed_by_user_id')->constrained('users')->onDelete('restrict');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daybook_closings');
    }
};
