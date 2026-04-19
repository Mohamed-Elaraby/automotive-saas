<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\StockItem;
use App\Models\StockMovement;
use Illuminate\Database\Seeder;

class TenantSparePartsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::query()->firstOrCreate(
            ['code' => 'MAIN'],
            [
                'name' => 'Main Branch',
                'address' => 'Demo warehouse',
                'phone' => null,
                'is_active' => true,
            ]
        );

        $items = [
                [
                    'name' => 'Engine Oil 5W-30',
                    'sku' => 'SP-OIL-5W30',
                    'barcode' => '629100000001',
                    'unit' => 'bottle',
                    'cost_price' => 18,
                    'sale_price' => 35,
                    'min_stock_alert' => 5,
                    'description' => 'Synthetic engine oil bottle for service jobs.',
                    'quantity' => 24,
                ],
                [
                    'name' => 'Oil Filter Toyota',
                    'sku' => 'SP-FLT-TOY-OIL',
                    'barcode' => '629100000002',
                    'unit' => 'pcs',
                    'cost_price' => 12,
                    'sale_price' => 28,
                    'min_stock_alert' => 4,
                    'description' => 'Common Toyota oil filter for workshop testing.',
                    'quantity' => 18,
                ],
                [
                    'name' => 'Front Brake Pads Set',
                    'sku' => 'SP-BRK-PAD-FR',
                    'barcode' => '629100000003',
                    'unit' => 'set',
                    'cost_price' => 65,
                    'sale_price' => 130,
                    'min_stock_alert' => 3,
                    'description' => 'Front brake pad set for demo work orders.',
                    'quantity' => 10,
                ],
                [
                    'name' => 'Air Filter Generic',
                    'sku' => 'SP-FLT-AIR-GEN',
                    'barcode' => '629100000004',
                    'unit' => 'pcs',
                    'cost_price' => 15,
                    'sale_price' => 40,
                    'min_stock_alert' => 4,
                    'description' => 'Generic engine air filter for service testing.',
                    'quantity' => 16,
                ],
        ];

        foreach ($items as $item) {
            $quantity = (float) $item['quantity'];
            unset($item['quantity']);

            $stockItem = StockItem::query()->updateOrCreate(
                ['sku' => $item['sku']],
                $item + ['is_active' => true]
            );

            Inventory::query()->updateOrCreate(
                [
                    'branch_id' => $branch->id,
                    'product_id' => $stockItem->id,
                ],
                [
                    'quantity' => $quantity,
                ]
            );

            StockMovement::query()->firstOrCreate(
                [
                    'branch_id' => $branch->id,
                    'product_id' => $stockItem->id,
                    'type' => 'opening',
                    'reference_type' => 'demo_seed',
                ],
                [
                    'quantity' => $quantity,
                    'reference_id' => null,
                    'notes' => 'Demo spare parts opening balance.',
                    'created_by' => null,
                    'movement_date' => now(),
                ]
            );
        }
    }
}
