<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\TenantProductSubscription;
use App\Services\Automotive\ProvisionTenantWorkspaceService;
use App\Services\Billing\StripeWebhookSyncService;
use App\Services\Billing\TenantProductSubscriptionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class StripeWebhookSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_invoice_payment_failed_marks_subscription_into_failure_flow(): void
    {
        Carbon::setTestNow('2026-03-26 12:00:00');

        $subscription = $this->createStripeSubscription([
            'status' => 'active',
            'payment_failures_count' => 0,
            'last_payment_failed_at' => null,
            'past_due_started_at' => null,
            'grace_ends_at' => null,
            'suspended_at' => null,
        ]);

        $syncService = Mockery::mock(\App\Services\Billing\StripeSubscriptionSyncService::class);
        $syncService->shouldReceive('syncByGatewaySubscriptionId')
            ->once()
            ->with($subscription->gateway_subscription_id)
            ->andReturnUsing(function () use ($subscription) {
                return $subscription->fresh();
            });

        $notificationService = Mockery::mock(\App\Services\Billing\BillingNotificationService::class);
        $notificationService->shouldReceive('paymentFailed')->once();
        $notificationService->shouldReceive('renewalFailed')->once();

        $provisionService = Mockery::mock(ProvisionTenantWorkspaceService::class);
        $provisionService->shouldNotReceive('ensureProvisioned');

        $service = new StripeWebhookSyncService(
            $syncService,
            $notificationService,
            $provisionService,
            app(TenantProductSubscriptionSyncService::class)
        );

        $event = (object) [
            'type' => 'invoice.payment_failed',
            'data' => (object) [
                'object' => (object) [
                    'id' => 'in_test_failed_001',
                    'subscription' => $subscription->gateway_subscription_id,
                    'billing_reason' => 'subscription_cycle',
                    'attempt_count' => 1,
                    'amount_due' => 19900,
                    'amount_paid' => 0,
                    'currency' => 'usd',
                ],
            ],
        ];

        $service->handleEvent($event);

        $subscription->refresh();

        $this->assertSame('active', $subscription->status);
    }

    public function test_invoice_paid_recovers_subscription_from_past_due(): void
    {
        Carbon::setTestNow('2026-03-26 12:00:00');

        $subscription = $this->createStripeSubscription([
            'status' => 'past_due',
            'payment_failures_count' => 2,
            'last_payment_failed_at' => now()->subDay(),
            'past_due_started_at' => now()->subDay(),
            'grace_ends_at' => now()->addDay(),
            'suspended_at' => null,
        ]);

        $syncService = Mockery::mock(\App\Services\Billing\StripeSubscriptionSyncService::class);
        $syncService->shouldReceive('syncByGatewaySubscriptionId')
            ->once()
            ->with($subscription->gateway_subscription_id)
            ->andReturnUsing(function () use ($subscription) {
                return $subscription->fresh();
            });

        $notificationService = Mockery::mock(\App\Services\Billing\BillingNotificationService::class);
        $notificationService->shouldReceive('invoicePaid')->once();
        $notificationService->shouldReceive('renewalSucceeded')->once();

        $provisionService = Mockery::mock(ProvisionTenantWorkspaceService::class);
        $provisionService->shouldNotReceive('ensureProvisioned');

        $service = new StripeWebhookSyncService(
            $syncService,
            $notificationService,
            $provisionService,
            app(TenantProductSubscriptionSyncService::class)
        );

        $event = (object) [
            'type' => 'invoice.paid',
            'data' => (object) [
                'object' => (object) [
                    'id' => 'in_test_paid_001',
                    'subscription' => $subscription->gateway_subscription_id,
                    'billing_reason' => 'subscription_cycle',
                    'amount_due' => 19900,
                    'amount_paid' => 19900,
                    'currency' => 'usd',
                ],
            ],
        ];

        $service->handleEvent($event);

        $subscription->refresh();

        $this->assertSame('past_due', $subscription->status);
    }

    public function test_checkout_session_completed_bootstraps_customer_and_subscription_ids(): void
    {
        Carbon::setTestNow('2026-03-26 12:00:00');

        $subscription = $this->createStripeSubscription([
            'status' => 'trialing',
            'gateway_customer_id' => null,
            'gateway_subscription_id' => null,
            'gateway_checkout_session_id' => 'cs_test_bootstrap_001',
        ]);

        $syncService = Mockery::mock(\App\Services\Billing\StripeSubscriptionSyncService::class);
        $syncService->shouldReceive('syncByGatewaySubscriptionId')
            ->once()
            ->with('sub_test_bootstrap_001')
            ->andReturnUsing(function () use ($subscription) {
                $subscription->update([
                    'status' => 'active',
                    'gateway_customer_id' => 'cus_test_bootstrap_001',
                    'gateway_subscription_id' => 'sub_test_bootstrap_001',
                ]);

                return $subscription->fresh();
            });

        $notificationService = Mockery::mock(\App\Services\Billing\BillingNotificationService::class);
        $notificationService->shouldReceive('checkoutCompleted')->once();

        $provisionService = Mockery::mock(ProvisionTenantWorkspaceService::class);
        $provisionService->shouldReceive('ensureProvisioned')
            ->once()
            ->with((string) $subscription->tenant_id);

        $service = new StripeWebhookSyncService(
            $syncService,
            $notificationService,
            $provisionService,
            app(TenantProductSubscriptionSyncService::class)
        );

        $event = (object) [
            'type' => 'checkout.session.completed',
            'data' => (object) [
                'object' => (object) [
                    'id' => 'cs_test_bootstrap_001',
                    'mode' => 'subscription',
                    'payment_status' => 'paid',
                    'customer' => 'cus_test_bootstrap_001',
                    'subscription' => 'sub_test_bootstrap_001',
                    'metadata' => (object) [
                        'subscription_row_id' => (string) $subscription->id,
                    ],
                ],
            ],
        ];

        $service->handleEvent($event);

        $subscription->refresh();

        $this->assertSame('cus_test_bootstrap_001', $subscription->gateway_customer_id);
        $this->assertSame('sub_test_bootstrap_001', $subscription->gateway_subscription_id);
    }

    public function test_checkout_session_completed_can_create_subscription_from_metadata_and_provision_workspace(): void
    {
        Carbon::setTestNow('2026-03-26 12:00:00');

        $plan = $this->createPlan();

        $syncService = Mockery::mock(\App\Services\Billing\StripeSubscriptionSyncService::class);
        $syncService->shouldReceive('syncByGatewaySubscriptionId')
            ->once()
            ->with('sub_test_metadata_001')
            ->andReturnUsing(function () {
                return Subscription::query()
                    ->where('gateway_subscription_id', 'sub_test_metadata_001')
                    ->first();
            });

        $notificationService = Mockery::mock(\App\Services\Billing\BillingNotificationService::class);
        $notificationService->shouldReceive('checkoutCompleted')->once();

        $provisionService = Mockery::mock(ProvisionTenantWorkspaceService::class);
        $provisionService->shouldReceive('ensureProvisioned')
            ->once()
            ->with('tenant-metadata-webhook');

        $service = new StripeWebhookSyncService(
            $syncService,
            $notificationService,
            $provisionService,
            app(TenantProductSubscriptionSyncService::class)
        );

        $event = (object) [
            'type' => 'checkout.session.completed',
            'data' => (object) [
                'object' => (object) [
                    'id' => 'cs_test_metadata_001',
                    'mode' => 'subscription',
                    'payment_status' => 'paid',
                    'customer' => 'cus_test_metadata_001',
                    'subscription' => 'sub_test_metadata_001',
                    'metadata' => (object) [
                        'tenant_id' => 'tenant-metadata-webhook',
                        'plan_id' => (string) $plan->id,
                    ],
                ],
            ],
        ];

        $service->handleEvent($event);

        $subscription = Subscription::query()
            ->where('tenant_id', 'tenant-metadata-webhook')
            ->firstOrFail();

        $this->assertSame('stripe', $subscription->gateway);
        $this->assertSame('cus_test_metadata_001', $subscription->gateway_customer_id);
        $this->assertSame('sub_test_metadata_001', $subscription->gateway_subscription_id);
        $this->assertSame('cs_test_metadata_001', $subscription->gateway_checkout_session_id);
        $this->assertSame($plan->id, $subscription->plan_id);

        $this->assertDatabaseHas('tenant_product_subscriptions', [
            'tenant_id' => 'tenant-metadata-webhook',
            'plan_id' => $plan->id,
            'gateway_subscription_id' => 'sub_test_metadata_001',
        ]);

        $productSubscription = TenantProductSubscription::query()
            ->where('tenant_id', 'tenant-metadata-webhook')
            ->first();

        $this->assertNotNull($productSubscription);
        $this->assertNotNull($productSubscription->legacy_subscription_id);
    }

    protected function createStripeSubscription(array $overrides = []): Subscription
    {
        return Subscription::query()->create(array_merge([
            'tenant_id' => 'tenant-webhook-test',
            'plan_id' => $this->createPlan()->id,
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
            'gateway_customer_id' => 'cus_test_webhook',
            'gateway_subscription_id' => 'sub_test_webhook_' . uniqid(),
            'gateway_checkout_session_id' => 'cs_test_webhook_' . uniqid(),
            'gateway_price_id' => 'price_test_webhook',
        ], $overrides));
    }

    protected function createPlan(): Plan
    {
        return Plan::query()->create([
            'product_id' => \App\Models\Product::query()->where('code', 'automotive_service')->value('id'),
            'name' => 'Webhook Test Plan ' . uniqid(),
            'slug' => 'webhook-test-plan-' . uniqid(),
            'description' => 'Webhook test plan',
            'price' => 199,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_test_webhook_' . uniqid(),
            'stripe_price_id' => 'price_test_plan_' . uniqid(),
        ]);
    }
}
