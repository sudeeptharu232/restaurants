<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Upgrade invoices table
        Schema::table('invoices', function (Blueprint $table) {
            // Drop foreign key to make order_id nullable
            try {
                $table->dropForeign('invoices_order_id_foreign');
            } catch (\Exception $e) {
                // Ignore if constraint doesn't exist
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->change();
            
            // Add foreign key constraint back
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');

            if (!Schema::hasColumn('invoices', 'customer_id')) {
                $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            }
            if (!Schema::hasColumn('invoices', 'status')) {
                $table->string('status')->default('draft')->index();
            }
            if (!Schema::hasColumn('invoices', 'taxable_amount')) {
                $table->decimal('taxable_amount', 10, 2)->default(0);
            }
            if (!Schema::hasColumn('invoices', 'paid_amount')) {
                $table->decimal('paid_amount', 10, 2)->default(0);
            }
            if (!Schema::hasColumn('invoices', 'due_amount')) {
                $table->decimal('due_amount', 10, 2)->default(0);
            }
            if (!Schema::hasColumn('invoices', 'pdf_path')) {
                $table->string('pdf_path')->nullable();
            }
            if (!Schema::hasColumn('invoices', 'invoice_date')) {
                $table->date('invoice_date')->nullable(); // will be nullable first, then we can default/update
            }
            if (!Schema::hasColumn('invoices', 'due_date')) {
                $table->date('due_date')->nullable();
            }
            if (!Schema::hasColumn('invoices', 'notes')) {
                $table->text('notes')->nullable();
            }
        });

        // Drop legacy payment status constraint if present
        DB::statement("ALTER TABLE invoices DROP CONSTRAINT IF EXISTS chk_invoice_payment_status");

        // Add new check constraints for invoices status
        DB::statement("ALTER TABLE invoices DROP CONSTRAINT IF EXISTS chk_invoice_status");
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT chk_invoice_status CHECK (status IN ('draft', 'issued', 'paid', 'partially_paid', 'cancelled'))");

        // 2. Upgrade invoice_items table
        Schema::table('invoice_items', function (Blueprint $table) {
            if (!Schema::hasColumn('invoice_items', 'order_item_id')) {
                $table->foreignId('order_item_id')->nullable()->constrained('order_items')->onDelete('set null');
            }
            if (!Schema::hasColumn('invoice_items', 'product_id')) {
                $table->foreignId('product_id')->nullable()->constrained('products')->onDelete('set null');
            }
            if (!Schema::hasColumn('invoice_items', 'service_id')) {
                $table->foreignId('service_id')->nullable()->constrained('services')->onDelete('set null');
            }
            if (!Schema::hasColumn('invoice_items', 'menu_item_id')) {
                $table->foreignId('menu_item_id')->nullable()->constrained('menu_items')->onDelete('set null');
            }
            if (!Schema::hasColumn('invoice_items', 'discount_amount')) {
                $table->decimal('discount_amount', 10, 2)->default(0);
            }
            if (!Schema::hasColumn('invoice_items', 'vat_amount')) {
                $table->decimal('vat_amount', 10, 2)->default(0);
            }

            // Rename columns safely if they exist
            if (Schema::hasColumn('invoice_items', 'price') && !Schema::hasColumn('invoice_items', 'unit_price')) {
                $table->renameColumn('price', 'unit_price');
            }
            if (Schema::hasColumn('invoice_items', 'total') && !Schema::hasColumn('invoice_items', 'total_amount')) {
                $table->renameColumn('total', 'total_amount');
            }
        });
    }

    public function down(): void
    {
        // No down needed as we regenerate db in test suites
    }
};
