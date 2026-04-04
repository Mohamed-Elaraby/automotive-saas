<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\TenantProductSubscription;
use App\Services\Billing\StripeSubscriptionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class StripeSubscriptionSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_sync_from_stripe_payload_updates_product_subscription_mirror(): void
    {
        $productId = Product::query()->where('code', 'automotive_service')->value('id');

        $plan = Plan::query()->create([
            'product_id' => $productId,
            'name' => 'Stripe Sync Plan',
            'slug' => 'stripe-sync-plan-' . uniqid(),
            'description' => 'Stripe sync test plan',
            'price' => 199,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => 'price_sync_test',
        ]);

        $subscription = Subscription::query()->create([
            'tenant_id' => 'tenant-sync-mirror',
            'plan_id' => $plan->id,
            'status' => 'past_due',
            'payment_failures_count' => 1,
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_sync_old',
            'gateway_subscription_id' => 'sub_sync_test',
            'gateway_price_id' => 'price_sync_test',
            'past_due_started_at' => now()->subHour(),
            'grace_ends_at' => now()->addHour(),
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $subscription->tenant_id,
            'product_id' => $productId,
            'plan_id' => $plan->id,
            'legacy_subscription_id' => $subscription->id,
            'status' => 'past_due',
            'payment_failures_count' => 1,
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_sync_old',
            'gateway_subscription_id' => 'sub_sync_test',
            'gateway_price_id' => 'price_sync_test',
        ]);

        $service = app(StripeSubscriptionSyncService::class);

        $fresh = $service->syncFromStripePayload($subscription, [
            'id' => 'sub_sync_test',
            'status' => 'active',
            'customer' => 'cus_sync_new',
            'metadata' => [
                'plan_id' => $plan->id,
            ],
            'items' => [
                'data' => [
                    [
                        'price' => [
                            'id' => 'price_sync_test',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame('active', $fresh->status);
        $this->assertSame('cus_sync_new', $fresh->gateway_customer_id);

        $this->assertDatabaseHas('tenant_product_subscriptions', [
            'tenant_id' => 'tenant-sync-mirror',
            'legacy_subscription_id' => $subscription->id,
            'status' => 'active',
            'gateway_customer_id' => 'cus_sync_new',
            'gateway_subscription_id' => 'sub_sync_test',
        ]);
    }

    public function test_canceled_subscription_with_future_period_end_is_synced_as_cancelled_not_expired(): void
    {
        $productId = Product::query()->where('code', 'automotive_service')->value('id');

        $plan = Plan::query()->create([
            'product_id' => $productId,
            'name' => 'Stripe Cancelled Future Plan',
            'slug' => 'stripe-cancelled-future-' . uniqid(),
            'description' => 'Stripe cancelled future test plan',
            'price' => 199,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => 'price_cancel_future_test',
        ]);

        $subscription = Subscription::query()->create([
            'tenant_id' => 'tenant-sync-cancelled',
            'plan_id' => $plan->id,
            'status' => 'active',
            'payment_failures_count' => 0,
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_cancel_future',
            'gateway_subscription_id' => 'sub_cancel_future',
            'gateway_price_id' => 'price_cancel_future_test',
        ]);

        $fresh = app(StripeSubscriptionSyncService::class)->syncFromStripePayload($subscription, [
            'id' => 'sub_cancel_future',
            'status' => 'canceled',
            'customer' => 'cus_cancel_future',
            'canceled_at' => now()->timestamp,
            'current_period_end' => now()->addDays(10)->timestamp,
            'metadata' => [
                'plan_id' => $plan->id,
            ],
            'items' => [
                'data' => [
                    [
                        'price' => [
                            'id' => 'price_cancel_future_test',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame('canceled', $fresh->status);
        $this->assertNotNull($fresh->ends_at);
        $this->assertTrue($fresh->ends_at->isFuture());
    }

    public function test_canceled_subscription_with_past_period_end_is_synced_as_expired(): void
    {
        $productId = Product::query()->where('code', 'automotive_service')->value('id');

        $plan = Plan::query()->create([
            'product_id' => $productId,
            'name' => 'Stripe Expired Plan',
            'slug' => 'stripe-expired-' . uniqid(),
            'description' => 'Stripe expired test plan',
            'price' => 199,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => 'price_expired_test',
        ]);

        $subscription = Subscription::query()->create([
            'tenant_id' => 'tenant-sync-expired',
            'plan_id' => $plan->id,
            'status' => 'active',
            'payment_failures_count' => 0,
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_expired_test',
            'gateway_subscription_id' => 'sub_expired_test',
            'gateway_price_id' => 'price_expired_test',
        ]);

        $fresh = app(StripeSubscriptionSyncService::class)->syncFromStripePayload($subscription, [
            'id' => 'sub_expired_test',
            'status' => 'canceled',
            'customer' => 'cus_expired_test',
            'canceled_at' => now()->subDays(5)->timestamp,
            'current_period_end' => now()->subDay()->timestamp,
            'metadata' => [
                'plan_id' => $plan->id,
            ],
            'items' => [
                'data' => [
                    [
                        'price' => [
                            'id' => 'price_expired_test',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame('expired', $fresh->status);
        $this->assertNotNull($fresh->ends_at);
        $this->assertTrue($fresh->ends_at->isPast());
    }
}
