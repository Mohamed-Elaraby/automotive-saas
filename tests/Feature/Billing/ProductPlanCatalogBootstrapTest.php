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

    public function test_product_catalog_is_seeded_and_plans_are_attached_to_automotive_product(): void
    {
        $this->seed(ProductSeeder::class);
        $this->seed(PlanSeeder::class);

        $automotive = Product::query()->where('code', 'automotive_service')->first();

        $this->assertNotNull($automotive);
        $this->assertSame('automotive-service', $automotive->slug);

        $trialPlan = Plan::query()->where('slug', 'trial')->first();

        $this->assertNotNull($trialPlan);
        $this->assertSame($automotive->id, $trialPlan->product_id);
        $this->assertSame('automotive_service', $trialPlan->product?->code);
    }
}
