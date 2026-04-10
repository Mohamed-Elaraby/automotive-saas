<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCapability;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductCapabilitiesSeeder extends Seeder
{
    public function run(): void
    {
        Product::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->each(function (Product $product): void {
                $capability = $this->defaultCapabilityForProduct($product);

                ProductCapability::query()->updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'code' => $capability['code'],
                    ],
                    $capability + [
                        'product_id' => $product->id,
                    ]
                );
            });
    }

    protected function defaultCapabilityForProduct(Product $product): array
    {
        return match ((string) $product->code) {
            'automotive_service' => [
                'code' => 'workshop_operations',
                'name' => 'Workshop Operations',
                'slug' => 'workshop-operations',
                'description' => 'Core service workflow for job cards, repairs, and workshop execution.',
                'is_active' => true,
                'sort_order' => 1,
            ],
            'parts_inventory' => [
                'code' => 'supplier_catalog',
                'name' => 'Supplier Catalog',
                'slug' => 'supplier-catalog',
                'description' => 'Manage spare parts suppliers and purchasing references.',
                'is_active' => true,
                'sort_order' => 1,
            ],
            'accounting' => [
                'code' => 'general_ledger',
                'name' => 'General Ledger',
                'slug' => 'general-ledger',
                'description' => 'Manage journals, ledgers, and core accounting entries.',
                'is_active' => true,
                'sort_order' => 1,
            ],
            default => [
                'code' => Str::snake($product->code . '_core_module'),
                'name' => $product->name . ' Core Module',
                'slug' => Str::slug($product->slug . '-core-module'),
                'description' => 'Core capability for ' . $product->name . '.',
                'is_active' => true,
                'sort_order' => 1,
            ],
        };
    }
}
