<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Services\Billing\StripeSubscriptionSyncService;
use App\Services\Billing\TenantBillingLifecycleService;
use App\Services\Billing\TenantProductSubscriptionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class StripeSubscriptionSyncRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_can_recover_missing_gateway_subscription_id_from_checkout_session(): void
    {
        $plan = $this->createPlan();

        $subscription = Subscription::query()->create([
            'tenant_id' => 'tenant-recover-checkout',
            'plan_id' => $plan->id,
            'status' => 'past_due',
            'payment_failures_count' => 0,
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_old_checkout',
            'gateway_subscription_id' => null,
            'gateway_checkout_session_id' => 'cs_recover_001',
            'gateway_price_id' => 'price_recover_001',
        ]);

        $checkoutSessions = Mockery::mock();
        $checkoutSessions->shouldReceive('retrieve')
            ->once()
            ->with('cs_recover_001', [])
            ->andReturn((object) [
                'subscription' => 'sub_recovered_001',
                'customer' => 'cus_recovered_001',
            ]);

        $subscriptions = Mockery::mock();
        $subscriptions->shouldReceive('retrieve')
            ->once()
            ->with('sub_recovered_001', [])
            ->andReturn(new class {
                public function toArray(): array
                {
                    return [
                        'id' => 'sub_recovered_001',
                        'status' => 'active',
                        'customer' => 'cus_recovered_001',
                        'metadata' => [],
                        'items' => [
                            'data' => [
                                [
                                    'price' => [
                                        'id' => 'price_recover_001',
                                    ],
                                ],
                            ],
                        ],
                    ];
                }
            });

        $checkout = Mockery::mock();
        $checkout->sessions = $checkoutSessions;

        $stripeClient = Mockery::mock();
        $stripeClient->checkout = $checkout;
        $stripeClient->subscriptions = $subscriptions;

        $service = Mockery::mock(
            StripeSubscriptionSyncService::class,
            [
                app(TenantBillingLifecycleService::class),
                app(TenantProductSubscriptionSyncService::class),
            ]
        )
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $service->shouldReceive('client')
            ->andReturn($stripeClient);

        $fresh = $service->syncLocalStripeSubscription($subscription);

        $this->assertNotNull($fresh);
        $this->assertSame('sub_recovered_001', $fresh->gateway_subscription_id);
        $this->assertSame('cus_recovered_001', $fresh->gateway_customer_id);
        $this->assertSame('active', $fresh->status);
    }

    protected function createPlan(): Plan
    {
        return Plan::query()->create([
            'product_id' => Product::query()->where('code', 'automotive_service')->value('id'),
            'name' => 'Recovery Plan',
            'slug' => 'recovery-plan-' . uniqid(),
            'description' => 'Recovery test plan',
            'price' => 199,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => 'price_recover_001',
        ]);
    }
}
