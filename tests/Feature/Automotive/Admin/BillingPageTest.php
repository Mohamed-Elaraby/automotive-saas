<?php

namespace Tests\Feature\Automotive\Admin;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\TenantUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BillingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_related_models_can_be_prepared_for_ui_state_rendering(): void
    {
        Carbon::setTestNow('2026-03-26 12:00:00');

        $starter = $this->createPlan('Starter', 'starter', 199, 'price_starter');
        $growth = $this->createPlan('Growth', 'growth', 399, 'price_growth');

        $subscription = $this->createSubscription([
            'tenant_id' => 'billing-page-test',
            'plan_id' => $starter->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_test_active',
            'gateway_subscription_id' => 'sub_test_active',
            'gateway_price_id' => 'price_starter',
        ]);

        $this->assertSame('active', $subscription->status);
        $this->assertSame($starter->id, $subscription->plan_id);
        $this->assertSame('price_starter', $subscription->gateway_price_id);
        $this->assertSame('Growth', $growth->name);
    }

    public function test_billing_state_can_represent_same_plan_selection_safely(): void
    {
        Carbon::setTestNow('2026-03-26 12:00:00');

        $starter = $this->createPlan('Starter', 'starter', 199, 'price_starter');

        $subscription = $this->createSubscription([
            'tenant_id' => 'billing-page-test',
            'plan_id' => $starter->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_test_same_plan',
            'gateway_subscription_id' => 'sub_test_same_plan',
            'gateway_price_id' => 'price_starter',
        ]);

        $this->assertSame($starter->id, $subscription->plan_id);
        $this->assertSame('active', $subscription->status);
    }

    public function test_billing_state_can_represent_past_due_or_grace_period_subscription(): void
    {
        Carbon::setTestNow('2026-03-26 12:00:00');

        $starter = $this->createPlan('Starter', 'starter', 199, 'price_starter');

        $subscription = $this->createSubscription([
            'tenant_id' => 'billing-page-test',
            'plan_id' => $starter->id,
            'status' => 'past_due',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_test_past_due',
            'gateway_subscription_id' => 'sub_test_past_due',
            'gateway_price_id' => 'price_starter',
            'last_payment_failed_at' => now()->subHour(),
            'past_due_started_at' => now()->subHour(),
            'grace_ends_at' => now()->addDays(2),
        ]);

        $this->assertSame('past_due', $subscription->status);
        $this->assertNotNull($subscription->grace_ends_at);
    }

    public function test_billing_state_can_represent_suspended_subscription(): void
    {
        Carbon::setTestNow('2026-03-26 12:00:00');

        $starter = $this->createPlan('Starter', 'starter', 199, 'price_starter');

        $subscription = $this->createSubscription([
            'tenant_id' => 'billing-page-test',
            'plan_id' => $starter->id,
            'status' => 'suspended',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_test_suspended',
            'gateway_subscription_id' => 'sub_test_suspended',
            'gateway_price_id' => 'price_starter',
            'suspended_at' => now()->subHour(),
        ]);

        $this->assertSame('suspended', $subscription->status);
        $this->assertNotNull($subscription->suspended_at);
    }

    protected function createSubscription(array $overrides = []): Subscription
    {
        return Subscription::query()->create(array_merge([
            'tenant_id' => 'billing-page-test',
            'plan_id' => $this->createPlan('Default', 'default-' . uniqid(), 199, 'price_default_' . uniqid())->id,
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
            'gateway_customer_id' => 'cus_test_billing_page',
            'gateway_subscription_id' => 'sub_test_billing_page_' . uniqid(),
            'gateway_checkout_session_id' => 'cs_test_billing_page_' . uniqid(),
            'gateway_price_id' => 'price_default',
        ], $overrides));
    }

    protected function createPlan(string $name, string $slug, int|float $price, ?string $stripePriceId): Plan
    {
        return Plan::query()->create([
            'name' => $name,
            'slug' => $slug . '-' . uniqid(),
            'description' => $name . ' description',
            'price' => $price,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => $stripePriceId,
        ]);
    }
}
