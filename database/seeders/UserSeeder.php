<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        $customer = User::create([
            'name' => 'Customer',
            'email' => 'customer@test.com',
            'password' => bcrypt('password'),
        ]);
        $customer->assignRole('customer');
    }
}
