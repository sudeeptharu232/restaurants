<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Prevent duplicate seed entries
        User::updateOrCreate(
            ['email' => 'admin-central@growstro.test'],
            [
                'name' => 'Growstro Super Admin',
                'password' => Hash::make('GrowstroSuperSecure2026!'),
                'role' => 'super_admin',
                'is_active' => true,
            ]
        );
    }
}
