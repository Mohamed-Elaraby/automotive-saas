<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentralBillingReportsStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_compute_core_billing_report_counts_and_estimated_mrr(): void
    {
        $starter = $this->createPlan('Starter', 'starter', 199, 'monthly');
        $growth = $this->createPlan('Growth', 'growth', 399, 'monthly');
        $trialPlan = $this->createPlan('Trial', 'trial', 0, 'trial');

        $this->createSubscription([
            'tenant_id' => 'tenant-active-1',
            'plan_id' => $starter->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_price_id' => 'price_starter',
        ]);

        $this->createSubscription([
            'tenant_id' => 'tenant-active-2',
            'plan_id' => $growth->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_price_id' => 'price_growth',
        ]);

        $this->createSubscription([
            'tenant_id' => 'tenant-trialing',
            'plan_id' => $trialPlan->id,
            'status' => 'trialing',
            'gateway' => null,
            'gateway_price_id' => null,
        ]);

        $this->createSubscription([
            'tenant_id' => 'tenant-past-due',
            'plan_id' => $starter->id,
            'status' => 'past_due',
            'gateway' => 'stripe',
            'gateway_price_id' => 'price_starter',
        ]);

        $this->createSubscription([
            'tenant_id' => 'tenant-suspended',
            'plan_id' => $starter->id,
            'status' => 'suspended',
            'gateway' => 'stripe',
            'gateway_price_id' => 'price_starter',
        ]);

        $this->createSubscription([
            'tenant_id' => 'tenant-canceled',
            'plan_id' => $growth->id,
            'status' => 'canceled',
            'gateway' => 'stripe',
            'gateway_price_id' => 'price_growth',
        ]);

        $this->createSubscription([
            'tenant_id' => 'tenant-expired',
            'plan_id' => $growth->id,
            'status' => 'expired',
            'gateway' => 'stripe',
            'gateway_price_id' => 'price_growth',
        ]);

        $totalSubscriptions = Subscription::query()->count();
        $activePaid = Subscription::query()->where('status', 'active')->count();
        $trialing = Subscription::query()->where('status', 'trialing')->count();
        $pastDue = Subscription::query()->where('status', 'past_due')->count();
        $suspended = Subscription::query()->where('status', 'suspended')->count();
        $canceled = Subscription::query()->where('status', 'canceled')->count();
        $expired = Subscription::query()->where('status', 'expired')->count();

        $estimatedMrr = Subscription::query()
            ->where('status', 'active')
            ->with('plan')
            ->get()
            ->sum(function (Subscription $subscription) {
                $plan = $subscription->plan;

                if (! $plan) {
                    return 0;
                }

                return (string) ($plan->billing_period ?? '') === 'monthly'
                    ? (float) $plan->price
                    : 0;
            });

        $this->assertSame(7, $totalSubscriptions);
        $this->assertSame(2, $activePaid);
        $this->assertSame(1, $trialing);
        $this->assertSame(1, $pastDue);
        $this->assertSame(1, $suspended);
        $this->assertSame(1, $canceled);
        $this->assertSame(1, $expired);
        $this->assertEquals(598.0, $estimatedMrr);
    }

    public function test_it_can_group_active_plan_distribution_for_monthly_active_subscriptions(): void
    {
        $starter = $this->createPlan('Starter', 'starter', 199, 'monthly');
        $growth = $this->createPlan('Growth', 'growth', 399, 'monthly');

        $this->createSubscription([
            'tenant_id' => 'tenant-distribution-1',
            'plan_id' => $starter->id,
            'status' => 'active',
            'gateway' => 'stripe',
        ]);

        $this->createSubscription([
            'tenant_id' => 'tenant-distribution-2',
            'plan_id' => $starter->id,
            'status' => 'active',
            'gateway' => 'stripe',
        ]);

        $this->createSubscription([
            'tenant_id' => 'tenant-distribution-3',
            'plan_id' => $growth->id,
            'status' => 'active',
            'gateway' => 'stripe',
        ]);

        $distribution = Subscription::query()
            ->where('status', 'active')
            ->with('plan')
            ->get()
            ->groupBy('plan_id')
            ->map(function ($subscriptions) {
                /** @var \App\Models\Subscription $first */
                $first = $subscriptions->first();
                $plan = $first->plan;

                return [
                    'plan_name' => $plan?->name,
                    'billing_period' => $plan?->billing_period,
                    'price' => (float) ($plan?->price ?? 0),
                    'active_subscriptions' => $subscriptions->count(),
                    'active_tenants' => $subscriptions->pluck('tenant_id')->unique()->count(),
                    'estimated_monthly_revenue' => (float) ($plan?->price ?? 0) * $subscriptions->count(),
                ];
            })
            ->values()
            ->sortBy('plan_name')
            ->values()
            ->all();

        $this->assertCount(2, $distribution);

        $this->assertSame('Growth', $distribution[0]['plan_name']);
        $this->assertSame('monthly', $distribution[0]['billing_period']);
        $this->assertEquals(399.0, $distribution[0]['price']);
        $this->assertSame(1, $distribution[0]['active_subscriptions']);
        $this->assertSame(1, $distribution[0]['active_tenants']);
        $this->assertEquals(399.0, $distribution[0]['estimated_monthly_revenue']);

        $this->assertSame('Starter', $distribution[1]['plan_name']);
        $this->assertSame('monthly', $distribution[1]['billing_period']);
        $this->assertEquals(199.0, $distribution[1]['price']);
        $this->assertSame(2, $distribution[1]['active_subscriptions']);
        $this->assertSame(2, $distribution[1]['active_tenants']);
        $this->assertEquals(398.0, $distribution[1]['estimated_monthly_revenue']);
    }

    protected function createSubscription(array $overrides = []): Subscription
    {
        return Subscription::query()->create(array_merge([
            'tenant_id' => 'tenant-report-test-' . uniqid(),
            'plan_id' => $this->createPlan('Default', 'default-' . uniqid(), 199, 'monthly')->id,
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

    protected function createPlan(string $name, string $slug, int|float $price, string $billingPeriod): Plan
    {
        return Plan::query()->create([
            'name' => $name,
            'slug' => $slug . '-' . uniqid(),
            'description' => $name . ' description',
            'price' => $price,
            'currency' => 'USD',
            'billing_period' => $billingPeriod,
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => 'price_' . uniqid(),
        ]);
    }
}
