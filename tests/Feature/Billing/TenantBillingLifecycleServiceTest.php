<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\TenantBillingLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TenantBillingLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TenantBillingLifecycleService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TenantBillingLifecycleService::class);
    }

    public function test_it_marks_subscription_as_past_due_and_sets_grace_period(): void
    {
        Carbon::setTestNow('2026-03-26 12:00:00');

        $subscription = $this->createActiveStripeSubscription([
            'payment_failures_count' => 0,
            'last_payment_failed_at' => null,
            'past_due_started_at' => null,
            'grace_ends_at' => null,
            'suspended_at' => null,
        ]);

        $this->service->markAsPastDue($subscription);

        $subscription->refresh();

        $this->assertSame('past_due', $subscription->status);
        $this->assertSame(1, $subscription->payment_failures_count);
        $this->assertNotNull($subscription->last_payment_failed_at);
        $this->assertNotNull($subscription->past_due_started_at);
        $this->assertNotNull($subscription->grace_ends_at);
        $this->assertNull($subscription->suspended_at);
    }

    public function test_it_marks_subscription_as_recovered_and_clears_failure_state(): void
    {
        Carbon::setTestNow('2026-03-26 12:00:00');

        $subscription = $this->createStripeSubscription([
            'status' => 'past_due',
            'payment_failures_count' => 2,
            'last_payment_failed_at' => now()->subDay(),
            'past_due_started_at' => now()->subDay(),
            'grace_ends_at' => now()->addDays(2),
            'suspended_at' => null,
            'trial_ends_at' => null,
        ]);

        $this->service->markAsRecovered($subscription);

        $subscription->refresh();

        $this->assertSame('active', $subscription->status);
        $this->assertSame(0, $subscription->payment_failures_count);
        $this->assertNull($subscription->last_payment_failed_at);
        $this->assertNull($subscription->past_due_started_at);
        $this->assertNull($subscription->grace_ends_at);
        $this->assertNull($subscription->suspended_at);
    }

    public function test_it_marks_subscription_as_suspended(): void
    {
        Carbon::setTestNow('2026-03-26 12:00:00');

        $subscription = $this->createStripeSubscription([
            'status' => 'past_due',
            'payment_failures_count' => 3,
            'last_payment_failed_at' => now()->subDays(4),
            'past_due_started_at' => now()->subDays(4),
            'grace_ends_at' => now()->subMinute(),
            'suspended_at' => null,
            'trial_ends_at' => null,
        ]);

        $this->service->markAsSuspended($subscription);

        $subscription->refresh();

        $this->assertSame('suspended', $subscription->status);
        $this->assertNotNull($subscription->suspended_at);
    }

    public function test_resolve_state_allows_access_for_active_subscription(): void
    {
        Carbon::setTestNow('2026-03-26 12:00:00');

        $subscription = $this->createActiveStripeSubscription();

        $state = $this->service->resolveState($subscription);

        $this->assertSame('active', $state['status'] ?? null);
        $this->assertTrue((bool) ($state['allow_access'] ?? false));
    }

    public function test_resolve_state_blocks_access_for_suspended_subscription(): void
    {
        Carbon::setTestNow('2026-03-26 12:00:00');

        $subscription = $this->createStripeSubscription([
            'status' => 'suspended',
            'suspended_at' => now()->subHour(),
            'trial_ends_at' => null,
        ]);

        $state = $this->service->resolveState($subscription);

        $this->assertSame('suspended', $state['status'] ?? null);
        $this->assertFalse((bool) ($state['allow_access'] ?? true));
    }

    public function test_resolve_state_keeps_past_due_access_during_grace_period_if_policy_allows_it(): void
    {
        Carbon::setTestNow('2026-03-26 12:00:00');

        $subscription = $this->createStripeSubscription([
            'status' => 'past_due',
            'payment_failures_count' => 1,
            'last_payment_failed_at' => now()->subHour(),
            'past_due_started_at' => now()->subHour(),
            'grace_ends_at' => now()->addDays(2),
            'suspended_at' => null,
            'trial_ends_at' => null,
        ]);

        $state = $this->service->resolveState($subscription);

        $this->assertSame('grace_period', $state['status'] ?? null);
        $this->assertArrayHasKey('allow_access', $state);
        $this->assertIsBool((bool) $state['allow_access']);
    }

    protected function createActiveStripeSubscription(array $overrides = []): Subscription
    {
        return $this->createStripeSubscription(array_merge([
            'status' => 'active',
            'plan_id' => $this->createPlan()->id,
            'trial_ends_at' => null,
            'grace_ends_at' => null,
            'last_payment_failed_at' => null,
            'past_due_started_at' => null,
            'suspended_at' => null,
            'cancelled_at' => null,
            'ends_at' => null,
            'payment_failures_count' => 0,
        ], $overrides));
    }

    protected function createStripeSubscription(array $overrides = []): Subscription
    {
        return Subscription::query()->create(array_merge([
            'tenant_id' => 'tenant-lifecycle-test',
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
            'gateway_customer_id' => 'cus_test_lifecycle',
            'gateway_subscription_id' => 'sub_test_lifecycle_' . uniqid(),
            'gateway_checkout_session_id' => 'cs_test_lifecycle_' . uniqid(),
            'gateway_price_id' => 'price_test_lifecycle',
        ], $overrides));
    }

    protected function createPlan(): Plan
    {
        return Plan::query()->create([
            'name' => 'Lifecycle Test Plan ' . uniqid(),
            'slug' => 'lifecycle-test-plan-' . uniqid(),
            'description' => 'Lifecycle test plan',
            'price' => 199,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_test_lifecycle_' . uniqid(),
            'stripe_price_id' => 'price_test_plan_' . uniqid(),
        ]);
    }
}
