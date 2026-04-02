<?php

namespace Tests\Feature\Admin\Subscriptions;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Admin;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSubscriptionsIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_index_displays_timeline_and_contextual_quick_actions(): void
    {
        $admin = $this->createAdmin();
        $localPlan = $this->createPlan('Local Monthly', 'local-monthly-' . uniqid(), 'monthly');
        $stripePlan = $this->createPlan('Stripe Monthly', 'stripe-monthly-' . uniqid(), 'monthly');

        $localSubscription = Subscription::query()->create([
            'tenant_id' => 'tenant-local-index-' . uniqid(),
            'plan_id' => $localPlan->id,
            'status' => 'past_due',
            'past_due_started_at' => now()->subDay(),
            'grace_ends_at' => now()->addDays(2),
            'gateway' => null,
        ]);

        $stripeSubscription = Subscription::query()->create([
            'tenant_id' => 'tenant-stripe-index-' . uniqid(),
            'plan_id' => $stripePlan->id,
            'status' => 'active',
            'ends_at' => now()->addMonth(),
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_index_test',
            'gateway_subscription_id' => 'sub_index_test',
            'gateway_price_id' => 'price_index_test',
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.subscriptions.index'));

        $response->assertOk();
        $response->assertSee('Lifecycle Timeline', false);
        $response->assertSee('Local-managed', false);
        $response->assertSee('Stripe-linked', false);
        $response->assertSee('Cancel', false);
        $response->assertSee('Sync Stripe', false);
        $response->assertSee('Backfill', false);
        $response->assertSee('Past due:', false);
        $response->assertSee('Grace ends:', false);
        $response->assertSee((string) $localSubscription->tenant_id, false);
        $response->assertSee((string) $stripeSubscription->tenant_id, false);
    }

    public function test_manual_quick_action_from_index_redirects_back_to_index(): void
    {
        $admin = $this->createAdmin();
        $plan = $this->createPlan('Recover Monthly', 'recover-monthly-' . uniqid(), 'monthly');

        $subscription = Subscription::query()->create([
            'tenant_id' => 'tenant-index-action-' . uniqid(),
            'plan_id' => $plan->id,
            'status' => 'suspended',
            'suspended_at' => now()->subDay(),
            'gateway' => null,
        ]);

        $response = $this
            ->actingAs($admin, 'admin')
            ->post(route('admin.subscriptions.manual-action', $subscription->id), [
                'action' => 'resume',
                'redirect_to' => 'index',
            ]);

        $response
            ->assertRedirect(route('admin.subscriptions.index'))
            ->assertSessionHas('success');

        $this->assertSame('active', $subscription->fresh()->status);
    }

    protected function createAdmin(): Admin
    {
        return Admin::query()->create([
            'name' => 'Central Admin',
            'email' => 'admin-subscriptions-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);
    }

    protected function createPlan(string $name, string $slug, string $billingPeriod): Plan
    {
        return Plan::query()->create([
            'name' => $name,
            'slug' => $slug,
            'description' => $name . ' description',
            'price' => 79,
            'currency' => 'USD',
            'billing_period' => $billingPeriod,
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => 'price_' . uniqid(),
        ]);
    }
}
