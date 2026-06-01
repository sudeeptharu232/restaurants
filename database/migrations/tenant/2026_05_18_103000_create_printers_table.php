<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('type')->default('thermal');
            $table->string('connection_string');
            $table->string('location')->default('billing')->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement("ALTER TABLE printers ADD CONSTRAINT chk_printer_type CHECK (type IN ('thermal', 'network', 'usb', 'bluetooth'))");
        DB::statement("ALTER TABLE printers ADD CONSTRAINT chk_printer_location CHECK (location IN ('kitchen', 'bar', 'reception', 'billing'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('printers');
    }
};
