<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loyalty_account_id')->constrained('loyalty_accounts')->onDelete('cascade');
            $table->integer('points');
            $table->string('type')->default('earn')->index();
            $table->string('reference')->nullable()->index();
            $table->timestamp('created_at')->nullable();
        });

        DB::statement("ALTER TABLE loyalty_transactions ADD CONSTRAINT chk_loyalty_tx_type CHECK (type IN ('earn', 'redeem', 'adjustment'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
    }
};
