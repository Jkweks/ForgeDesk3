<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'sku' => 'SKU-001',
                'description' => 'Steel Plate 1/4"',
                'location' => 'A-12-03',
                'unit_cost' => 45.50,
                'unit_price' => 89.99,
                'quantity_on_hand' => 450,
                'quantity_committed' => 120,
                'minimum_quantity' => 100,
                'unit_of_measure' => 'EA',
            ],
            [
                'sku' => 'SKU-002',
                'description' => 'Aluminum Sheet 20ga',
                'location' => 'B-05-12',
                'unit_cost' => 32.00,
                'unit_price' => 65.00,
                'quantity_on_hand' => 280,
                'quantity_committed' => 50,
                'minimum_quantity' => 75,
                'unit_of_measure' => 'EA',
            ],
            [
                'sku' => 'SKU-003',
                'description' => 'Door Hinge Heavy Duty',
                'location' => 'C-08-15',
                'unit_cost' => 12.50,
                'unit_price' => 24.99,
                'quantity_on_hand' => 1250,
                'quantity_committed' => 800,
                'minimum_quantity' => 200,
                'unit_of_measure' => 'EA',
            ],
            [
                'sku' => 'SKU-004',
                'description' => 'Paint - Industrial Grey',
                'location' => 'D-03-07',
                'unit_cost' => 28.00,
                'unit_price' => 55.99,
                'quantity_on_hand' => 85,
                'quantity_committed' => 30,
                'minimum_quantity' => 50,
                'unit_of_measure' => 'GAL',
            ],
            [
                'sku' => 'SKU-005',
                'description' => 'Weatherstripping 100ft',
                'location' => 'E-11-20',
                'unit_cost' => 18.00,
                'unit_price' => 39.99,
                'quantity_on_hand' => 42,
                'quantity_committed' => 15,
                'minimum_quantity' => 50,
                'unit_of_measure' => 'ROLL',
            ],
        ];

        foreach ($products as $productData) {
            $product = Product::create($productData);
            $product->updateStatus();
        }
    }
}