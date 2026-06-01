<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique()->index();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            // PostgreSQL: use string + CHECK constraint instead of enum
            $table->string('billing_interval')->default('monthly');
            $table->jsonb('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("ALTER TABLE subscription_plans ADD CONSTRAINT chk_billing_interval CHECK (billing_interval IN ('monthly', 'yearly'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
