<?php

namespace Tests\Feature\Billing;

use App\Http\Controllers\Admin\BillingReportController;
use App\Models\BillingInvoice;
use App\Models\Currency;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class CentralBillingReportControllerStateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-03-26 12:00:00');

        Route::name('admin.reports.billing')->get('/test-admin/reports/billing', fn () => 'billing-report');
        Route::name('admin.reports.billing.export-csv')->get('/test-admin/reports/billing/export', fn () => 'billing-report-export');
    }

    public function test_index_builds_expected_summary_distribution_recent_invoices_and_trend_state(): void
    {
        Currency::query()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'native_symbol' => '$',
            'decimal_places' => 2,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $starter = $this->createPlan('Starter', 'starter', 199, 'monthly', 'USD', 1);
        $growth = $this->createPlan('Growth', 'growth', 399, 'monthly', 'USD', 2);
        $trial = $this->createPlan('Trial', 'trial', 0, 'trial', 'USD', 3);

        $this->createSubscription([
            'tenant_id' => 'tenant-active-1',
            'plan_id' => $starter->id,
            'status' => 'active',
            'gateway' => 'stripe',
        ]);

        $this->createSubscription([
            'tenant_id' => 'tenant-active-2',
            'plan_id' => $growth->id,
            'status' => 'active',
            'gateway' => 'stripe',
        ]);

        $this->createSubscription([
            'tenant_id' => 'tenant-trial',
            'plan_id' => $trial->id,
            'status' => 'trialing',
            'gateway' => null,
            'gateway_customer_id' => null,
            'gateway_subscription_id' => null,
        ]);

        $this->createSubscription([
            'tenant_id' => 'tenant-past-due',
            'plan_id' => $starter->id,
            'status' => 'past_due',
            'gateway' => 'stripe',
        ]);

        $this->createSubscription([
            'tenant_id' => 'tenant-suspended',
            'plan_id' => $starter->id,
            'status' => 'suspended',
            'gateway' => 'stripe',
        ]);

        $activeSubscription = Subscription::query()->where('tenant_id', 'tenant-active-1')->firstOrFail();

        BillingInvoice::query()->create([
            'subscription_id' => $activeSubscription->id,
            'tenant_id' => $activeSubscription->tenant_id,
            'gateway' => 'stripe',
            'gateway_invoice_id' => 'in_report_new',
            'gateway_customer_id' => 'cus_report_001',
            'gateway_subscription_id' => 'sub_report_001',
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
            'hosted_invoice_url' => 'https://example.com/invoices/new',
            'invoice_pdf' => 'https://example.com/invoices/new.pdf',
            'issued_at' => Carbon::parse('2026-03-10 10:00:00'),
            'paid_at' => Carbon::parse('2026-03-10 10:10:00'),
            'raw_payload' => [],
        ]);

        BillingInvoice::query()->create([
            'subscription_id' => $activeSubscription->id,
            'tenant_id' => $activeSubscription->tenant_id,
            'gateway' => 'stripe',
            'gateway_invoice_id' => 'in_report_old',
            'gateway_customer_id' => 'cus_report_001',
            'gateway_subscription_id' => 'sub_report_001',
            'invoice_number' => 'INV-OLD',
            'status' => 'open',
            'billing_reason' => 'subscription_cycle',
            'currency' => 'USD',
            'total_minor' => 19900,
            'total_decimal' => 199.00,
            'amount_paid_minor' => 0,
            'amount_paid_decimal' => 0.00,
            'amount_due_minor' => 19900,
            'amount_due_decimal' => 199.00,
            'hosted_invoice_url' => 'https://example.com/invoices/old',
            'invoice_pdf' => 'https://example.com/invoices/old.pdf',
            'issued_at' => Carbon::parse('2026-02-10 10:00:00'),
            'paid_at' => null,
            'raw_payload' => [],
        ]);

        $request = Request::create('/admin/reports/billing', 'GET', [
            'tenant_id' => '',
            'status' => '',
            'gateway' => '',
            'month' => '',
            'currency' => '',
        ]);

        $controller = new BillingReportController();

        $view = $controller->index($request);

        $this->assertInstanceOf(View::class, $view);

        $data = $view->getData();

        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('activePlanDistribution', $data);
        $this->assertArrayHasKey('gatewayBreakdown', $data);
        $this->assertArrayHasKey('recentInvoices', $data);
        $this->assertArrayHasKey('monthlyInvoiceTrend', $data);
        $this->assertArrayHasKey('filters', $data);
        $this->assertArrayHasKey('filterOptions', $data);

        $summary = $data['summary'];

        $this->assertSame(5, $summary['total_subscriptions']);
        $this->assertSame(2, $summary['active_subscriptions']);
        $this->assertSame(2, $summary['active_paid_subscriptions']);
        $this->assertSame(1, $summary['trialing_subscriptions']);
        $this->assertSame(1, $summary['past_due_subscriptions']);
        $this->assertSame(1, $summary['suspended_subscriptions']);
        $this->assertSame(0, $summary['canceled_subscriptions']);
        $this->assertSame(0, $summary['expired_subscriptions']);

        $mrr = collect($summary['estimated_mrr_by_currency']);
        $this->assertCount(1, $mrr);
        $this->assertSame('USD', $mrr[0]['currency']);
        $this->assertEquals(598.0, $mrr[0]['estimated_mrr']);

        $distribution = collect($data['activePlanDistribution']);
        $this->assertCount(2, $distribution);
        $this->assertSame('Starter', $distribution[0]->plan_name);
        $this->assertSame('Growth', $distribution[1]->plan_name);

        $gatewayBreakdown = collect($data['gatewayBreakdown']);
        $this->assertCount(1, $gatewayBreakdown);
        $this->assertSame('stripe', $gatewayBreakdown[0]->gateway);

        $recentInvoices = collect($data['recentInvoices']);
        $this->assertCount(2, $recentInvoices);
        $this->assertSame('in_report_new', $recentInvoices[0]['id']);
        $this->assertSame('in_report_old', $recentInvoices[1]['id']);

        $trend = collect($data['monthlyInvoiceTrend']);
        $this->assertCount(2, $trend);
        $this->assertSame('2026-03', $trend[0]['month']);
        $this->assertSame('2026-02', $trend[1]['month']);

        $this->assertSame('', $data['filters']['tenant_id']);
        $this->assertSame('', $data['filters']['status']);
        $this->assertSame('', $data['filters']['gateway']);
        $this->assertSame('', $data['filters']['month']);
        $this->assertSame('', $data['filters']['currency']);

        $this->assertArrayHasKey('statuses', $data['filterOptions']);
        $this->assertArrayHasKey('gateways', $data['filterOptions']);
        $this->assertArrayHasKey('currencies', $data['filterOptions']);
        $this->assertArrayHasKey('months', $data['filterOptions']);
    }

    public function test_index_applies_invoice_filters_to_recent_invoices_and_monthly_trend(): void
    {
        Currency::query()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'native_symbol' => '$',
            'decimal_places' => 2,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $starter = $this->createPlan('Starter', 'starter', 199, 'monthly', 'USD', 1);

        $subscriptionA = $this->createSubscription([
            'tenant_id' => 'tenant-filter-a',
            'plan_id' => $starter->id,
            'status' => 'active',
            'gateway' => 'stripe',
        ]);

        $subscriptionB = $this->createSubscription([
            'tenant_id' => 'tenant-filter-b',
            'plan_id' => $starter->id,
            'status' => 'active',
            'gateway' => 'stripe',
        ]);

        BillingInvoice::query()->create([
            'subscription_id' => $subscriptionA->id,
            'tenant_id' => 'tenant-filter-a',
            'gateway' => 'stripe',
            'gateway_invoice_id' => 'in_filter_match',
            'gateway_customer_id' => 'cus_filter_a',
            'gateway_subscription_id' => 'sub_filter_a',
            'invoice_number' => 'INV-FILTER-MATCH',
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
            'issued_at' => Carbon::parse('2026-03-01 10:00:00'),
            'paid_at' => Carbon::parse('2026-03-01 10:05:00'),
            'raw_payload' => [],
        ]);

        BillingInvoice::query()->create([
            'subscription_id' => $subscriptionB->id,
            'tenant_id' => 'tenant-filter-b',
            'gateway' => 'stripe',
            'gateway_invoice_id' => 'in_filter_other',
            'gateway_customer_id' => 'cus_filter_b',
            'gateway_subscription_id' => 'sub_filter_b',
            'invoice_number' => 'INV-FILTER-OTHER',
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
            'issued_at' => Carbon::parse('2026-02-01 10:00:00'),
            'paid_at' => null,
            'raw_payload' => [],
        ]);

        $request = Request::create('/admin/reports/billing', 'GET', [
            'tenant_id' => 'tenant-filter-a',
            'status' => 'paid',
            'gateway' => 'stripe',
            'month' => '2026-03',
            'currency' => 'USD',
        ]);

        $controller = new BillingReportController();

        $view = $controller->index($request);
        $data = $view->getData();

        $recentInvoices = collect($data['recentInvoices']);
        $trend = collect($data['monthlyInvoiceTrend']);

        $this->assertCount(1, $recentInvoices);
        $this->assertSame('in_filter_match', $recentInvoices[0]['id']);

        $this->assertCount(1, $trend);
        $this->assertSame('2026-03', $trend[0]['month']);
        $this->assertEquals(199.00, $trend[0]['amount_paid_decimal']);

        $this->assertSame('tenant-filter-a', $data['filters']['tenant_id']);
        $this->assertSame('paid', $data['filters']['status']);
        $this->assertSame('stripe', $data['filters']['gateway']);
        $this->assertSame('2026-03', $data['filters']['month']);
        $this->assertSame('USD', $data['filters']['currency']);
    }

    public function test_export_csv_returns_streamed_response_with_filter_aware_filename(): void
    {
        Currency::query()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'native_symbol' => '$',
            'decimal_places' => 2,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $starter = $this->createPlan('Starter', 'starter', 199, 'monthly', 'USD', 1);
        $subscription = $this->createSubscription([
            'tenant_id' => 'tenant-export-a',
            'plan_id' => $starter->id,
            'status' => 'active',
            'gateway' => 'stripe',
        ]);

        BillingInvoice::query()->create([
            'subscription_id' => $subscription->id,
            'tenant_id' => 'tenant-export-a',
            'gateway' => 'stripe',
            'gateway_invoice_id' => 'in_export_001',
            'gateway_customer_id' => 'cus_export_001',
            'gateway_subscription_id' => 'sub_export_001',
            'invoice_number' => 'INV-EXPORT-001',
            'status' => 'paid',
            'billing_reason' => 'subscription_cycle',
            'currency' => 'USD',
            'total_minor' => 19900,
            'total_decimal' => 199.00,
            'amount_paid_minor' => 19900,
            'amount_paid_decimal' => 199.00,
            'amount_due_minor' => 0,
            'amount_due_decimal' => 0.00,
            'hosted_invoice_url' => 'https://example.com/export/001',
            'invoice_pdf' => 'https://example.com/export/001.pdf',
            'issued_at' => Carbon::parse('2026-03-01 10:00:00'),
            'paid_at' => Carbon::parse('2026-03-01 10:05:00'),
            'raw_payload' => [],
        ]);

        $request = Request::create('/admin/reports/billing/export', 'GET', [
            'tenant_id' => 'tenant-export-a',
            'status' => 'paid',
            'gateway' => 'stripe',
            'month' => '2026-03',
            'currency' => 'USD',
        ]);

        $controller = new BillingReportController();

        $response = $controller->exportCsv($request);

        $this->assertInstanceOf(StreamedResponse::class, $response);

        $contentDisposition = $response->headers->get('Content-Disposition');
        $this->assertNotNull($contentDisposition);
        $this->assertStringContainsString('billing-invoices', $contentDisposition);
        $this->assertStringContainsString('tenant-tenant-export-a', $contentDisposition);
        $this->assertStringContainsString('status-paid', $contentDisposition);
        $this->assertStringContainsString('gateway-stripe', $contentDisposition);
        $this->assertStringContainsString('currency-USD', $contentDisposition);
        $this->assertStringContainsString('month-2026-03', $contentDisposition);
        $this->assertStringContainsString('.csv', $contentDisposition);
    }

    protected function createSubscription(array $overrides = []): Subscription
    {
        return Subscription::query()->create(array_merge([
            'tenant_id' => 'tenant-central-report-test-' . uniqid(),
            'plan_id' => $this->createPlan('Starter', 'starter-' . uniqid(), 199, 'monthly', 'USD', 1)->id,
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

    protected function createPlan(
        string $name,
        string $slug,
        int|float $price,
        string $billingPeriod,
        string $currency,
        int $sortOrder
    ): Plan {
        return Plan::query()->create([
            'name' => $name,
            'slug' => $slug,
            'description' => $name . ' description',
            'price' => $price,
            'currency' => $currency,
            'billing_period' => $billingPeriod,
            'is_active' => true,
            'sort_order' => $sortOrder,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => 'price_' . uniqid(),
        ]);
    }
}
