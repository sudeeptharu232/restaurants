<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // PostgreSQL: ->after() is silently ignored but included for documentation clarity

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('super_admin');
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'is_active']);
        });
    }
};
