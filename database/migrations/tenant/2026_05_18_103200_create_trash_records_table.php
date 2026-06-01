<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trash_records', function (Blueprint $table) {
            $table->id();
            $table->string('trashable_type')->index();
            $table->unsignedBigInteger('trashable_id')->index();
            $table->foreignId('deleted_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->jsonb('payload'); // Full model attributes backup payload
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trash_records');
    }
};
