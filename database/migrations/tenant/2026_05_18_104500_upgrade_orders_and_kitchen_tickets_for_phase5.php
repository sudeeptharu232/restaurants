<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Upgrade orders table
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'payment_status')) {
                $table->string('payment_status')->default('unpaid')->index();
            }
            if (!Schema::hasColumn('orders', 'kitchen_status')) {
                $table->string('kitchen_status')->default('pending')->index();
            }
            if (!Schema::hasColumn('orders', 'service_charge_amount')) {
                $table->decimal('service_charge_amount', 10, 2)->default(0);
            }
            if (!Schema::hasColumn('orders', 'paid_amount')) {
                $table->decimal('paid_amount', 10, 2)->default(0);
            }
            if (!Schema::hasColumn('orders', 'due_amount')) {
                $table->decimal('due_amount', 10, 2)->default(0);
            }
            if (!Schema::hasColumn('orders', 'vat_amount')) {
                $table->decimal('vat_amount', 10, 2)->default(0);
            }
            if (!Schema::hasColumn('orders', 'delivery_address')) {
                $table->text('delivery_address')->nullable();
            }
        });

        // Drop existing constraints on orders if they exist
        DB::statement("ALTER TABLE orders DROP CONSTRAINT IF EXISTS chk_order_type");
        DB::statement("ALTER TABLE orders DROP CONSTRAINT IF EXISTS chk_order_status");

        // Add new check constraints for orders
        DB::statement("ALTER TABLE orders ADD CONSTRAINT chk_order_type CHECK (type IN ('dine_in', 'delivery', 'takeaway', 'pickup', 'reservation', 'qr_menu', 'regular'))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT chk_order_status CHECK (status IN ('draft', 'pending', 'preparing', 'ready', 'served', 'completed', 'cancelled'))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT chk_order_payment_status CHECK (payment_status IN ('unpaid', 'partially_paid', 'paid', 'refunded'))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT chk_order_kitchen_status CHECK (kitchen_status IN ('pending', 'preparing', 'ready', 'served'))");

        // 2. Upgrade order_items table
        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'name')) {
                $table->string('name')->nullable();
            }
            if (!Schema::hasColumn('order_items', 'unit_price')) {
                if (Schema::hasColumn('order_items', 'price')) {
                    $table->renameColumn('price', 'unit_price');
                } else {
                    $table->decimal('unit_price', 10, 2)->default(0);
                }
            }
            if (!Schema::hasColumn('order_items', 'discount_amount')) {
                $table->decimal('discount_amount', 10, 2)->default(0);
            }
            if (!Schema::hasColumn('order_items', 'vat_amount')) {
                $table->decimal('vat_amount', 10, 2)->default(0);
            }
            if (!Schema::hasColumn('order_items', 'total_amount')) {
                $table->decimal('total_amount', 10, 2)->default(0);
            }
            if (!Schema::hasColumn('order_items', 'kitchen_status')) {
                $table->string('kitchen_status')->default('pending')->index();
            }
        });

        // Add CHECK constraint on order_items kitchen_status
        DB::statement("ALTER TABLE order_items DROP CONSTRAINT IF EXISTS chk_item_kitchen_status");
        DB::statement("ALTER TABLE order_items ADD CONSTRAINT chk_item_kitchen_status CHECK (kitchen_status IN ('pending', 'preparing', 'ready', 'served', 'cancelled'))");

        // 3. Upgrade kitchen_tickets table
        Schema::table('kitchen_tickets', function (Blueprint $table) {
            if (!Schema::hasColumn('kitchen_tickets', 'printed_at')) {
                $table->timestamp('printed_at')->nullable();
            }
            if (!Schema::hasColumn('kitchen_tickets', 'type')) {
                $table->string('type')->default('KOT')->index();
            }
        });

        // Drop existing constraints on kitchen_tickets if they exist
        DB::statement("ALTER TABLE kitchen_tickets DROP CONSTRAINT IF EXISTS chk_kitchen_ticket_status");
        DB::statement("ALTER TABLE kitchen_tickets DROP CONSTRAINT IF EXISTS chk_kitchen_ticket_type");

        // Add new check constraints for kitchen_tickets
        DB::statement("ALTER TABLE kitchen_tickets ADD CONSTRAINT chk_kitchen_ticket_status CHECK (status IN ('pending', 'preparing', 'ready', 'served', 'cancelled'))");
        DB::statement("ALTER TABLE kitchen_tickets ADD CONSTRAINT chk_kitchen_ticket_type CHECK (type IN ('KOT', 'BOT'))");

        // 4. Upgrade kitchen_ticket_items table
        DB::statement("ALTER TABLE kitchen_ticket_items DROP CONSTRAINT IF EXISTS chk_kit_item_status");
        DB::statement("ALTER TABLE kitchen_ticket_items ADD CONSTRAINT chk_kit_item_status CHECK (status IN ('pending', 'preparing', 'ready', 'served', 'cancelled'))");
    }

    public function down(): void
    {
        // No down needed as we overwrite dynamic DBs
    }
};
