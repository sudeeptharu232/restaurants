<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kitchen_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->string('ticket_number')->index();
            $table->string('status')->default('pending')->index();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE kitchen_tickets ADD CONSTRAINT chk_kitchen_ticket_status CHECK (status IN ('pending', 'in_progress', 'completed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_tickets');
    }
};
