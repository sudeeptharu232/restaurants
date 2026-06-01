<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_report_settings', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number')->index();
            $table->boolean('is_enabled')->default(false);
            $table->time('send_time')->default('20:00:00');
            $table->jsonb('report_types')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_report_settings');
    }
};
