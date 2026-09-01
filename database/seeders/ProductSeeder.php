<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Product::truncate();

        $products = [
            [
                'name' => 'Coffee',
                'code' => 'HOT-001',
                'category' => 'Hot Drinks',
                'price' => 0.000,
                'cost' => 0.000,
                'stock' => 999,
                'image' => '☕',
                'color' => 'amber',
                'is_active' => true,
            ],
            [
                'name' => 'Tea / Karak',
                'code' => 'TEA-001',
                'category' => 'Hot Drinks',
                'price' => 0.000,
                'cost' => 0.000,
                'stock' => 999,
                'image' => '🍵',
                'color' => 'amber',
                'is_active' => true,
            ],
            [
                'name' => 'Cold Drinks',
                'code' => 'COLD-001',
                'category' => 'Cold Drinks',
                'price' => 0.000,
                'cost' => 0.000,
                'stock' => 999,
                'image' => '🥤',
                'color' => 'sky',
                'is_active' => true,
            ],
            [
                'name' => 'Bakery & Pastry',
                'code' => 'BAK-001',
                'category' => 'Bakery',
                'price' => 0.000,
                'cost' => 0.000,
                'stock' => 999,
                'image' => '🥐',
                'color' => 'orange',
                'is_active' => true,
            ],
            [
                'name' => 'Sandwiches & Snacks',
                'code' => 'SNK-001',
                'category' => 'Snacks',
                'price' => 0.000,
                'cost' => 0.000,
                'stock' => 999,
                'image' => '🥪',
                'color' => 'emerald',
                'is_active' => true,
            ],
            [
                'name' => 'Retail & Sweets',
                'code' => 'RET-001',
                'category' => 'Retail',
                'price' => 0.000,
                'cost' => 0.000,
                'stock' => 999,
                'image' => '🍰',
                'color' => 'rose',
                'is_active' => true,
            ],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}

