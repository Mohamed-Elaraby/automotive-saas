<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\Product;
use App\Services\Billing\BillingPlanCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BillingPlanCatalogServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_load_paid_plans_when_billing_feature_tables_do_not_exist(): void
    {
        Schema::dropIfExists('billing_feature_plan');
        Schema::dropIfExists('billing_features');

        $productId = Product::query()->where('code', 'automotive_service')->value('id');

        Plan::query()->create([
            'product_id' => $productId,
            'name' => 'Catalog Safe Plan',
            'slug' => 'catalog-safe-' . uniqid(),
            'description' => 'Safe plan without feature tables',
            'price' => 199,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => 'price_' . uniqid(),
        ]);

        $plans = app(BillingPlanCatalogService::class)->getPaidPlans('automotive_service');

        $this->assertCount(1, $plans);
        $this->assertSame([], $plans->first()->features_array ?? null);
    }

    public function test_it_can_count_paid_plans_by_product_code(): void
    {
        $automotiveProduct = Product::query()->where('code', 'automotive_service')->firstOrFail();
        $partsProduct = Product::query()->create([
            'code' => 'parts_inventory',
            'name' => 'Parts Inventory Management',
            'slug' => 'parts-inventory',
            'description' => 'Parts catalog',
            'is_active' => true,
        ]);

        Plan::query()->create([
            'product_id' => $automotiveProduct->id,
            'name' => 'Automotive Growth',
            'slug' => 'automotive-growth-' . uniqid(),
            'description' => 'Automotive plan',
            'price' => 199,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => 'price_' . uniqid(),
        ]);

        Plan::query()->create([
            'product_id' => $partsProduct->id,
            'name' => 'Parts Starter',
            'slug' => 'parts-starter-' . uniqid(),
            'description' => 'Parts plan',
            'price' => 149,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => 'price_' . uniqid(),
        ]);

        Plan::query()->create([
            'product_id' => $partsProduct->id,
            'name' => 'Parts Trial',
            'slug' => 'parts-trial-' . uniqid(),
            'description' => 'Parts trial',
            'price' => 0,
            'currency' => 'USD',
            'billing_period' => 'trial',
            'is_active' => true,
        ]);

        $counts = app(BillingPlanCatalogService::class)->paidPlanCountsByProductCode();

        $this->assertSame(1, (int) $counts->get('automotive_service'));
        $this->assertSame(1, (int) $counts->get('parts_inventory'));
    }
}
