<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\StripeWebhookSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class StripeCancellationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_customer_subscription_updated_notifies_when_cancel_at_period_end_is_enabled(): void
    {
        Carbon::setTestNow('2026-03-26 12:00:00');

        $subscription = $this->createStripeSubscription([
            'status' => 'active',
            'cancelled_at' => null,
            'ends_at' => null,
            'gateway_price_id' => 'price_old_plan',
        ]);

        $syncService = Mockery::mock(\App\Services\Billing\StripeSubscriptionSyncService::class);
        $syncService->shouldReceive('syncByGatewaySubscriptionId')
            ->once()
            ->with($subscription->gateway_subscription_id)
            ->andReturnUsing(function () use ($subscription) {
                $subscription->update([
                    'status' => 'active',
                    'cancelled_at' => now(),
                    'ends_at' => now()->addMonth(),
                    'gateway_price_id' => 'price_old_plan',
                    'plan_id' => $subscription->plan_id,
                ]);

                return $subscription->fresh();
            });

        $notificationService = Mockery::mock(\App\Services\Billing\BillingNotificationService::class);
        $notificationService->shouldReceive('subscriptionCancelled')->once();
        $notificationService->shouldNotReceive('planChanged');

        $service = new StripeWebhookSyncService($syncService, $notificationService);

        $event = (object) [
            'type' => 'customer.subscription.updated',
            'data' => (object) [
                'object' => (object) [
                    'id' => $subscription->gateway_subscription_id,
                    'status' => 'active',
                    'cancel_at_period_end' => true,
                ],
            ],
        ];

        $service->handleEvent($event);

        $subscription->refresh();

        $this->assertNotNull($subscription->cancelled_at);
        $this->assertNotNull($subscription->ends_at);
    }

    public function test_customer_subscription_updated_notifies_when_plan_changes(): void
    {
        Carbon::setTestNow('2026-03-26 12:00:00');

        $oldPlan = $this->createPlan('Old Plan', 'old-plan', 'price_old_plan');
        $newPlan = $this->createPlan('New Plan', 'new-plan', 'price_new_plan');

        $subscription = $this->createStripeSubscription([
            'status' => 'active',
            'plan_id' => $oldPlan->id,
            'gateway_price_id' => 'price_old_plan',
            'cancelled_at' => null,
            'ends_at' => null,
        ]);

        $syncService = Mockery::mock(\App\Services\Billing\StripeSubscriptionSyncService::class);
        $syncService->shouldReceive('syncByGatewaySubscriptionId')
            ->once()
            ->with($subscription->gateway_subscription_id)
            ->andReturnUsing(function () use ($subscription, $newPlan) {
                $subscription->update([
                    'status' => 'active',
                    'plan_id' => $newPlan->id,
                    'gateway_price_id' => 'price_new_plan',
                ]);

                return $subscription->fresh();
            });

        $notificationService = Mockery::mock(\App\Services\Billing\BillingNotificationService::class);
        $notificationService->shouldReceive('planChanged')->once();
        $notificationService->shouldNotReceive('subscriptionCancelled');

        $service = new StripeWebhookSyncService($syncService, $notificationService);

        $event = (object) [
            'type' => 'customer.subscription.updated',
            'data' => (object) [
                'object' => (object) [
                    'id' => $subscription->gateway_subscription_id,
                    'status' => 'active',
                    'cancel_at_period_end' => false,
                ],
            ],
        ];

        $service->handleEvent($event);

        $subscription->refresh();

        $this->assertSame($newPlan->id, $subscription->plan_id);
        $this->assertSame('price_new_plan', $subscription->gateway_price_id);
    }

    public function test_customer_subscription_deleted_notifies_as_expired_when_period_end_is_past(): void
    {
        Carbon::setTestNow('2026-03-26 12:00:00');

        $subscription = $this->createStripeSubscription([
            'status' => 'active',
            'ends_at' => null,
        ]);

        $syncService = Mockery::mock(\App\Services\Billing\StripeSubscriptionSyncService::class);
        $syncService->shouldReceive('syncByGatewaySubscriptionId')
            ->once()
            ->with($subscription->gateway_subscription_id)
            ->andReturnUsing(function () use ($subscription) {
                return $subscription->fresh();
            });

        $notificationService = Mockery::mock(\App\Services\Billing\BillingNotificationService::class);
        $notificationService->shouldReceive('subscriptionExpired')->once();
        $notificationService->shouldNotReceive('subscriptionCancelled');

        $service = new StripeWebhookSyncService($syncService, $notificationService);

        $event = (object) [
            'type' => 'customer.subscription.deleted',
            'data' => (object) [
                'object' => (object) [
                    'id' => $subscription->gateway_subscription_id,
                    'current_period_end' => now()->subMinute()->timestamp,
                ],
            ],
        ];

        $service->handleEvent($event);

        $this->assertTrue(true);
    }

    public function test_customer_subscription_deleted_notifies_as_cancelled_when_period_end_is_future(): void
    {
        Carbon::setTestNow('2026-03-26 12:00:00');

        $subscription = $this->createStripeSubscription([
            'status' => 'active',
            'ends_at' => now()->addMonth(),
        ]);

        $syncService = Mockery::mock(\App\Services\Billing\StripeSubscriptionSyncService::class);
        $syncService->shouldReceive('syncByGatewaySubscriptionId')
            ->once()
            ->with($subscription->gateway_subscription_id)
            ->andReturnUsing(function () use ($subscription) {
                return $subscription->fresh();
            });

        $notificationService = Mockery::mock(\App\Services\Billing\BillingNotificationService::class);
        $notificationService->shouldReceive('subscriptionCancelled')->once();
        $notificationService->shouldNotReceive('subscriptionExpired');

        $service = new StripeWebhookSyncService($syncService, $notificationService);

        $event = (object) [
            'type' => 'customer.subscription.deleted',
            'data' => (object) [
                'object' => (object) [
                    'id' => $subscription->gateway_subscription_id,
                    'current_period_end' => now()->addMonth()->timestamp,
                ],
            ],
        ];

        $service->handleEvent($event);

        $this->assertTrue(true);
    }

    protected function createStripeSubscription(array $overrides = []): Subscription
    {
        return Subscription::query()->create(array_merge([
            'tenant_id' => 'tenant-cancel-test',
            'plan_id' => $this->createPlan('Starter', 'starter-' . uniqid(), 'price_test_cancel_' . uniqid())->id,
            'status' => 'active',
            'trial_ends_at' => null,
            'grace_ends_at' => null,
            'last_payment_failed_at' => null,
            'past_due_started_at' => null,
            'suspended_at' => null,
            'cancelled_at' => null,
            'ends_at' => null,
            'payment_failures_count' => 0,
            'external_id' => null,
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_test_cancel',
            'gateway_subscription_id' => 'sub_test_cancel_' . uniqid(),
            'gateway_checkout_session_id' => 'cs_test_cancel_' . uniqid(),
            'gateway_price_id' => 'price_test_cancel_default',
        ], $overrides));
    }

    protected function createPlan(string $name, string $slug, string $stripePriceId): Plan
    {
        return Plan::query()->create([
            'name' => $name,
            'slug' => $slug,
            'description' => $name . ' description',
            'price' => 199,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => $stripePriceId,
        ]);
    }
}
