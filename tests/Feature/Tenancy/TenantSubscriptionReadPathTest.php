<?php

namespace Tests\Feature\Tenancy;

use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\TenantProductSubscription;
use App\Services\Tenancy\TenantPlanService;
use App\Services\Tenancy\TenantSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantSubscriptionReadPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_plan_service_prefers_product_subscription_when_available(): void
    {
        $product = Product::query()->where('code', 'automotive_service')->firstOrFail();
        $legacyPlan = $this->createPlan($product->id, 'legacy-plan');
        $productPlan = $this->createPlan($product->id, 'product-plan');

        Subscription::query()->create([
            'tenant_id' => 'tenant-read-path',
            'plan_id' => $legacyPlan->id,
            'status' => 'expired',
            'payment_failures_count' => 0,
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => 'tenant-read-path',
            'product_id' => $product->id,
            'plan_id' => $productPlan->id,
            'status' => 'active',
            'payment_failures_count' => 0,
        ]);

        $current = app(TenantPlanService::class)->getCurrentSubscription('tenant-read-path');

        $this->assertNotNull($current);
        $this->assertSame('active', $current->status);
        $this->assertSame($productPlan->id, $current->plan_id);
    }

    public function test_tenant_subscription_service_falls_back_to_legacy_subscription_when_product_subscription_is_missing(): void
    {
        $product = Product::query()->where('code', 'automotive_service')->firstOrFail();
        $legacyPlan = $this->createPlan($product->id, 'fallback-plan');

        Subscription::query()->create([
            'tenant_id' => 'tenant-read-fallback',
            'plan_id' => $legacyPlan->id,
            'status' => 'trialing',
            'payment_failures_count' => 0,
        ]);

        $current = app(TenantSubscriptionService::class)->getCurrentSubscription('tenant-read-fallback');

        $this->assertNotNull($current);
        $this->assertSame('trialing', $current->status);
        $this->assertSame($legacyPlan->id, $current->plan_id);
    }

    protected function createPlan(int $productId, string $slugPrefix): Plan
    {
        return Plan::query()->create([
            'product_id' => $productId,
            'name' => 'Plan ' . $slugPrefix,
            'slug' => $slugPrefix . '-' . uniqid(),
            'description' => 'Read path test plan',
            'price' => 99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => 'price_' . uniqid(),
        ]);
    }
}
