<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoTenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Find or create the demo tenant Sajilo Store
        $tenant = Tenant::find('sajilo');

        if (!$tenant) {
            // Drop orphaned PostgreSQL database if it exists (terminate connections first)
            $dbName = 'tenant' . 'sajilo';
            \Illuminate\Support\Facades\DB::statement("SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '{$dbName}'");
            \Illuminate\Support\Facades\DB::statement("DROP DATABASE IF EXISTS \"{$dbName}\"");

            $tenant = Tenant::create([
                'id' => 'sajilo',
                'name' => 'Sajilo Store',
            ]);

            // Bind the sajilo.localhost domain
            $tenant->domains()->create([
                'domain' => 'sajilo.localhost',
            ]);
        }

        // 2. Switch context dynamically to the tenant's database to run isolated seeds
        $tenant->run(function () {
            // Seed the isolated tenant owner account
            User::updateOrCreate(
                ['email' => 'owner-sajilo@growstro.test'],
                [
                    'name' => 'Demo Owner',
                    'password' => Hash::make('SajiloStoreOwner2026!'),
                    'role' => 'owner',
                    'is_active' => true,
                ]
            );

            // Execute tenant-scoped POS and inventory seeds
            $this->call([
                DemoCustomerSeeder::class,
                DemoProductSeeder::class,
                DemoOrderSeeder::class,
                RichDemoDataSeeder::class,
            ]);
        });
    }
}
