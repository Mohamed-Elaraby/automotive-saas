<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\Product;
use Database\Seeders\PlanSeeder;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPlanCatalogBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_catalog_is_seeded_and_plans_are_attached_to_all_current_products(): void
    {
        $this->seed(ProductSeeder::class);
        $this->seed(PlanSeeder::class);

        $automotive = Product::query()->where('code', 'automotive_service')->first();
        $parts = Product::query()->where('code', 'parts_inventory')->first();
        $accounting = Product::query()->where('code', 'accounting')->first();

        $this->assertNotNull($automotive);
        $this->assertNotNull($parts);
        $this->assertNotNull($accounting);
        $this->assertSame('automotive-service', $automotive->slug);
        $this->assertTrue((bool) $parts->is_active);
        $this->assertTrue((bool) $accounting->is_active);

        $automotiveTrial = Plan::query()->where('slug', 'automotive-service-trial')->first();
        $partsTrial = Plan::query()->where('slug', 'parts-inventory-trial')->first();
        $accountingTrial = Plan::query()->where('slug', 'accounting-trial')->first();

        $this->assertNotNull($automotiveTrial);
        $this->assertNotNull($partsTrial);
        $this->assertNotNull($accountingTrial);
        $this->assertSame($automotive->id, $automotiveTrial->product_id);
        $this->assertSame($parts->id, $partsTrial->product_id);
        $this->assertSame($accounting->id, $accountingTrial->product_id);
        $this->assertSame('automotive_service', $automotiveTrial->product?->code);
        $this->assertSame('parts_inventory', $partsTrial->product?->code);
        $this->assertSame('accounting', $accountingTrial->product?->code);
    }
}
