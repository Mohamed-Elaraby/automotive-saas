<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'code' => 'automotive_service',
                'name' => 'Automotive Service Management',
                'slug' => 'automotive-service',
                'description' => 'Manage automotive service centers, job cards, repairs, and workshop operations.',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'code' => 'parts_inventory',
                'name' => 'Parts Inventory Management',
                'slug' => 'parts-inventory',
                'description' => 'Manage spare parts inventory, warehouses, purchasing, sales, and stock operations.',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'code' => 'accounting',
                'name' => 'Accounting System',
                'slug' => 'accounting',
                'description' => 'Manage journals, ledgers, vouchers, and financial operations.',
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($products as $product) {
            Product::query()->updateOrCreate(
                ['code' => $product['code']],
                $product
            );
        }
    }
}
