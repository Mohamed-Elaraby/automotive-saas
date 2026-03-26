<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\StripeInvoiceLedgerBackfillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Tests\TestCase;

class BackfillStripeInvoicesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_warns_when_no_stripe_linked_subscriptions_are_found(): void
    {
        $this->createSubscription([
            'gateway' => null,
            'gateway_customer_id' => null,
        ]);

        $service = Mockery::mock(StripeInvoiceLedgerBackfillService::class);
        $service->shouldNotReceive('backfillForSubscription');

        $this->app->instance(StripeInvoiceLedgerBackfillService::class, $service);

        $this->artisan('billing:backfill-stripe-invoices')
            ->expectsOutput('No Stripe-linked subscriptions found.')
            ->assertExitCode(SymfonyCommand::SUCCESS);
    }

    public function test_it_backfills_all_matching_stripe_linked_subscriptions(): void
    {
        $subscriptionOne = $this->createSubscription([
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_backfill_cmd_001',
        ]);

        $subscriptionTwo = $this->createSubscription([
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_backfill_cmd_002',
        ]);

        $this->createSubscription([
            'gateway' => null,
            'gateway_customer_id' => null,
        ]);

        $service = Mockery::mock(StripeInvoiceLedgerBackfillService::class);

        $service->shouldReceive('backfillForSubscription')
            ->once()
            ->with(Mockery::on(fn ($sub) => (int) $sub->id === (int) $subscriptionOne->id), 100)
            ->andReturn([
                'ok' => true,
                'message' => 'Imported 2 invoices.',
            ]);

        $service->shouldReceive('backfillForSubscription')
            ->once()
            ->with(Mockery::on(fn ($sub) => (int) $sub->id === (int) $subscriptionTwo->id), 100)
            ->andReturn([
                'ok' => false,
                'message' => 'Stripe request failed.',
            ]);

        $this->app->instance(StripeInvoiceLedgerBackfillService::class, $service);

        $this->artisan('billing:backfill-stripe-invoices')
            ->expectsOutput("Subscription #{$subscriptionOne->id}: Imported 2 invoices.")
            ->expectsOutput("Subscription #{$subscriptionTwo->id}: Stripe request failed.")
            ->assertExitCode(SymfonyCommand::SUCCESS);
    }

    public function test_it_can_target_a_single_subscription_by_id(): void
    {
        $targetSubscription = $this->createSubscription([
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_backfill_cmd_target',
        ]);

        $otherSubscription = $this->createSubscription([
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_backfill_cmd_other',
        ]);

        $service = Mockery::mock(StripeInvoiceLedgerBackfillService::class);

        $service->shouldReceive('backfillForSubscription')
            ->once()
            ->with(Mockery::on(fn ($sub) => (int) $sub->id === (int) $targetSubscription->id), 100)
            ->andReturn([
                'ok' => true,
                'message' => 'Imported only target subscription invoices.',
            ]);

        $service->shouldNotReceive('backfillForSubscription')
            ->with(Mockery::on(fn ($sub) => (int) $sub->id === (int) $otherSubscription->id), Mockery::any());

        $this->app->instance(StripeInvoiceLedgerBackfillService::class, $service);

        $this->artisan("billing:backfill-stripe-invoices {$targetSubscription->id}")
            ->expectsOutput("Subscription #{$targetSubscription->id}: Imported only target subscription invoices.")
            ->assertExitCode(SymfonyCommand::SUCCESS);
    }

    public function test_it_passes_custom_limit_to_backfill_service(): void
    {
        $subscription = $this->createSubscription([
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_backfill_cmd_limit',
        ]);

        $service = Mockery::mock(StripeInvoiceLedgerBackfillService::class);

        $service->shouldReceive('backfillForSubscription')
            ->once()
            ->with(Mockery::on(fn ($sub) => (int) $sub->id === (int) $subscription->id), 25)
            ->andReturn([
                'ok' => true,
                'message' => 'Imported with custom limit.',
            ]);

        $this->app->instance(StripeInvoiceLedgerBackfillService::class, $service);

        $this->artisan('billing:backfill-stripe-invoices --limit=25')
            ->expectsOutput("Subscription #{$subscription->id}: Imported with custom limit.")
            ->assertExitCode(SymfonyCommand::SUCCESS);
    }

    protected function createSubscription(array $overrides = []): Subscription
    {
        return Subscription::query()->create(array_merge([
            'tenant_id' => 'tenant-backfill-command-test-' . uniqid(),
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
