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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->onDelete('restrict');
            // PostgreSQL: use string + CHECK constraint instead of enum
            $table->string('status')->default('trialing')->index();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('trial_ends_at')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE subscriptions ADD CONSTRAINT chk_subscription_status CHECK (status IN ('active', 'trialing', 'canceled', 'expired'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
