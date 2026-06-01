<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index('customer_id');
            $table->index('restaurant_table_id');
            $table->index(['status', 'created_at']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->index('order_id');
            $table->index('menu_item_id');
            $table->index('product_id');
            $table->index('service_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index('order_id');
            $table->index('customer_id');
            $table->index(['status', 'due_amount']);
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->index('invoice_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index('invoice_id');
            $table->index(['status', 'payment_date']);
        });

        Schema::table('kitchen_tickets', function (Blueprint $table) {
            $table->index('order_id');
        });

        Schema::table('kitchen_ticket_items', function (Blueprint $table) {
            $table->index('kitchen_ticket_id');
            $table->index('order_item_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index('category_id');
            $table->index(['track_stock', 'stock_quantity']);
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->index('category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropIndex(['category_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['category_id']);
            $table->dropIndex(['track_stock', 'stock_quantity']);
        });

        Schema::table('kitchen_ticket_items', function (Blueprint $table) {
            $table->dropIndex(['kitchen_ticket_id']);
            $table->dropIndex(['order_item_id']);
        });

        Schema::table('kitchen_tickets', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['invoice_id']);
            $table->dropIndex(['status', 'payment_date']);
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropIndex(['invoice_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
            $table->dropIndex(['customer_id']);
            $table->dropIndex(['status', 'due_amount']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
            $table->dropIndex(['menu_item_id']);
            $table->dropIndex(['product_id']);
            $table->dropIndex(['service_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['customer_id']);
            $table->dropIndex(['restaurant_table_id']);
            $table->dropIndex(['status', 'created_at']);
        });
    }
};
