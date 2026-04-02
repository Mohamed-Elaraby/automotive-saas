<?php

namespace Tests\Feature\Admin\Subscriptions;

use App\Models\Admin;
use App\Models\Plan;
use App\Models\Subscription;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminSubscriptionAdvancedControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_force_lifecycle_can_activate_local_subscription(): void
    {
        $admin = $this->createAdmin();
        $plan = $this->createPlan('Starter Monthly', 'starter-monthly', 'monthly');

        $subscription = Subscription::query()->create([
            'tenant_id' => 'tenant-subscription-force-' . uniqid(),
            'plan_id' => $plan->id,
            'status' => 'past_due',
            'grace_ends_at' => now()->addDay(),
            'last_payment_failed_at' => now()->subDay(),
            'past_due_started_at' => now()->subDays(2),
            'payment_failures_count' => 3,
            'gateway' => null,
        ]);

        $response = $this
            ->actingAs($admin, 'admin')
            ->post(route('admin.subscriptions.manual-action', $subscription->id), [
                'action' => 'force_lifecycle',
                'target_status' => 'active',
            ]);

        $response
            ->assertRedirect(route('admin.subscriptions.show', $subscription->id))
            ->assertSessionHas('success');

        $fresh = $subscription->fresh();

        $this->assertSame('active', $fresh->status);
        $this->assertNull($fresh->grace_ends_at);
        $this->assertNull($fresh->last_payment_failed_at);
        $this->assertNull($fresh->past_due_started_at);
        $this->assertSame(0, $fresh->payment_failures_count);
        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'subscription.manual_action',
            'subject_id' => (string) $subscription->id,
        ]);
    }

    public function test_manual_action_rejects_stripe_linked_subscription(): void
    {
        $admin = $this->createAdmin();
        $plan = $this->createPlan('Stripe Monthly', 'stripe-monthly', 'monthly');

        $subscription = Subscription::query()->create([
            'tenant_id' => 'tenant-subscription-stripe-' . uniqid(),
            'plan_id' => $plan->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_manual_block',
            'gateway_subscription_id' => 'sub_manual_block',
        ]);

        $response = $this
            ->actingAs($admin, 'admin')
            ->from(route('admin.subscriptions.show', $subscription->id))
            ->post(route('admin.subscriptions.manual-action', $subscription->id), [
                'action' => 'cancel',
            ]);

        $response
            ->assertRedirect(route('admin.subscriptions.show', $subscription->id))
            ->assertSessionHas('error');

        $this->assertSame('active', $subscription->fresh()->status);
    }

    public function test_manual_action_allows_customer_only_subscription_without_stripe_subscription_link(): void
    {
        $admin = $this->createAdmin();
        $plan = $this->createPlan('Customer Only Monthly', 'customer-only-monthly', 'monthly');

        $subscription = Subscription::query()->create([
            'tenant_id' => 'tenant-subscription-customer-only-' . uniqid(),
            'plan_id' => $plan->id,
            'status' => 'active',
            'gateway' => null,
            'gateway_customer_id' => 'cus_customer_only',
            'gateway_subscription_id' => null,
        ]);

        $response = $this
            ->actingAs($admin, 'admin')
            ->post(route('admin.subscriptions.manual-action', $subscription->id), [
                'action' => 'cancel',
            ]);

        $response
            ->assertRedirect(route('admin.subscriptions.show', $subscription->id))
            ->assertSessionHas('success');

        $this->assertSame('canceled', $subscription->fresh()->status);
    }

    public function test_manual_action_allows_gateway_flag_without_stripe_subscription_id(): void
    {
        $admin = $this->createAdmin();
        $plan = $this->createPlan('Stripe Pending Monthly', 'stripe-pending-monthly', 'monthly');

        $subscription = Subscription::query()->create([
            'tenant_id' => 'tenant-subscription-gateway-only-' . uniqid(),
            'plan_id' => $plan->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_pending_only',
            'gateway_checkout_session_id' => 'cs_pending_only',
            'gateway_subscription_id' => null,
        ]);

        $response = $this
            ->actingAs($admin, 'admin')
            ->post(route('admin.subscriptions.manual-action', $subscription->id), [
                'action' => 'cancel',
            ]);

        $response
            ->assertRedirect(route('admin.subscriptions.show', $subscription->id))
            ->assertSessionHas('success');

        $this->assertSame('canceled', $subscription->fresh()->status);
    }

    public function test_manual_renew_sets_future_ends_at_for_local_subscription(): void
    {
        $admin = $this->createAdmin();
        $plan = $this->createPlan('Renew Monthly', 'renew-monthly', 'monthly');

        $subscription = Subscription::query()->create([
            'tenant_id' => 'tenant-subscription-renew-' . uniqid(),
            'plan_id' => $plan->id,
            'status' => 'canceled',
            'cancelled_at' => now()->subDay(),
            'ends_at' => now()->subHour(),
            'gateway' => null,
        ]);

        $response = $this
            ->actingAs($admin, 'admin')
            ->post(route('admin.subscriptions.manual-action', $subscription->id), [
                'action' => 'renew',
            ]);

        $response
            ->assertRedirect(route('admin.subscriptions.show', $subscription->id))
            ->assertSessionHas('success');

        $fresh = $subscription->fresh();

        $this->assertSame('active', $fresh->status);
        $this->assertNull($fresh->cancelled_at);
        $this->assertNotNull($fresh->ends_at);
        $this->assertTrue(Carbon::parse($fresh->ends_at)->greaterThan(now()->addDays(27)));
    }

    public function test_resume_restores_trial_subscription_to_trialing_instead_of_active(): void
    {
        $admin = $this->createAdmin();
        $plan = $this->createPlan('Trial Workspace', 'trial-workspace-' . uniqid(), 'trial');

        $subscription = Subscription::query()->create([
            'tenant_id' => 'tenant-subscription-trial-resume-' . uniqid(),
            'plan_id' => $plan->id,
            'status' => 'suspended',
            'suspended_at' => now()->subDay(),
            'trial_ends_at' => now()->addDays(5),
            'gateway' => 'stripe',
            'gateway_checkout_session_id' => 'cs_resume_trial_only',
            'gateway_subscription_id' => null,
        ]);

        $response = $this
            ->actingAs($admin, 'admin')
            ->post(route('admin.subscriptions.manual-action', $subscription->id), [
                'action' => 'resume',
            ]);

        $response
            ->assertRedirect(route('admin.subscriptions.show', $subscription->id))
            ->assertSessionHas('success');

        $fresh = $subscription->fresh();

        $this->assertSame('trialing', $fresh->status);
        $this->assertNull($fresh->suspended_at);
    }

    public function test_timestamp_update_changes_local_lifecycle_dates(): void
    {
        $admin = $this->createAdmin();
        $plan = $this->createPlan('Dates Monthly', 'dates-monthly', 'monthly');

        $subscription = Subscription::query()->create([
            'tenant_id' => 'tenant-subscription-dates-' . uniqid(),
            'plan_id' => $plan->id,
            'status' => 'trialing',
            'gateway' => null,
        ]);

        $trialEndsAt = now()->addDays(10)->format('Y-m-d H:i:s');
        $graceEndsAt = now()->addDays(13)->format('Y-m-d H:i:s');
        $endsAt = now()->addMonth()->format('Y-m-d H:i:s');

        $response = $this
            ->actingAs($admin, 'admin')
            ->post(route('admin.subscriptions.timestamps', $subscription->id), [
                'trial_ends_at' => $trialEndsAt,
                'grace_ends_at' => $graceEndsAt,
                'ends_at' => $endsAt,
            ]);

        $response
            ->assertRedirect(route('admin.subscriptions.show', $subscription->id))
            ->assertSessionHas('success');

        $fresh = $subscription->fresh();

        $this->assertSame(Carbon::parse($trialEndsAt)->format('Y-m-d H:i'), $fresh->trial_ends_at?->format('Y-m-d H:i'));
        $this->assertSame(Carbon::parse($graceEndsAt)->format('Y-m-d H:i'), $fresh->grace_ends_at?->format('Y-m-d H:i'));
        $this->assertSame(Carbon::parse($endsAt)->format('Y-m-d H:i'), $fresh->ends_at?->format('Y-m-d H:i'));
        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'subscription.timestamps.updated',
            'subject_id' => (string) $subscription->id,
        ]);
    }

    protected function createAdmin(): Admin
    {
        return Admin::query()->create([
            'name' => 'Central Admin',
            'email' => 'admin-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);
    }

    protected function createPlan(string $name, string $slug, string $billingPeriod): Plan
    {
        return Plan::query()->create([
            'name' => $name,
            'slug' => $slug,
            'description' => $name . ' description',
            'price' => 99,
            'currency' => 'USD',
            'billing_period' => $billingPeriod,
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => 'price_' . uniqid(),
        ]);
    }
}
