<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;

class DemoProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed Categories
        $bevCategory = Category::updateOrCreate(
            ['slug' => 'beverages'],
            ['name' => 'Beverages', 'type' => 'product', 'is_active' => true]
        );

        $bakeryCategory = Category::updateOrCreate(
            ['slug' => 'bakery'],
            ['name' => 'Bakery & Sweets', 'type' => 'product', 'is_active' => true]
        );

        $salonCategory = Category::updateOrCreate(
            ['slug' => 'salon-grooming'],
            ['name' => 'Salon Grooming', 'type' => 'service', 'is_active' => true]
        );

        $foodCategory = Category::updateOrCreate(
            ['slug' => 'nepalese-main-course'],
            ['name' => 'Nepalese Main Course', 'type' => 'menu', 'is_active' => true]
        );

        // 2. Seed Products
        Product::updateOrCreate(
            ['sku' => 'PROD-COKE-250'],
            [
                'category_id' => $bevCategory->id,
                'name' => 'Coca Cola 250ml',
                'barcode' => '8901764012219',
                'price' => 80.00,
                'cost_price' => 65.00,
                'stock_quantity' => 100.00,
                'track_stock' => true,
                'is_active' => true,
            ]
        );

        Product::updateOrCreate(
            ['sku' => 'PROD-BREAD-BRN'],
            [
                'category_id' => $bakeryCategory->id,
                'name' => 'Brown Bread Large',
                'barcode' => '8901234567890',
                'price' => 120.00,
                'cost_price' => 90.00,
                'stock_quantity' => 15.00,
                'track_stock' => true,
                'is_active' => true,
            ]
        );

        // 3. Seed Services
        Service::updateOrCreate(
            ['name' => 'Hair Cut & Shave'],
            [
                'category_id' => $salonCategory->id,
                'duration_minutes' => 30,
                'price' => 250.00,
                'is_active' => true,
            ]
        );

        // 4. Seed Menu Items
        MenuItem::updateOrCreate(
            ['name' => 'Thakali Mutton Set'],
            [
                'category_id' => $foodCategory->id,
                'description' => 'Traditional Thakali thali served with local ghee, black lentil soup, greens, and tomato pickles.',
                'price' => 550.00,
                'is_available' => true,
            ]
        );

        MenuItem::updateOrCreate(
            ['name' => 'Chicken Momo'],
            [
                'category_id' => $foodCategory->id,
                'description' => 'Steamed dumplings stuffed with minced spiced chicken, served with hot spicy sesame soup.',
                'price' => 180.00,
                'is_available' => true,
            ]
        );
    }
}
