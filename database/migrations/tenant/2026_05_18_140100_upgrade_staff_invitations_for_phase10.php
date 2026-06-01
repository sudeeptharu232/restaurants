<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_invitations', function (Blueprint $table) {
            if (!Schema::hasColumn('staff_invitations', 'phone')) {
                $table->string('phone')->nullable()->index();
            }
            if (!Schema::hasColumn('staff_invitations', 'permissions')) {
                $table->jsonb('permissions')->nullable();
            }
            if (!Schema::hasColumn('staff_invitations', 'status')) {
                $table->string('status')->default('pending')->index();
            }
        });

        // Add CHECK constraint for status
        DB::statement("ALTER TABLE staff_invitations DROP CONSTRAINT IF EXISTS chk_staff_invitations_status");
        DB::statement("ALTER TABLE staff_invitations ADD CONSTRAINT chk_staff_invitations_status CHECK (status IN ('pending', 'accepted', 'expired', 'cancelled'))");
    }

    public function down(): void
    {
        Schema::table('staff_invitations', function (Blueprint $table) {
            $table->dropColumn(['phone', 'permissions', 'status']);
        });
    }
};
