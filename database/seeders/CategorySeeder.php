<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Category::create(['name' => 'Retail', 'slug' => 'retail']);
        Category::create(['name' => 'Mobility', 'slug' => 'mobility']);
        Category::create(['name' => 'Self Service', 'slug' => 'self-service']);
        Category::create(['name' => 'Kitchen', 'slug' => 'kitchen']);
    }
}
