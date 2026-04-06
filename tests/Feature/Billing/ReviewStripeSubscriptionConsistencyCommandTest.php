<?php

namespace Tests\Feature\Billing;

use App\Services\Billing\StripeSubscriptionConsistencyReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ReviewStripeSubscriptionConsistencyCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_returns_success_when_no_stripe_linked_subscriptions_exist(): void
    {
        $this->artisan('billing:review-stripe-consistency')
            ->expectsOutput('No Stripe-linked subscriptions were found for review.')
            ->assertExitCode(0);
    }

    public function test_it_returns_a_friendly_error_when_review_fails(): void
    {
        $service = Mockery::mock(StripeSubscriptionConsistencyReviewService::class);
        $service->shouldReceive('review')
            ->once()
            ->andThrow(new \RuntimeException('database unavailable'));

        $this->app->instance(StripeSubscriptionConsistencyReviewService::class, $service);

        $this->artisan('billing:review-stripe-consistency')
            ->expectsOutput('Unable to review Stripe subscription consistency: database unavailable')
            ->assertExitCode(1);
    }

    public function test_it_can_filter_output_to_only_remaining_issues(): void
    {
        $service = Mockery::mock(StripeSubscriptionConsistencyReviewService::class);
        $service->shouldReceive('review')
            ->once()
            ->andReturn(collect([
                [
                    'subscription_id' => 10,
                    'tenant_id' => 'tenant-ok',
                    'status_before' => 'past_due',
                    'status_after' => 'active',
                    'plan_before' => '1:starter',
                    'plan_after' => '1:starter',
                    'gateway_subscription_id_before' => 'sub_ok',
                    'gateway_subscription_id_after' => 'sub_ok',
                    'sync_state' => 'SYNCED',
                    'mirror_status' => 'MATCHED',
                    'subscription_invoice_count' => 2,
                    'mixed_customer_invoice_count' => 0,
                    'issues_after' => 'OK',
                    'result' => 'OK',
                ],
                [
                    'subscription_id' => 11,
                    'tenant_id' => 'tenant-bad',
                    'status_before' => 'past_due',
                    'status_after' => 'past_due',
                    'plan_before' => '2:pro',
                    'plan_after' => '2:pro',
                    'gateway_subscription_id_before' => '',
                    'gateway_subscription_id_after' => '',
                    'sync_state' => 'NO_RESULT',
                    'mirror_status' => 'MISSING',
                    'subscription_invoice_count' => 0,
                    'mixed_customer_invoice_count' => 1,
                    'issues_after' => 'missing_product_subscription_mirror',
                    'result' => 'NEEDS_REVIEW',
                ],
            ]));

        $this->app->instance(StripeSubscriptionConsistencyReviewService::class, $service);

        $this->artisan('billing:review-stripe-consistency --only-issues')
            ->expectsTable([
                'Sub ID',
                'Tenant',
                'Status',
                'Plan',
                'Stripe Sub ID',
                'Sync',
                'Mirror',
                'Invoices',
                'Mixed Cust Inv',
                'Issues After',
                'Result',
            ], [[
                'Sub ID' => 11,
                'Tenant' => 'tenant-bad',
                'Status' => 'past_due -> past_due',
                'Plan' => '2:pro -> 2:pro',
                'Stripe Sub ID' => '- -> -',
                'Sync' => 'NO_RESULT',
                'Mirror' => 'MISSING',
                'Invoices' => 0,
                'Mixed Cust Inv' => 1,
                'Issues After' => 'missing_product_subscription_mirror',
                'Result' => 'NEEDS_REVIEW',
            ]])
            ->expectsOutput('Summary: reviewed=2, needs_review=1, synced=1, sync_failed=0')
            ->expectsOutput('Detected 1 Stripe subscription records that still need review.')
            ->assertExitCode(1);
    }

    public function test_it_reports_clean_summary_when_only_issues_filter_finds_nothing(): void
    {
        $service = Mockery::mock(StripeSubscriptionConsistencyReviewService::class);
        $service->shouldReceive('review')
            ->once()
            ->andReturn(collect([
                [
                    'subscription_id' => 10,
                    'tenant_id' => 'tenant-ok',
                    'status_before' => 'past_due',
                    'status_after' => 'active',
                    'plan_before' => '1:starter',
                    'plan_after' => '1:starter',
                    'gateway_subscription_id_before' => 'sub_ok',
                    'gateway_subscription_id_after' => 'sub_ok',
                    'sync_state' => 'SYNCED',
                    'mirror_status' => 'MATCHED',
                    'subscription_invoice_count' => 2,
                    'mixed_customer_invoice_count' => 0,
                    'issues_after' => 'OK',
                    'result' => 'OK',
                ],
            ]));

        $this->app->instance(StripeSubscriptionConsistencyReviewService::class, $service);

        $this->artisan('billing:review-stripe-consistency --only-issues')
            ->expectsOutput('No Stripe subscription issues remain after filtering.')
            ->expectsOutput('Summary: reviewed=1, needs_review=0, synced=1, sync_failed=0')
            ->expectsOutput('Stripe subscription consistency review completed with no remaining issues.')
            ->assertExitCode(0);
    }

    public function test_it_can_render_json_output(): void
    {
        $service = Mockery::mock(StripeSubscriptionConsistencyReviewService::class);
        $service->shouldReceive('review')
            ->once()
            ->andReturn(collect([
                [
                    'subscription_id' => 15,
                    'tenant_id' => 'tenant-json',
                    'status_before' => 'past_due',
                    'status_after' => 'active',
                    'plan_before' => '1:starter',
                    'plan_after' => '1:starter',
                    'gateway_subscription_id_before' => 'sub_json',
                    'gateway_subscription_id_after' => 'sub_json',
                    'sync_state' => 'SYNCED',
                    'mirror_status' => 'MATCHED',
                    'subscription_invoice_count' => 3,
                    'mixed_customer_invoice_count' => 0,
                    'issues_after' => 'OK',
                    'result' => 'OK',
                ],
            ]));

        $this->app->instance(StripeSubscriptionConsistencyReviewService::class, $service);

        $this->artisan('billing:review-stripe-consistency --format=json')
            ->expectsOutputToContain('"Tenant": "tenant-json"')
            ->expectsOutput('Summary: reviewed=1, needs_review=0, synced=1, sync_failed=0')
            ->expectsOutput('Stripe subscription consistency review completed with no remaining issues.')
            ->assertExitCode(0);
    }

    public function test_it_can_write_json_output_to_a_file(): void
    {
        $service = Mockery::mock(StripeSubscriptionConsistencyReviewService::class);
        $service->shouldReceive('review')
            ->once()
            ->andReturn(collect([
                [
                    'subscription_id' => 20,
                    'tenant_id' => 'tenant-file',
                    'status_before' => 'past_due',
                    'status_after' => 'active',
                    'plan_before' => '1:starter',
                    'plan_after' => '1:starter',
                    'gateway_subscription_id_before' => 'sub_file',
                    'gateway_subscription_id_after' => 'sub_file',
                    'sync_state' => 'SYNCED',
                    'mirror_status' => 'MATCHED',
                    'subscription_invoice_count' => 1,
                    'mixed_customer_invoice_count' => 0,
                    'issues_after' => 'OK',
                    'result' => 'OK',
                ],
            ]));

        $this->app->instance(StripeSubscriptionConsistencyReviewService::class, $service);

        $path = sys_get_temp_dir() . '/stripe-consistency-review-' . uniqid() . '.json';

        $this->artisan('billing:review-stripe-consistency --format=json --output=' . $path)
            ->expectsOutput('Summary: reviewed=1, needs_review=0, synced=1, sync_failed=0')
            ->expectsOutput('Wrote review output to: ' . $path)
            ->expectsOutput('Stripe subscription consistency review completed with no remaining issues.')
            ->assertExitCode(0);

        $payload = json_decode((string) file_get_contents($path), true);

        $this->assertSame(1, $payload['summary']['reviewed'] ?? null);
        $this->assertSame('tenant-file', $payload['rows'][0]['Tenant'] ?? null);

        @unlink($path);
    }

    public function test_it_rejects_invalid_format_values(): void
    {
        $this->artisan('billing:review-stripe-consistency --format=xml')
            ->expectsOutput('Invalid format. Allowed values: table, json, csv.')
            ->assertExitCode(1);
    }
}
