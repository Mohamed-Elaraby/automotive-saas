<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\TenantCleanupEligibilityService;
use App\Support\Billing\SubscriptionStatuses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantCleanupEligibilityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TenantCleanupEligibilityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TenantCleanupEligibilityService::class);
    }

    public function test_it_allows_cleanup_when_tenant_has_only_expired_trials_without_billing_linkage(): void
    {
        $tenantId = 'tenant-cleanup-eligible';

        $this->createSubscription([
            'tenant_id' => $tenantId,
            'status' => SubscriptionStatuses::EXPIRED,
            'trial_ends_at' => now()->subDays(10),
            'gateway' => null,
            'gateway_customer_id' => null,
            'gateway_subscription_id' => null,
            'gateway_checkout_session_id' => null,
            'gateway_price_id' => null,
        ]);

        $result = $this->service->evaluateAutomaticCleanup($tenantId);

        $this->assertTrue($result['eligible']);
        $this->assertSame('expired_trials_only', $result['reason']);
    }

    public function test_it_blocks_cleanup_for_expired_paid_subscription_without_trial_marker(): void
    {
        $tenantId = 'tenant-cleanup-paid';

        $this->createSubscription([
            'tenant_id' => $tenantId,
            'status' => SubscriptionStatuses::EXPIRED,
            'trial_ends_at' => null,
            'ends_at' => now()->subDays(10),
            'gateway' => null,
            'gateway_customer_id' => null,
            'gateway_subscription_id' => null,
            'gateway_checkout_session_id' => null,
            'gateway_price_id' => null,
        ]);

        $result = $this->service->evaluateAutomaticCleanup($tenantId);

        $this->assertFalse($result['eligible']);
        $this->assertSame('non_trial_or_non_terminal_subscription', $result['reason']);
    }

    public function test_it_blocks_cleanup_when_any_subscription_has_stripe_linkage(): void
    {
        $tenantId = 'tenant-cleanup-stripe';

        $this->createSubscription([
            'tenant_id' => $tenantId,
            'status' => SubscriptionStatuses::EXPIRED,
            'trial_ends_at' => now()->subDays(10),
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_cleanup_service',
            'gateway_subscription_id' => 'sub_cleanup_service',
        ]);

        $result = $this->service->evaluateAutomaticCleanup($tenantId);

        $this->assertFalse($result['eligible']);
        $this->assertSame('stripe_linked', $result['reason']);
    }

    protected function createSubscription(array $overrides = []): Subscription
    {
        return Subscription::query()->create(array_merge([
            'tenant_id' => 'tenant-cleanup-service-' . uniqid(),
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
            'gateway' => null,
            'gateway_customer_id' => null,
            'gateway_subscription_id' => null,
            'gateway_checkout_session_id' => null,
            'gateway_price_id' => null,
        ], $overrides));
    }

    protected function createPlan(): Plan
    {
        return Plan::query()->create([
            'name' => 'Cleanup Eligibility Plan ' . uniqid(),
            'slug' => 'cleanup-eligibility-plan-' . uniqid(),
            'description' => 'Cleanup eligibility test plan',
            'price' => 99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => 'price_' . uniqid(),
        ]);
    }
}
