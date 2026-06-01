<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->foreignId('restaurant_table_id')->nullable()->constrained('restaurant_tables')->onDelete('set null');
            $table->string('guest_name')->index();
            $table->string('guest_phone')->index();
            $table->integer('party_size')->default(2);
            $table->dateTime('reservation_time')->index();
            $table->string('status')->default('confirmed')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE reservations ADD CONSTRAINT chk_reservation_status CHECK (status IN ('confirmed', 'seated', 'cancelled', 'no_show'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
