<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Billing\BillingNotificationService;
use App\Support\Billing\SubscriptionStatuses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Tests\TestCase;

class BillingLifecycleCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();

        parent::tearDown();
    }

    public function test_billing_run_lifecycle_emits_trial_notifications_and_suspends_overdue_subscriptions(): void
    {
        Carbon::setTestNow('2026-04-04 01:45:00');

        $trialSubscription = $this->createSubscription([
            'status' => SubscriptionStatuses::TRIALING,
            'trial_ends_at' => now()->addDay(),
            'gateway' => null,
            'gateway_customer_id' => null,
            'gateway_subscription_id' => null,
        ]);

        $pastDueSubscription = $this->createSubscription([
            'status' => SubscriptionStatuses::PAST_DUE,
            'grace_ends_at' => now()->subMinute(),
            'past_due_started_at' => now()->subDays(3),
            'gateway' => 'stripe',
        ]);

        $notifications = Mockery::mock(BillingNotificationService::class);
        $notifications->shouldReceive('trialEnding')
            ->once()
            ->with(
                Mockery::on(fn ($subscription) => (int) $subscription->id === (int) $trialSubscription->id),
                Mockery::on(fn ($context) => ($context['source'] ?? null) === 'billing.run_lifecycle')
            );
        $notifications->shouldReceive('suspended')
            ->once()
            ->with(
                Mockery::on(fn ($subscription) => (int) $subscription->id === (int) $pastDueSubscription->id),
                Mockery::on(fn ($context) => ($context['source'] ?? null) === 'billing.run_lifecycle')
            );

        $this->app->instance(BillingNotificationService::class, $notifications);

        $this->artisan('billing:run-lifecycle')
            ->expectsOutput("Trial ending notification emitted for subscription #{$trialSubscription->id}")
            ->expectsOutput("Subscription #{$pastDueSubscription->id} marked as suspended.")
            ->assertExitCode(SymfonyCommand::SUCCESS);

        $this->assertSame(SubscriptionStatuses::TRIALING, $trialSubscription->fresh()->status);
        $this->assertSame(SubscriptionStatuses::SUSPENDED, $pastDueSubscription->fresh()->status);
        $this->assertNotNull($pastDueSubscription->fresh()->suspended_at);
    }

    public function test_tenants_cleanup_expires_ended_trials_without_deleting_recently_expired_tenants(): void
    {
        Carbon::setTestNow('2026-04-04 02:00:00');

        $tenant = Tenant::query()->create([
            'id' => 'tenant-cleanup-expire',
            'data' => ['db_name' => 'tenant_cleanup_expire'],
        ]);

        $subscription = $this->createSubscription([
            'tenant_id' => $tenant->id,
            'status' => SubscriptionStatuses::TRIALING,
            'trial_ends_at' => now()->subDay(),
            'gateway' => null,
            'gateway_customer_id' => null,
            'gateway_subscription_id' => null,
        ]);

        $this->artisan('tenants:cleanup --grace-days=7')
            ->expectsOutput('Starting tenants cleanup...')
            ->expectsOutput('Trials to expire: 1')
            ->expectsOutput("Expiring tenant [{$tenant->id}]")
            ->expectsOutput('Expired tenants past grace period: 0')
            ->expectsOutput('Tenants cleanup finished.')
            ->assertExitCode(SymfonyCommand::SUCCESS);

        $this->assertSame(SubscriptionStatuses::EXPIRED, $subscription->fresh()->status);
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id]);
    }

    public function test_tenants_cleanup_dry_run_does_not_mutate_trial_status(): void
    {
        Carbon::setTestNow('2026-04-04 02:00:00');

        $subscription = $this->createSubscription([
            'tenant_id' => 'tenant-cleanup-dry-run',
            'status' => SubscriptionStatuses::TRIALING,
            'trial_ends_at' => now()->subDay(),
            'gateway' => null,
            'gateway_customer_id' => null,
            'gateway_subscription_id' => null,
        ]);

        $this->artisan('tenants:cleanup --grace-days=7 --dry-run')
            ->expectsOutput('Dry run: yes')
            ->expectsOutput('Trials to expire: 1')
            ->assertExitCode(SymfonyCommand::SUCCESS);

        $this->assertSame(SubscriptionStatuses::TRIALING, $subscription->fresh()->status);
    }

    protected function createSubscription(array $overrides = []): Subscription
    {
        return Subscription::query()->create(array_merge([
            'tenant_id' => 'tenant-lifecycle-command-' . uniqid(),
            'plan_id' => $this->createPlan()->id,
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

    protected function createPlan(): Plan
    {
        return Plan::query()->create([
            'name' => 'Lifecycle Command Plan ' . uniqid(),
            'slug' => 'lifecycle-command-plan-' . uniqid(),
            'description' => 'Lifecycle command test plan',
            'price' => 199,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => 'price_' . uniqid(),
        ]);
    }
}
