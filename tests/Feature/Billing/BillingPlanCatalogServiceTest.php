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
}
