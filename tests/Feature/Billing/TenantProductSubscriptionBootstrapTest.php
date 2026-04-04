<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\TenantProductSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantProductSubscriptionBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_subscription_data_can_be_mirrored_into_tenant_product_subscriptions(): void
    {
        $product = Product::query()->firstOrCreate(
            ['code' => 'automotive_service'],
            [
                'name' => 'Automotive Service Management',
                'slug' => 'automotive-service',
                'is_active' => true,
            ]
        );

        $plan = Plan::query()->create([
            'product_id' => $product->id,
            'name' => 'Growth',
            'slug' => 'growth',
            'price' => 399,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
        ]);

        $legacySubscription = Subscription::query()->create([
            'tenant_id' => 'tenant-alpha',
            'plan_id' => $plan->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_123',
            'gateway_subscription_id' => 'sub_123',
            'gateway_checkout_session_id' => 'cs_123',
            'gateway_price_id' => 'price_123',
            'payment_failures_count' => 1,
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $legacySubscription->tenant_id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'legacy_subscription_id' => $legacySubscription->id,
            'status' => $legacySubscription->status,
            'gateway' => $legacySubscription->gateway,
            'gateway_customer_id' => $legacySubscription->gateway_customer_id,
            'gateway_subscription_id' => $legacySubscription->gateway_subscription_id,
            'gateway_checkout_session_id' => $legacySubscription->gateway_checkout_session_id,
            'gateway_price_id' => $legacySubscription->gateway_price_id,
            'payment_failures_count' => $legacySubscription->payment_failures_count,
        ]);

        $productSubscription = TenantProductSubscription::query()->with(['product', 'plan', 'legacySubscription'])->first();

        $this->assertNotNull($productSubscription);
        $this->assertSame('tenant-alpha', $productSubscription->tenant_id);
        $this->assertSame($product->id, $productSubscription->product_id);
        $this->assertSame($plan->id, $productSubscription->plan_id);
        $this->assertSame($legacySubscription->id, $productSubscription->legacy_subscription_id);
        $this->assertSame('automotive_service', $productSubscription->product?->code);
        $this->assertSame('growth', $productSubscription->plan?->slug);
        $this->assertSame('sub_123', $productSubscription->legacySubscription?->gateway_subscription_id);
    }
}
