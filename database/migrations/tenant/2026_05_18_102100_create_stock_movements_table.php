<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->onDelete('cascade');
            $table->decimal('quantity', 10, 2);
            $table->string('type')->default('in')->index();
            $table->string('reference')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT chk_stock_movement_type CHECK (type IN ('in', 'out', 'adjustment', 'return'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
