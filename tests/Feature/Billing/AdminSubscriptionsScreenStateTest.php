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
use App\Support\Billing\SubscriptionStatuses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Mockery;
use Tests\TestCase;

class AdminSubscriptionsScreenStateTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_index_builds_expected_state_with_filters_status_counts_and_gateway_options(): void
    {
        $starter = $this->createPlan('Starter', 'starter', 'price_starter');
        $growth = $this->createPlan('Growth', 'growth', 'price_growth');

        $this->createSubscription([
            'tenant_id' => 'tenant-active-stripe',
            'plan_id' => $starter->id,
            'status' => SubscriptionStatuses::ACTIVE,
            'gateway' => 'stripe',
        ]);

        $this->createSubscription([
            'tenant_id' => 'tenant-trial',
            'plan_id' => $starter->id,
            'status' => SubscriptionStatuses::TRIALING,
            'gateway' => null,
            'gateway_customer_id' => null,
            'gateway_subscription_id' => null,
        ]);

        $this->createSubscription([
            'tenant_id' => 'tenant-past-due',
            'plan_id' => $growth->id,
            'status' => SubscriptionStatuses::PAST_DUE,
            'gateway' => 'stripe',
        ]);

        $request = Request::create('/admin/subscriptions', 'GET', [
            'tenant_id' => 'tenant',
            'status' => SubscriptionStatuses::ACTIVE,
            'plan_id' => $starter->id,
            'gateway' => 'stripe',
        ]);

        $controller = $this->makeController(
            stripeInvoiceHistoryService: Mockery::mock(StripeInvoiceHistoryService::class),
            tenantBillingLifecycleService: Mockery::mock(TenantBillingLifecycleService::class),
            subscriptionLifecycleNormalizationService: Mockery::mock(SubscriptionLifecycleNormalizationService::class)
        );

        $view = $controller->index($request);

        $this->assertInstanceOf(View::class, $view);

        $data = $view->getData();

        $this->assertArrayHasKey('subscriptions', $data);
        $this->assertArrayHasKey('filters', $data);
        $this->assertArrayHasKey('statusCounts', $data);
        $this->assertArrayHasKey('plans', $data);
        $this->assertArrayHasKey('gatewayOptions', $data);
        $this->assertArrayHasKey('statusOptions', $data);

        $this->assertSame('tenant', $data['filters']['tenant_id']);
        $this->assertSame(SubscriptionStatuses::ACTIVE, $data['filters']['status']);
        $this->assertSame($starter->id, $data['filters']['plan_id']);
        $this->assertSame('stripe', $data['filters']['gateway']);

        $this->assertSame(3, $data['statusCounts']['total']);
        $this->assertSame(1, $data['statusCounts'][SubscriptionStatuses::ACTIVE]);
        $this->assertSame(1, $data['statusCounts'][SubscriptionStatuses::TRIALING]);
        $this->assertSame(1, $data['statusCounts'][SubscriptionStatuses::PAST_DUE]);
        $this->assertSame(0, $data['statusCounts'][SubscriptionStatuses::SUSPENDED]);
        $this->assertSame(0, $data['statusCounts'][SubscriptionStatuses::CANCELLED]);
        $this->assertSame(0, $data['statusCounts'][SubscriptionStatuses::EXPIRED]);

        $this->assertContains('stripe', $data['gatewayOptions']->all());
        $this->assertContains(SubscriptionStatuses::ACTIVE, $data['statusOptions']);
        $this->assertContains(SubscriptionStatuses::TRIALING, $data['statusOptions']);
        $this->assertContains(SubscriptionStatuses::PAST_DUE, $data['statusOptions']);

        $subscriptions = $data['subscriptions'];
        $this->assertSame(1, $subscriptions->count());
        $this->assertSame('tenant-active-stripe', $subscriptions->first()->tenant_id);
    }

    public function test_show_builds_expected_state_with_filtered_invoice_history_resolved_state_and_normalization_preview(): void
    {
        $starter = $this->createPlan('Starter', 'starter', 'price_starter');

        $subscription = $this->createSubscription([
            'tenant_id' => 'tenant-show-test',
            'plan_id' => $starter->id,
            'status' => SubscriptionStatuses::ACTIVE,
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_show_test_001',
            'gateway_subscription_id' => 'sub_show_test_001',
        ]);

        $stripeInvoiceHistoryService = Mockery::mock(StripeInvoiceHistoryService::class);
        $stripeInvoiceHistoryService->shouldReceive('listCustomerInvoices')
            ->once()
            ->with('cus_show_test_001', 15)
            ->andReturn([
                'ok' => true,
                'invoices' => [
                    [
                        'id' => 'in_match_001',
                        'subscription_id' => 'sub_show_test_001',
                    ],
                    [
                        'id' => 'in_other_001',
                        'subscription_id' => 'sub_other_999',
                    ],
                    [
                        'id' => 'in_no_subscription',
                        'subscription_id' => '',
                    ],
                ],
                'message' => null,
            ]);

        $tenantBillingLifecycleService = Mockery::mock(TenantBillingLifecycleService::class);
        $tenantBillingLifecycleService->shouldReceive('resolveState')
            ->once()
            ->with(Mockery::on(fn ($sub) => (string) $sub->tenant_id === 'tenant-show-test'))
            ->andReturn([
                'status' => 'active',
                'allow_access' => true,
            ]);

        $subscriptionLifecycleNormalizationService = Mockery::mock(SubscriptionLifecycleNormalizationService::class);
        $subscriptionLifecycleNormalizationService->shouldReceive('normalizeOne')
            ->once()
            ->with($subscription->id, false)
            ->andReturn([
                'ok' => true,
                'applied' => false,
                'message' => 'Preview only.',
            ]);

        $controller = $this->makeController(
            stripeInvoiceHistoryService: $stripeInvoiceHistoryService,
            tenantBillingLifecycleService: $tenantBillingLifecycleService,
            subscriptionLifecycleNormalizationService: $subscriptionLifecycleNormalizationService
        );

        $view = $controller->show($subscription->id);

        $this->assertInstanceOf(View::class, $view);

        $data = $view->getData();

        $this->assertArrayHasKey('subscription', $data);
        $this->assertArrayHasKey('invoiceHistory', $data);
        $this->assertArrayHasKey('resolvedState', $data);
        $this->assertArrayHasKey('normalizationPreview', $data);

        $this->assertSame($subscription->id, $data['subscription']->id);
        $this->assertSame('active', $data['resolvedState']['status']);
        $this->assertTrue($data['resolvedState']['allow_access']);

        $this->assertTrue($data['invoiceHistory']['ok']);
        $this->assertCount(2, $data['invoiceHistory']['invoices']);
        $this->assertSame('in_match_001', $data['invoiceHistory']['invoices'][0]['id']);
        $this->assertSame('in_no_subscription', $data['invoiceHistory']['invoices'][1]['id']);

        $this->assertTrue($data['normalizationPreview']['ok']);
        $this->assertFalse($data['normalizationPreview']['applied']);
    }

    public function test_show_throws_not_found_for_missing_subscription(): void
    {
        $controller = $this->makeController(
            stripeInvoiceHistoryService: Mockery::mock(StripeInvoiceHistoryService::class),
            tenantBillingLifecycleService: Mockery::mock(TenantBillingLifecycleService::class),
            subscriptionLifecycleNormalizationService: Mockery::mock(SubscriptionLifecycleNormalizationService::class)
        );

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        $controller->show(999999);
    }

    protected function makeController(
        StripeInvoiceHistoryService $stripeInvoiceHistoryService,
        TenantBillingLifecycleService $tenantBillingLifecycleService,
        SubscriptionLifecycleNormalizationService $subscriptionLifecycleNormalizationService
    ): SubscriptionController {
        return new SubscriptionController(
            $stripeInvoiceHistoryService,
            Mockery::mock(StripeSubscriptionSyncService::class),
            Mockery::mock(StripeInvoiceLedgerBackfillService::class),
            $tenantBillingLifecycleService,
            $subscriptionLifecycleNormalizationService,
            Mockery::mock(BillingNotificationService::class),
        );
    }

    protected function createSubscription(array $overrides = []): Subscription
    {
        return Subscription::query()->create(array_merge([
            'tenant_id' => 'tenant-admin-screen-test-' . uniqid(),
            'plan_id' => $this->createPlan('Starter', 'starter-' . uniqid(), 'price_' . uniqid())->id,
            'status' => SubscriptionStatuses::ACTIVE,
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
