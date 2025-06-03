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
        $mobility = Category::where('slug', 'mobility')->first();

        Product::create([
            'name' => 'M2-203',
            'slug' => 'm2-203',
            'description' => 'mobility product',
            'price' => 399.99,
            'stock' => 50,
            'rating' => 4.5,
            'review_count' => 10,
            'category_id' => $mobility->id
        ]);

        Product::create([
            'name' => 'Swift 1',
            'slug' => 'swift-1',
            'description' => 'smart mobility product',
            'price' => 999.99,
            'stock' => 30,
            'rating' => 3,
            'review_count' => 8,
            'category_id' => $mobility->id
        ]);
    }
}
