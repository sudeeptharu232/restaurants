<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Backward-compatible plans
        SubscriptionPlan::updateOrCreate(
            ['slug' => 'basic-plan'],
            [
                'name' => 'Basic Plan',
                'description' => 'Perfect for small local tea shops and grocers in Nepal.',
                'price' => 1000.00,
                'billing_interval' => 'monthly',
                'duration_days' => 30,
                'max_staff' => 3,
                'max_products' => 50,
                'max_invoices_per_month' => 100,
                'whatsapp_reports_enabled' => false,
                'analytics_enabled' => true,
                'features' => [
                    'billing',
                    'inventory_lite',
                    'receipt_printer',
                ],
                'is_active' => true,
            ]
        );

        SubscriptionPlan::updateOrCreate(
            ['slug' => 'enterprise-plan'],
            [
                'name' => 'Enterprise Plan',
                'description' => 'Complete suite for premium boutique hotels and fine dining restaurants.',
                'price' => 5000.00,
                'billing_interval' => 'monthly',
                'duration_days' => 30,
                'max_staff' => null,
                'max_products' => null,
                'max_invoices_per_month' => null,
                'whatsapp_reports_enabled' => true,
                'analytics_enabled' => true,
                'features' => [
                    'billing',
                    'inventory_advanced',
                    'kot_kitchen_screen',
                    'multi_table_spaces',
                    'whatsapp_daily_reports',
                    'loyalty_points_program',
                ],
                'is_active' => true,
            ]
        );

        // Phase 11 requested plans
        SubscriptionPlan::updateOrCreate(
            ['slug' => 'free-trial'],
            [
                'name' => 'Free Trial',
                'description' => 'Experience all primary features of Growstro risk-free.',
                'price' => 0.00,
                'billing_interval' => 'monthly',
                'duration_days' => 14,
                'max_staff' => 2,
                'max_products' => 15,
                'max_invoices_per_month' => 30,
                'whatsapp_reports_enabled' => false,
                'analytics_enabled' => false,
                'features' => ['billing', 'inventory_lite'],
                'is_active' => true,
            ]
        );

        SubscriptionPlan::updateOrCreate(
            ['slug' => 'starter'],
            [
                'name' => 'Starter',
                'description' => 'Great for startup cafes and boutique retailers.',
                'price' => 1200.00,
                'billing_interval' => 'monthly',
                'duration_days' => 30,
                'max_staff' => 5,
                'max_products' => 100,
                'max_invoices_per_month' => 300,
                'whatsapp_reports_enabled' => false,
                'analytics_enabled' => true,
                'features' => ['billing', 'inventory_lite', 'analytics'],
                'is_active' => true,
            ]
        );

        SubscriptionPlan::updateOrCreate(
            ['slug' => 'business'],
            [
                'name' => 'Business',
                'description' => 'Perfect standard plan for growing restaurants.',
                'price' => 2500.00,
                'billing_interval' => 'monthly',
                'duration_days' => 30,
                'max_staff' => 15,
                'max_products' => 500,
                'max_invoices_per_month' => 1000,
                'whatsapp_reports_enabled' => true,
                'analytics_enabled' => true,
                'features' => ['billing', 'inventory_advanced', 'analytics', 'whatsapp_reports'],
                'is_active' => true,
            ]
        );

        SubscriptionPlan::updateOrCreate(
            ['slug' => 'pro'],
            [
                'name' => 'Pro',
                'description' => 'Unlimited scaling capabilities for high-performance venues.',
                'price' => 4500.00,
                'billing_interval' => 'monthly',
                'duration_days' => 30,
                'max_staff' => null,
                'max_products' => null,
                'max_invoices_per_month' => null,
                'whatsapp_reports_enabled' => true,
                'analytics_enabled' => true,
                'features' => ['billing', 'inventory_advanced', 'analytics', 'whatsapp_reports', 'kot_screen'],
                'is_active' => true,
            ]
        );
    }
}
