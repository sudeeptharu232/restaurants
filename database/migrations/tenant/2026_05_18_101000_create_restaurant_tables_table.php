<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_space_id')->constrained('restaurant_spaces')->onDelete('cascade');
            $table->string('table_number')->index();
            $table->integer('capacity')->default(4);
            $table->string('status')->default('vacant')->index();
            $table->timestamps();

            // Ensure table numbers are unique within each specific space
            $table->unique(['restaurant_space_id', 'table_number']);
        });

        DB::statement("ALTER TABLE restaurant_tables ADD CONSTRAINT chk_table_status CHECK (status IN ('vacant', 'occupied', 'reserved'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_tables');
    }
};
