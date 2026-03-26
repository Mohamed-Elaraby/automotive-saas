<?php

namespace Tests\Feature\Billing;

use App\Http\Controllers\Admin\SubscriptionController;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\BillingNotificationService;
use App\Services\Billing\StripeInvoiceHistoryService;
use App\Services\Billing\StripeInvoiceLedgerBackfillService;
use App\Services\Billing\StripeSubscriptionSyncService;
use App\Services\Billing\SubscriptionLifecycleNormalizationService;
use App\Services\Billing\TenantBillingLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;

class AdminSubscriptionLifecycleActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::name('admin.subscriptions.index')->get('/test-admin/subscriptions', fn () => 'index');
        Route::name('admin.subscriptions.show')->get('/test-admin/subscriptions/{subscription}', fn ($subscription) => 'show-' . $subscription);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_sync_from_stripe_rejects_missing_subscription(): void
    {
        $controller = $this->makeController(
            stripeSubscriptionSyncService: Mockery::mock(StripeSubscriptionSyncService::class),
            tenantBillingLifecycleService: Mockery::mock(TenantBillingLifecycleService::class),
            subscriptionLifecycleNormalizationService: Mockery::mock(SubscriptionLifecycleNormalizationService::class),
            billingNotificationService: Mockery::mock(BillingNotificationService::class),
        );

        $response = $controller->syncFromStripe(999999);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('admin.subscriptions.index'), $response->getTargetUrl());
        $this->assertSame('The subscription record was not found.', session('error'));
    }

    public function test_sync_from_stripe_rejects_non_stripe_subscription(): void
    {
        $subscription = $this->createSubscription([
            'gateway' => null,
            'gateway_subscription_id' => null,
        ]);

        $syncService = Mockery::mock(StripeSubscriptionSyncService::class);
        $syncService->shouldNotReceive('syncByGatewaySubscriptionId');

        $controller = $this->makeController(
            stripeSubscriptionSyncService: $syncService,
            tenantBillingLifecycleService: Mockery::mock(TenantBillingLifecycleService::class),
            subscriptionLifecycleNormalizationService: Mockery::mock(SubscriptionLifecycleNormalizationService::class),
            billingNotificationService: Mockery::mock(BillingNotificationService::class),
        );

        $response = $controller->syncFromStripe($subscription->id);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('admin.subscriptions.show', $subscription->id), $response->getTargetUrl());
        $this->assertSame('This subscription is not linked to the Stripe gateway.', session('error'));
    }

    public function test_sync_from_stripe_rejects_missing_gateway_subscription_id(): void
    {
        $subscription = $this->createSubscription([
            'gateway' => 'stripe',
            'gateway_subscription_id' => null,
        ]);

        $syncService = Mockery::mock(StripeSubscriptionSyncService::class);
        $syncService->shouldNotReceive('syncByGatewaySubscriptionId');

        $controller = $this->makeController(
            stripeSubscriptionSyncService: $syncService,
            tenantBillingLifecycleService: Mockery::mock(TenantBillingLifecycleService::class),
            subscriptionLifecycleNormalizationService: Mockery::mock(SubscriptionLifecycleNormalizationService::class),
            billingNotificationService: Mockery::mock(BillingNotificationService::class),
        );

        $response = $controller->syncFromStripe($subscription->id);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('admin.subscriptions.show', $subscription->id), $response->getTargetUrl());
        $this->assertSame('No Stripe subscription ID is linked to this subscription.', session('error'));
    }

    public function test_sync_from_stripe_calls_sync_service_and_emits_manual_sync_notification(): void
    {
        $subscription = $this->createSubscription([
            'gateway' => 'stripe',
            'gateway_subscription_id' => 'sub_admin_sync_001',
        ]);

        $syncService = Mockery::mock(StripeSubscriptionSyncService::class);
        $syncService->shouldReceive('syncByGatewaySubscriptionId')
            ->once()
            ->with('sub_admin_sync_001')
            ->andReturn($subscription);

        $billingNotificationService = Mockery::mock(BillingNotificationService::class);
        $billingNotificationService->shouldReceive('manualSync')
            ->once()
            ->with(
                Mockery::on(fn ($sub) => (int) $sub->id === (int) $subscription->id),
                Mockery::on(fn ($context) => ($context['source'] ?? null) === 'admin.sync_from_stripe'
                    && ($context['gateway_subscription_id'] ?? null) === 'sub_admin_sync_001')
            );

        $controller = $this->makeController(
            stripeSubscriptionSyncService: $syncService,
            tenantBillingLifecycleService: Mockery::mock(TenantBillingLifecycleService::class),
            subscriptionLifecycleNormalizationService: Mockery::mock(SubscriptionLifecycleNormalizationService::class),
            billingNotificationService: $billingNotificationService,
        );

        $response = $controller->syncFromStripe($subscription->id);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('admin.subscriptions.show', $subscription->id), $response->getTargetUrl());
        $this->assertSame('Subscription data was synced successfully from Stripe.', session('success'));
    }

    public function test_refresh_state_calls_lifecycle_service_and_emits_manual_refresh_notification(): void
    {
        $subscription = $this->createSubscription([
            'status' => 'past_due',
        ]);

        $tenantBillingLifecycleService = Mockery::mock(TenantBillingLifecycleService::class);
        $tenantBillingLifecycleService->shouldReceive('resolveState')
            ->once()
            ->with(Mockery::on(fn ($sub) => (int) $sub->id === (int) $subscription->id))
            ->andReturn([
                'status' => 'grace_period',
                'allow_access' => true,
            ]);

        $billingNotificationService = Mockery::mock(BillingNotificationService::class);
        $billingNotificationService->shouldReceive('manualRefreshState')
            ->once()
            ->with(
                Mockery::on(fn ($sub) => (int) $sub->id === (int) $subscription->id),
                Mockery::on(fn ($context) => ($context['source'] ?? null) === 'admin.refresh_state'
                    && ($context['resolved_state']['status'] ?? null) === 'grace_period')
            );

        $controller = $this->makeController(
            stripeSubscriptionSyncService: Mockery::mock(StripeSubscriptionSyncService::class),
            tenantBillingLifecycleService: $tenantBillingLifecycleService,
            subscriptionLifecycleNormalizationService: Mockery::mock(SubscriptionLifecycleNormalizationService::class),
            billingNotificationService: $billingNotificationService,
        );

        $response = $controller->refreshState($subscription->id);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('admin.subscriptions.show', $subscription->id), $response->getTargetUrl());
        $this->assertSame('Local billing state was refreshed. Current resolved status: Grace period', session('success'));
    }

    public function test_normalize_lifecycle_rejects_missing_subscription(): void
    {
        $controller = $this->makeController(
            stripeSubscriptionSyncService: Mockery::mock(StripeSubscriptionSyncService::class),
            tenantBillingLifecycleService: Mockery::mock(TenantBillingLifecycleService::class),
            subscriptionLifecycleNormalizationService: Mockery::mock(SubscriptionLifecycleNormalizationService::class),
            billingNotificationService: Mockery::mock(BillingNotificationService::class),
        );

        $response = $controller->normalizeLifecycle(999999);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('admin.subscriptions.index'), $response->getTargetUrl());
        $this->assertSame('The subscription record was not found.', session('error'));
    }

    public function test_normalize_lifecycle_returns_error_when_normalization_fails(): void
    {
        $subscription = $this->createSubscription();

        $normalizer = Mockery::mock(SubscriptionLifecycleNormalizationService::class);
        $normalizer->shouldReceive('normalizeOne')
            ->once()
            ->with($subscription->id, true)
            ->andReturn([
                'ok' => false,
                'message' => 'Unable to normalize lifecycle fields.',
            ]);

        $billingNotificationService = Mockery::mock(BillingNotificationService::class);
        $billingNotificationService->shouldNotReceive('manualNormalizeLifecycle');

        $controller = $this->makeController(
            stripeSubscriptionSyncService: Mockery::mock(StripeSubscriptionSyncService::class),
            tenantBillingLifecycleService: Mockery::mock(TenantBillingLifecycleService::class),
            subscriptionLifecycleNormalizationService: $normalizer,
            billingNotificationService: $billingNotificationService,
        );

        $response = $controller->normalizeLifecycle($subscription->id);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('admin.subscriptions.show', $subscription->id), $response->getTargetUrl());
        $this->assertSame('Unable to normalize lifecycle fields.', session('error'));
    }

    public function test_normalize_lifecycle_emits_manual_notification_when_successful(): void
    {
        $subscription = $this->createSubscription();

        $normalizer = Mockery::mock(SubscriptionLifecycleNormalizationService::class);
        $normalizer->shouldReceive('normalizeOne')
            ->once()
            ->with($subscription->id, true)
            ->andReturn([
                'ok' => true,
                'applied' => true,
                'message' => 'Lifecycle fields were normalized successfully.',
            ]);

        $billingNotificationService = Mockery::mock(BillingNotificationService::class);
        $billingNotificationService->shouldReceive('manualNormalizeLifecycle')
            ->once()
            ->with(
                Mockery::on(fn ($sub) => (int) $sub->id === (int) $subscription->id),
                true,
                Mockery::on(fn ($context) => ($context['source'] ?? null) === 'admin.normalize_lifecycle'
                    && ($context['normalization_result']['ok'] ?? null) === true)
            );

        $controller = $this->makeController(
            stripeSubscriptionSyncService: Mockery::mock(StripeSubscriptionSyncService::class),
            tenantBillingLifecycleService: Mockery::mock(TenantBillingLifecycleService::class),
            subscriptionLifecycleNormalizationService: $normalizer,
            billingNotificationService: $billingNotificationService,
        );

        $response = $controller->normalizeLifecycle($subscription->id);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('admin.subscriptions.show', $subscription->id), $response->getTargetUrl());
        $this->assertSame('Lifecycle fields were normalized successfully.', session('success'));
    }

    protected function makeController(
        StripeSubscriptionSyncService $stripeSubscriptionSyncService,
        TenantBillingLifecycleService $tenantBillingLifecycleService,
        SubscriptionLifecycleNormalizationService $subscriptionLifecycleNormalizationService,
        BillingNotificationService $billingNotificationService
    ): SubscriptionController {
        return new SubscriptionController(
            Mockery::mock(StripeInvoiceHistoryService::class),
            $stripeSubscriptionSyncService,
            Mockery::mock(StripeInvoiceLedgerBackfillService::class),
            $tenantBillingLifecycleService,
            $subscriptionLifecycleNormalizationService,
            $billingNotificationService
        );
    }

    protected function createSubscription(array $overrides = []): Subscription
    {
        return Subscription::query()->create(array_merge([
            'tenant_id' => 'tenant-admin-lifecycle-actions-' . uniqid(),
            'plan_id' => $this->createPlan('Starter', 'starter-' . uniqid(), 'price_' . uniqid())->id,
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
            'gateway_customer_id' => 'cus_' . uniqid(),
            'gateway_subscription_id' => 'sub_' . uniqid(),
            'gateway_checkout_session_id' => 'cs_' . uniqid(),
            'gateway_price_id' => 'price_' . uniqid(),
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
