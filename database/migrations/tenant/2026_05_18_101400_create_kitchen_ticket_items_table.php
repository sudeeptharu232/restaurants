<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kitchen_ticket_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kitchen_ticket_id')->constrained('kitchen_tickets')->onDelete('cascade');
            $table->foreignId('order_item_id')->constrained('order_items')->onDelete('cascade');
            $table->decimal('quantity', 10, 2);
            $table->string('status')->default('pending')->index();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE kitchen_ticket_items ADD CONSTRAINT chk_kit_item_status CHECK (status IN ('pending', 'preparing', 'completed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_ticket_items');
    }
};
