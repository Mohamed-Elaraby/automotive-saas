<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\StripeSubscriptionPlanChangeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeSubscriptionPlanChangeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_plan_change_when_subscription_is_not_linked_to_stripe(): void
    {
        $currentPlan = $this->createPlan('Starter', 'starter', 'price_starter');
        $targetPlan = $this->createPlan('Growth', 'growth', 'price_growth');

        $subscription = $this->createSubscription([
            'plan_id' => $currentPlan->id,
            'gateway' => 'stripe',
            'gateway_subscription_id' => null,
            'gateway_price_id' => 'price_starter',
            'status' => 'active',
        ]);

        $service = app(StripeSubscriptionPlanChangeService::class);

        $result = $service->changePlan($subscription, $targetPlan);

        $this->assertFalse($result['ok'] ?? true);
        $this->assertStringContainsString(
            'no stripe subscription id is linked',
            strtolower((string) ($result['message'] ?? ''))
        );
    }

    public function test_it_rejects_plan_change_when_target_plan_has_no_stripe_price_id(): void
    {
        $currentPlan = $this->createPlan('Starter', 'starter', 'price_starter');
        $targetPlan = $this->createPlan('Growth', 'growth', null);

        $subscription = $this->createSubscription([
            'plan_id' => $currentPlan->id,
            'gateway' => 'stripe',
            'gateway_subscription_id' => 'sub_test_plan_change_001',
            'gateway_price_id' => 'price_starter',
            'status' => 'active',
        ]);

        $service = app(StripeSubscriptionPlanChangeService::class);

        $result = $service->changePlan($subscription, $targetPlan);

        $this->assertFalse($result['ok'] ?? true);
        $this->assertStringContainsString(
            'stripe price',
            strtolower((string) ($result['message'] ?? ''))
        );
    }

    public function test_it_rejects_plan_change_when_target_plan_is_same_current_price(): void
    {
        $currentPlan = $this->createPlan('Starter', 'starter', 'price_starter');

        $subscription = $this->createSubscription([
            'plan_id' => $currentPlan->id,
            'gateway' => 'stripe',
            'gateway_subscription_id' => 'sub_test_plan_change_002',
            'gateway_price_id' => 'price_starter',
            'status' => 'active',
        ]);

        $service = app(StripeSubscriptionPlanChangeService::class);

        $result = $service->changePlan($subscription, $currentPlan);

        $this->assertFalse($result['ok'] ?? true);
        $this->assertStringContainsString(
            'already',
            strtolower((string) ($result['message'] ?? ''))
        );
    }

    public function test_service_class_resolves_successfully_from_container(): void
    {
        $service = app(StripeSubscriptionPlanChangeService::class);

        $this->assertInstanceOf(StripeSubscriptionPlanChangeService::class, $service);
    }

    protected function createSubscription(array $overrides = []): Subscription
    {
        return Subscription::query()->create(array_merge([
            'tenant_id' => 'tenant-plan-change-test',
            'plan_id' => $this->createPlan('Default', 'default-' . uniqid(), 'price_default_' . uniqid())->id,
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
            'gateway_customer_id' => 'cus_test_plan_change',
            'gateway_subscription_id' => 'sub_test_' . uniqid(),
            'gateway_checkout_session_id' => 'cs_test_' . uniqid(),
            'gateway_price_id' => 'price_default',
        ], $overrides));
    }

    protected function createPlan(string $name, string $slug, ?string $stripePriceId): Plan
    {
        return Plan::query()->create([
            'name' => $name,
            'slug' => $slug . '-' . uniqid(),
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
