<?php

namespace Tests\Feature\Billing;

use App\Http\Controllers\Admin\SubscriptionController;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Admin\AdminActivityLogger;
use App\Services\Admin\AdminSubscriptionControlService;
use App\Services\Billing\BillingNotificationService;
use App\Services\Billing\StripeInvoiceHistoryService;
use App\Services\Billing\StripeInvoiceLedgerBackfillService;
use App\Services\Billing\StripeSubscriptionManagementService;
use App\Services\Billing\StripeSubscriptionPlanChangeService;
use App\Services\Billing\StripeSubscriptionSyncService;
use App\Services\Billing\SubscriptionLifecycleNormalizationService;
use App\Services\Billing\TenantBillingLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;

class AdminSubscriptionInvoiceBackfillTest extends TestCase
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

    public function test_it_redirects_with_error_when_subscription_is_missing(): void
    {
        $backfillService = Mockery::mock(StripeInvoiceLedgerBackfillService::class);
        $backfillService->shouldNotReceive('backfillForSubscription');

        $controller = $this->makeController($backfillService);

        $response = $controller->backfillInvoices(Request::create('/test-admin/subscriptions/999999/backfill-invoices', 'POST'), 999999);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('admin.subscriptions.show', 999999), $response->getTargetUrl());
        $this->assertSame('The subscription record was not found.', session('error'));
    }

    public function test_it_rejects_backfill_when_subscription_is_not_linked_to_stripe_gateway(): void
    {
        $subscription = $this->createSubscription([
            'gateway' => null,
            'gateway_customer_id' => null,
        ]);

        $backfillService = Mockery::mock(StripeInvoiceLedgerBackfillService::class);
        $backfillService->shouldNotReceive('backfillForSubscription');

        $controller = $this->makeController($backfillService);

        $response = $controller->backfillInvoices(Request::create('/test-admin/subscriptions/' . $subscription->id . '/backfill-invoices', 'POST'), $subscription->id);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('admin.subscriptions.show', $subscription->id), $response->getTargetUrl());
        $this->assertSame('This subscription is not linked to the Stripe gateway.', session('error'));
    }

    public function test_it_rejects_backfill_when_customer_id_is_missing(): void
    {
        $subscription = $this->createSubscription([
            'gateway' => 'stripe',
            'gateway_customer_id' => null,
        ]);

        $backfillService = Mockery::mock(StripeInvoiceLedgerBackfillService::class);
        $backfillService->shouldNotReceive('backfillForSubscription');

        $controller = $this->makeController($backfillService);

        $response = $controller->backfillInvoices(Request::create('/test-admin/subscriptions/' . $subscription->id . '/backfill-invoices', 'POST'), $subscription->id);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('admin.subscriptions.show', $subscription->id), $response->getTargetUrl());
        $this->assertSame('No Stripe customer ID is linked to this subscription.', session('error'));
    }

    public function test_it_calls_backfill_service_and_returns_success_message(): void
    {
        $subscription = $this->createSubscription([
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_backfill_test_001',
            'gateway_subscription_id' => 'sub_backfill_test_001',
        ]);

        $backfillService = Mockery::mock(StripeInvoiceLedgerBackfillService::class);
        $backfillService->shouldReceive('backfillForSubscription')
            ->once()
            ->with(Mockery::on(function ($incoming) use ($subscription) {
                return (int) $incoming->id === (int) $subscription->id
                    && (string) $incoming->gateway_customer_id === 'cus_backfill_test_001';
            }), 100)
            ->andReturn([
                'ok' => true,
                'message' => 'Stripe invoices were backfilled successfully.',
            ]);

        $controller = $this->makeController($backfillService);

        $response = $controller->backfillInvoices(Request::create('/test-admin/subscriptions/' . $subscription->id . '/backfill-invoices', 'POST'), $subscription->id);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('admin.subscriptions.show', $subscription->id), $response->getTargetUrl());
        $this->assertSame('Stripe invoices were backfilled successfully.', session('success'));
    }

    public function test_it_returns_error_message_when_backfill_service_reports_failure(): void
    {
        $subscription = $this->createSubscription([
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_backfill_test_002',
            'gateway_subscription_id' => 'sub_backfill_test_002',
        ]);

        $backfillService = Mockery::mock(StripeInvoiceLedgerBackfillService::class);
        $backfillService->shouldReceive('backfillForSubscription')
            ->once()
            ->with(Mockery::type(Subscription::class), 100)
            ->andReturn([
                'ok' => false,
                'message' => 'No Stripe invoices could be imported for this subscription.',
            ]);

        $controller = $this->makeController($backfillService);

        $response = $controller->backfillInvoices(Request::create('/test-admin/subscriptions/' . $subscription->id . '/backfill-invoices', 'POST'), $subscription->id);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('admin.subscriptions.show', $subscription->id), $response->getTargetUrl());
        $this->assertSame('No Stripe invoices could be imported for this subscription.', session('error'));
    }

    protected function makeController(StripeInvoiceLedgerBackfillService $backfillService): SubscriptionController
    {
        $stripeInvoiceHistoryService = Mockery::mock(StripeInvoiceHistoryService::class);
        $stripeSubscriptionSyncService = Mockery::mock(StripeSubscriptionSyncService::class);
        $tenantBillingLifecycleService = Mockery::mock(TenantBillingLifecycleService::class);
        $subscriptionLifecycleNormalizationService = Mockery::mock(SubscriptionLifecycleNormalizationService::class);
        $billingNotificationService = Mockery::mock(BillingNotificationService::class);

        return new SubscriptionController(
            Mockery::mock(AdminSubscriptionControlService::class),
            Mockery::mock(AdminActivityLogger::class),
            $stripeInvoiceHistoryService,
            $stripeSubscriptionSyncService,
            Mockery::mock(StripeSubscriptionManagementService::class),
            Mockery::mock(StripeSubscriptionPlanChangeService::class),
            $backfillService,
            $tenantBillingLifecycleService,
            $subscriptionLifecycleNormalizationService,
            $billingNotificationService
        );
    }

    protected function createSubscription(array $overrides = []): Subscription
    {
        return Subscription::query()->create(array_merge([
            'tenant_id' => 'tenant-admin-backfill-test',
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
            'gateway_customer_id' => 'cus_default_' . uniqid(),
            'gateway_subscription_id' => 'sub_default_' . uniqid(),
            'gateway_checkout_session_id' => 'cs_default_' . uniqid(),
            'gateway_price_id' => 'price_default_' . uniqid(),
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
