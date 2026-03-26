<?php

namespace Tests\Feature\Billing;

use App\Models\BillingInvoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\LocalBillingInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LocalBillingInvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected LocalBillingInvoiceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(LocalBillingInvoiceService::class);
    }

    public function test_it_upserts_a_billing_invoice_from_stripe_invoice_using_gateway_subscription_id_match(): void
    {
        $subscription = $this->createSubscription([
            'tenant_id' => 'tenant-local-ledger-subscription-match',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_local_match_001',
            'gateway_subscription_id' => 'sub_local_match_001',
        ]);

        $invoice = (object) [
            'id' => 'in_local_match_001',
            'customer' => 'cus_local_match_001',
            'subscription' => 'sub_local_match_001',
            'number' => 'INV-001',
            'status' => 'paid',
            'billing_reason' => 'subscription_cycle',
            'currency' => 'usd',
            'total' => 19900,
            'amount_paid' => 19900,
            'amount_due' => 0,
            'hosted_invoice_url' => 'https://example.com/invoice/001',
            'invoice_pdf' => 'https://example.com/invoice/001.pdf',
            'created' => 1774500000,
            'status_transitions' => (object) [
                'paid_at' => 1774500300,
            ],
        ];

        $row = $this->service->upsertFromStripeInvoice($invoice);

        $this->assertInstanceOf(BillingInvoice::class, $row);
        $this->assertSame($subscription->id, $row->subscription_id);
        $this->assertSame($subscription->tenant_id, $row->tenant_id);
        $this->assertSame('stripe', $row->gateway);
        $this->assertSame('in_local_match_001', $row->gateway_invoice_id);
        $this->assertSame('cus_local_match_001', $row->gateway_customer_id);
        $this->assertSame('sub_local_match_001', $row->gateway_subscription_id);
        $this->assertSame('INV-001', $row->invoice_number);
        $this->assertSame('paid', $row->status);
        $this->assertSame('subscription_cycle', $row->billing_reason);
        $this->assertSame('USD', $row->currency);
        $this->assertSame(19900, $row->total_minor);
        $this->assertEquals(199.00, (float) $row->total_decimal);
        $this->assertSame(19900, $row->amount_paid_minor);
        $this->assertEquals(199.00, (float) $row->amount_paid_decimal);
        $this->assertSame(0, $row->amount_due_minor);
        $this->assertEquals(0.00, (float) $row->amount_due_decimal);
        $this->assertSame('https://example.com/invoice/001', $row->hosted_invoice_url);
        $this->assertSame('https://example.com/invoice/001.pdf', $row->invoice_pdf);
        $this->assertNotNull($row->issued_at);
        $this->assertNotNull($row->paid_at);
        $this->assertIsArray($row->raw_payload);
    }

    public function test_it_falls_back_to_gateway_customer_id_when_subscription_id_does_not_match(): void
    {
        $subscription = $this->createSubscription([
            'tenant_id' => 'tenant-local-ledger-customer-fallback',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_local_fallback_001',
            'gateway_subscription_id' => 'sub_local_fallback_actual',
        ]);

        $invoice = (object) [
            'id' => 'in_local_fallback_001',
            'customer' => 'cus_local_fallback_001',
            'subscription' => 'sub_non_existing_anywhere',
            'number' => 'INV-002',
            'status' => 'open',
            'billing_reason' => 'subscription_cycle',
            'currency' => 'usd',
            'total' => 39900,
            'amount_paid' => 0,
            'amount_due' => 39900,
            'created' => 1774600000,
            'status_transitions' => (object) [],
        ];

        $row = $this->service->upsertFromStripeInvoice($invoice);

        $this->assertSame($subscription->id, $row->subscription_id);
        $this->assertSame($subscription->tenant_id, $row->tenant_id);
        $this->assertSame('cus_local_fallback_001', $row->gateway_customer_id);
        $this->assertSame('sub_non_existing_anywhere', $row->gateway_subscription_id);
        $this->assertSame('open', $row->status);
        $this->assertEquals(399.00, (float) $row->total_decimal);
        $this->assertEquals(0.00, (float) $row->amount_paid_decimal);
        $this->assertEquals(399.00, (float) $row->amount_due_decimal);
    }

    public function test_it_updates_existing_ledger_row_when_same_gateway_invoice_id_is_received_again(): void
    {
        $subscription = $this->createSubscription([
            'tenant_id' => 'tenant-local-ledger-upsert-update',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_local_update_001',
            'gateway_subscription_id' => 'sub_local_update_001',
        ]);

        $firstInvoice = (object) [
            'id' => 'in_local_update_001',
            'customer' => 'cus_local_update_001',
            'subscription' => 'sub_local_update_001',
            'number' => 'INV-003',
            'status' => 'open',
            'billing_reason' => 'subscription_cycle',
            'currency' => 'usd',
            'total' => 19900,
            'amount_paid' => 0,
            'amount_due' => 19900,
            'created' => 1774700000,
            'status_transitions' => (object) [],
        ];

        $secondInvoice = (object) [
            'id' => 'in_local_update_001',
            'customer' => 'cus_local_update_001',
            'subscription' => 'sub_local_update_001',
            'number' => 'INV-003',
            'status' => 'paid',
            'billing_reason' => 'subscription_cycle',
            'currency' => 'usd',
            'total' => 19900,
            'amount_paid' => 19900,
            'amount_due' => 0,
            'created' => 1774700000,
            'status_transitions' => (object) [
                'paid_at' => 1774700500,
            ],
        ];

        $rowOne = $this->service->upsertFromStripeInvoice($firstInvoice);
        $rowTwo = $this->service->upsertFromStripeInvoice($secondInvoice);

        $this->assertSame($rowOne->id, $rowTwo->id);
        $this->assertDatabaseCount('billing_invoices', 1);

        $rowTwo->refresh();

        $this->assertSame('paid', $rowTwo->status);
        $this->assertEquals(199.00, (float) $rowTwo->amount_paid_decimal);
        $this->assertEquals(0.00, (float) $rowTwo->amount_due_decimal);
        $this->assertNotNull($rowTwo->paid_at);
    }

    public function test_it_can_return_customer_invoice_history_sorted_by_issued_at_desc(): void
    {
        $subscription = $this->createSubscription([
            'tenant_id' => 'tenant-local-ledger-history-customer',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_local_history_001',
            'gateway_subscription_id' => 'sub_local_history_001',
        ]);

        BillingInvoice::query()->create([
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'gateway' => 'stripe',
            'gateway_invoice_id' => 'in_hist_old',
            'gateway_customer_id' => 'cus_local_history_001',
            'gateway_subscription_id' => 'sub_local_history_001',
            'invoice_number' => 'INV-OLD',
            'status' => 'paid',
            'billing_reason' => 'subscription_cycle',
            'currency' => 'USD',
            'total_minor' => 19900,
            'total_decimal' => 199.00,
            'amount_paid_minor' => 19900,
            'amount_paid_decimal' => 199.00,
            'amount_due_minor' => 0,
            'amount_due_decimal' => 0.00,
            'hosted_invoice_url' => null,
            'invoice_pdf' => null,
            'issued_at' => Carbon::parse('2026-02-10 10:00:00'),
            'paid_at' => Carbon::parse('2026-02-10 10:05:00'),
            'raw_payload' => [],
        ]);

        BillingInvoice::query()->create([
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'gateway' => 'stripe',
            'gateway_invoice_id' => 'in_hist_new',
            'gateway_customer_id' => 'cus_local_history_001',
            'gateway_subscription_id' => 'sub_local_history_001',
            'invoice_number' => 'INV-NEW',
            'status' => 'paid',
            'billing_reason' => 'subscription_cycle',
            'currency' => 'USD',
            'total_minor' => 39900,
            'total_decimal' => 399.00,
            'amount_paid_minor' => 39900,
            'amount_paid_decimal' => 399.00,
            'amount_due_minor' => 0,
            'amount_due_decimal' => 0.00,
            'hosted_invoice_url' => null,
            'invoice_pdf' => null,
            'issued_at' => Carbon::parse('2026-03-10 10:00:00'),
            'paid_at' => Carbon::parse('2026-03-10 10:05:00'),
            'raw_payload' => [],
        ]);

        $history = $this->service->getCustomerInvoiceHistory('cus_local_history_001', 20);

        $this->assertTrue($history['ok']);
        $this->assertCount(2, $history['invoices']);
        $this->assertSame('in_hist_new', $history['invoices'][0]['id']);
        $this->assertSame('in_hist_old', $history['invoices'][1]['id']);
    }

    public function test_it_can_return_subscription_invoice_history_sorted_by_issued_at_desc(): void
    {
        $subscription = $this->createSubscription([
            'tenant_id' => 'tenant-local-ledger-history-subscription',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_local_sub_history_001',
            'gateway_subscription_id' => 'sub_local_sub_history_001',
        ]);

        BillingInvoice::query()->create([
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'gateway' => 'stripe',
            'gateway_invoice_id' => 'in_sub_hist_old',
            'gateway_customer_id' => 'cus_local_sub_history_001',
            'gateway_subscription_id' => 'sub_local_sub_history_001',
            'invoice_number' => 'SUB-OLD',
            'status' => 'paid',
            'billing_reason' => 'subscription_cycle',
            'currency' => 'USD',
            'total_minor' => 19900,
            'total_decimal' => 199.00,
            'amount_paid_minor' => 19900,
            'amount_paid_decimal' => 199.00,
            'amount_due_minor' => 0,
            'amount_due_decimal' => 0.00,
            'hosted_invoice_url' => null,
            'invoice_pdf' => null,
            'issued_at' => Carbon::parse('2026-01-10 10:00:00'),
            'paid_at' => Carbon::parse('2026-01-10 10:05:00'),
            'raw_payload' => [],
        ]);

        BillingInvoice::query()->create([
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'gateway' => 'stripe',
            'gateway_invoice_id' => 'in_sub_hist_new',
            'gateway_customer_id' => 'cus_local_sub_history_001',
            'gateway_subscription_id' => 'sub_local_sub_history_001',
            'invoice_number' => 'SUB-NEW',
            'status' => 'open',
            'billing_reason' => 'subscription_cycle',
            'currency' => 'USD',
            'total_minor' => 39900,
            'total_decimal' => 399.00,
            'amount_paid_minor' => 0,
            'amount_paid_decimal' => 0.00,
            'amount_due_minor' => 39900,
            'amount_due_decimal' => 399.00,
            'hosted_invoice_url' => null,
            'invoice_pdf' => null,
            'issued_at' => Carbon::parse('2026-03-11 10:00:00'),
            'paid_at' => null,
            'raw_payload' => [],
        ]);

        $history = $this->service->getSubscriptionInvoiceHistory('sub_local_sub_history_001', 20);

        $this->assertTrue($history['ok']);
        $this->assertCount(2, $history['invoices']);
        $this->assertSame('in_sub_hist_new', $history['invoices'][0]['id']);
        $this->assertSame('in_sub_hist_old', $history['invoices'][1]['id']);
    }

    protected function createSubscription(array $overrides = []): Subscription
    {
        return Subscription::query()->create(array_merge([
            'tenant_id' => 'tenant-local-ledger-test-' . uniqid(),
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
