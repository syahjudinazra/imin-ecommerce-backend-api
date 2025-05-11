<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Category;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $electronics = Category::where('slug', 'electronics')->first();

        Product::create([
            'name' => 'Smartphone',
            'slug' => 'smartphone',
            'description' => 'Android smartphone',
            'price' => 399.99,
            'stock' => 50,
            'category_id' => $electronics->id
        ]);

        Product::create([
            'name' => 'Laptop',
            'slug' => 'laptop',
            'description' => 'High-end laptop',
            'price' => 999.99,
            'stock' => 30,
            'category_id' => $electronics->id
        ]);
    }
}
