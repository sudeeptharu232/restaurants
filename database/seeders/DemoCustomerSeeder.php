<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class DemoCustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Customer::updateOrCreate(
            ['phone' => '9841234567'],
            [
                'name' => 'Ram Bahadur',
                'email' => 'ram@growstro.test',
                'address' => 'New Baneshwor, Kathmandu',
                'points' => 150,
            ]
        );

        Customer::updateOrCreate(
            ['phone' => '9851029384'],
            [
                'name' => 'Sita Shrestha',
                'email' => 'sita@growstro.test',
                'address' => 'Patan Durbar, Lalitpur',
                'points' => 320,
            ]
        );

        Customer::updateOrCreate(
            ['phone' => '9818475620'],
            [
                'name' => 'Hari Kumar',
                'email' => 'hari@growstro.test',
                'address' => 'Lake Side, Pokhara',
                'points' => 45,
            ]
        );
    }
}
