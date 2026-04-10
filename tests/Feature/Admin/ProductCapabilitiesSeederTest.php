<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductCapability;
use Database\Seeders\ProductCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCapabilitiesSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_one_default_capability_for_each_product(): void
    {
        Product::query()->updateOrCreate(
            ['code' => 'automotive_service'],
            [
                'name' => 'Automotive Service Management',
                'slug' => 'automotive-service',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        Product::query()->updateOrCreate(
            ['code' => 'parts_inventory'],
            [
                'name' => 'Parts Inventory Management',
                'slug' => 'parts-inventory',
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        Product::query()->updateOrCreate(
            ['code' => 'accounting'],
            [
                'name' => 'Accounting System',
                'slug' => 'accounting',
                'is_active' => true,
                'sort_order' => 3,
            ]
        );

        $this->seed(ProductCapabilitiesSeeder::class);

        $this->assertDatabaseHas('product_capabilities', [
            'code' => 'workshop_operations',
            'name' => 'Workshop Operations',
        ]);
        $this->assertDatabaseHas('product_capabilities', [
            'code' => 'supplier_catalog',
            'name' => 'Supplier Catalog',
        ]);
        $this->assertDatabaseHas('product_capabilities', [
            'code' => 'general_ledger',
            'name' => 'General Ledger',
        ]);

        $this->assertSame(3, ProductCapability::query()->count());
    }
}
