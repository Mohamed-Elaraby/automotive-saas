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
use Mockery;
use Tests\TestCase;

class AdminSubscriptionInvoiceHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_loads_and_filters_invoice_history_by_gateway_subscription_id(): void
    {
        $subscription = $this->createSubscription([
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_test_invoice_history',
            'gateway_subscription_id' => 'sub_match_001',
        ]);

        $stripeInvoiceHistoryService = Mockery::mock(StripeInvoiceHistoryService::class);
        $stripeInvoiceHistoryService->shouldReceive('listCustomerInvoices')
            ->once()
            ->with('cus_test_invoice_history', 15)
            ->andReturn([
                'ok' => true,
                'invoices' => [
                    [
                        'id' => 'in_match_001',
                        'subscription_id' => 'sub_match_001',
                        'amount_paid' => 19900,
                    ],
                    [
                        'id' => 'in_other_001',
                        'subscription_id' => 'sub_other_999',
                        'amount_paid' => 39900,
                    ],
                    [
                        'id' => 'in_no_subscription',
                        'subscription_id' => '',
                        'amount_paid' => 1000,
                    ],
                ],
                'message' => null,
            ]);

        $controller = $this->makeController($stripeInvoiceHistoryService);

        $result = $controller->exposedLoadInvoiceHistoryForSubscription((object) $subscription->toArray());

        $this->assertTrue($result['ok']);
        $this->assertCount(2, $result['invoices']);
        $this->assertSame('in_match_001', $result['invoices'][0]['id']);
        $this->assertSame('in_no_subscription', $result['invoices'][1]['id']);
    }

    public function test_it_returns_empty_invoice_history_when_subscription_is_not_stripe_or_customer_missing(): void
    {
        $subscription = $this->createSubscription([
            'gateway' => null,
            'gateway_customer_id' => null,
            'gateway_subscription_id' => 'sub_unused_001',
        ]);

        $stripeInvoiceHistoryService = Mockery::mock(StripeInvoiceHistoryService::class);
        $stripeInvoiceHistoryService->shouldNotReceive('listCustomerInvoices');

        $controller = $this->makeController($stripeInvoiceHistoryService);

        $result = $controller->exposedLoadInvoiceHistoryForSubscription((object) $subscription->toArray());

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['invoices']);
        $this->assertNull($result['message']);
    }

    protected function makeController(StripeInvoiceHistoryService $stripeInvoiceHistoryService): object
    {
        $stripeSubscriptionSyncService = Mockery::mock(StripeSubscriptionSyncService::class);
        $stripeInvoiceLedgerBackfillService = Mockery::mock(StripeInvoiceLedgerBackfillService::class);
        $tenantBillingLifecycleService = Mockery::mock(TenantBillingLifecycleService::class);
        $subscriptionLifecycleNormalizationService = Mockery::mock(SubscriptionLifecycleNormalizationService::class);
        $billingNotificationService = Mockery::mock(BillingNotificationService::class);

        return new class(
            $stripeInvoiceHistoryService,
            $stripeSubscriptionSyncService,
            $stripeInvoiceLedgerBackfillService,
            $tenantBillingLifecycleService,
            $subscriptionLifecycleNormalizationService,
            $billingNotificationService
        ) extends SubscriptionController {
            public function exposedLoadInvoiceHistoryForSubscription(object $subscription): array
            {
                return $this->loadInvoiceHistoryForSubscription($subscription);
            }
        };
    }

    protected function createSubscription(array $overrides = []): Subscription
    {
        return Subscription::query()->create(array_merge([
            'tenant_id' => 'tenant-admin-invoice-history-test',
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
