<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            // Hot Drinks & Coffee
            [
                'name' => 'Arabic Coffee Dallah',
                'code' => 'HOT-001',
                'category' => 'Hot Drinks',
                'price' => 1.750,
                'cost' => 0.400,
                'stock' => 50,
                'image' => '☕',
                'color' => 'amber',
            ],
            [
                'name' => 'Karak Tea (Cardamom)',
                'code' => 'HOT-002',
                'category' => 'Hot Drinks',
                'price' => 0.500,
                'cost' => 0.100,
                'stock' => 120,
                'image' => '🍵',
                'color' => 'amber',
            ],
            [
                'name' => 'Spanish Latte (Large)',
                'code' => 'HOT-003',
                'category' => 'Hot Drinks',
                'price' => 1.850,
                'cost' => 0.450,
                'stock' => 80,
                'image' => '☕',
                'color' => 'amber',
            ],
            [
                'name' => 'Espresso Double Shot',
                'code' => 'HOT-004',
                'category' => 'Hot Drinks',
                'price' => 0.950,
                'cost' => 0.200,
                'stock' => 100,
                'image' => '☕',
                'color' => 'amber',
            ],
            [
                'name' => 'Flat White',
                'code' => 'HOT-005',
                'category' => 'Hot Drinks',
                'price' => 1.500,
                'cost' => 0.350,
                'stock' => 75,
                'image' => '☕',
                'color' => 'amber',
            ],

            // Cold Beverages & Juices
            [
                'name' => 'Fresh Pomegranate Juice',
                'code' => 'COLD-001',
                'category' => 'Cold Drinks',
                'price' => 1.250,
                'cost' => 0.300,
                'stock' => 40,
                'image' => '🥤',
                'color' => 'rose',
            ],
            [
                'name' => 'Iced V60 Ethiopia',
                'code' => 'COLD-002',
                'category' => 'Cold Drinks',
                'price' => 2.250,
                'cost' => 0.600,
                'stock' => 60,
                'image' => '🧊',
                'color' => 'rose',
            ],
            [
                'name' => 'Mineral Water (500ml)',
                'code' => 'COLD-003',
                'category' => 'Cold Drinks',
                'price' => 0.250,
                'cost' => 0.050,
                'stock' => 200,
                'image' => '💧',
                'color' => 'sky',
            ],
            [
                'name' => 'Lemon Mint Cooler',
                'code' => 'COLD-004',
                'category' => 'Cold Drinks',
                'price' => 1.000,
                'cost' => 0.200,
                'stock' => 50,
                'image' => '🍋',
                'color' => 'lime',
            ],
            [
                'name' => 'Soft Drink Can',
                'code' => 'COLD-005',
                'category' => 'Cold Drinks',
                'price' => 0.350,
                'cost' => 0.120,
                'stock' => 150,
                'image' => '🥫',
                'color' => 'red',
            ],

            // Bakery & Pastries
            [
                'name' => 'Cheese & Zaatar Croissant',
                'code' => 'BAK-001',
                'category' => 'Bakery',
                'price' => 0.850,
                'cost' => 0.250,
                'stock' => 35,
                'image' => '🥐',
                'color' => 'orange',
            ],
            [
                'name' => 'Cardamom Cinnamon Roll',
                'code' => 'BAK-002',
                'category' => 'Bakery',
                'price' => 1.100,
                'cost' => 0.300,
                'stock' => 25,
                'image' => '🥮',
                'color' => 'orange',
            ],
            [
                'name' => 'Pistachio Kunafa Slice',
                'code' => 'BAK-003',
                'category' => 'Bakery',
                'price' => 2.500,
                'cost' => 0.750,
                'stock' => 20,
                'image' => '🍰',
                'color' => 'emerald',
            ],
            [
                'name' => 'Chocolate Babka Bun',
                'code' => 'BAK-004',
                'category' => 'Bakery',
                'price' => 1.350,
                'cost' => 0.400,
                'stock' => 30,
                'image' => '🍫',
                'color' => 'amber',
            ],

            // Snacks & Quick Bites
            [
                'name' => 'Halloumi Pesto Toast',
                'code' => 'SNK-001',
                'category' => 'Snacks',
                'price' => 1.950,
                'cost' => 0.600,
                'stock' => 40,
                'image' => '🥪',
                'color' => 'emerald',
            ],
            [
                'name' => 'Mixed Salted Nuts (Pack)',
                'code' => 'SNK-002',
                'category' => 'Snacks',
                'price' => 0.750,
                'cost' => 0.250,
                'stock' => 90,
                'image' => '🥜',
                'color' => 'yellow',
            ],
            [
                'name' => 'Gourmet Potato Chips (Truffle)',
                'code' => 'SNK-003',
                'category' => 'Snacks',
                'price' => 0.650,
                'cost' => 0.200,
                'stock' => 60,
                'image' => '🥔',
                'color' => 'yellow',
            ],
            [
                'name' => 'Medjool Dates Box (500g)',
                'code' => 'SNK-004',
                'category' => 'Snacks',
                'price' => 3.750,
                'cost' => 1.200,
                'stock' => 25,
                'image' => '🌴',
                'color' => 'amber',
            ],

            // Retail & Essentials
            [
                'name' => 'Artisan Olive Oil (500ml)',
                'code' => 'RET-001',
                'category' => 'Retail',
                'price' => 4.500,
                'cost' => 2.000,
                'stock' => 15,
                'image' => '🫒',
                'color' => 'emerald',
            ],
            [
                'name' => 'Specialty Coffee Beans (250g)',
                'code' => 'RET-002',
                'category' => 'Retail',
                'price' => 5.250,
                'cost' => 2.500,
                'stock' => 30,
                'image' => '🫘',
                'color' => 'amber',
            ],
            [
                'name' => 'Organic Honey Jar (350g)',
                'code' => 'RET-003',
                'category' => 'Retail',
                'price' => 6.000,
                'cost' => 3.000,
                'stock' => 18,
                'image' => '🍯',
                'color' => 'yellow',
            ],
            [
                'name' => 'Saffron Box (5g Premium)',
                'code' => 'RET-004',
                'category' => 'Retail',
                'price' => 8.500,
                'cost' => 4.500,
                'stock' => 10,
                'image' => '🌸',
                'color' => 'red',
            ],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(
                ['code' => $product['code']],
                $product
            );
        }
    }
}
