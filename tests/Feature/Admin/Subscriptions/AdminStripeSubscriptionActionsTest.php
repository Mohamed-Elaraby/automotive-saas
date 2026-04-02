<?php

namespace Tests\Feature\Admin\Subscriptions;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Admin;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\StripeSubscriptionManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AdminStripeSubscriptionActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_admin_can_schedule_stripe_cancellation_from_subscription_show(): void
    {
        $admin = $this->createAdmin();
        $subscription = $this->createStripeSubscription();

        $service = Mockery::mock(StripeSubscriptionManagementService::class);
        $service->shouldReceive('cancelAtPeriodEnd')
            ->once()
            ->with(Mockery::on(fn ($sub) => (int) $sub->id === (int) $subscription->id))
            ->andReturn([
                'success' => true,
                'message' => 'Subscription cancellation has been scheduled for the end of the current billing period.',
            ]);

        $this->app->instance(StripeSubscriptionManagementService::class, $service);

        $response = $this
            ->actingAs($admin, 'admin')
            ->post(route('admin.subscriptions.cancel-on-stripe', $subscription->id));

        $response
            ->assertRedirect(route('admin.subscriptions.show', $subscription->id))
            ->assertSessionHas('success', 'Subscription cancellation has been scheduled for the end of the current billing period.');
    }

    public function test_admin_can_resume_stripe_subscription_from_subscription_show(): void
    {
        $admin = $this->createAdmin();
        $subscription = $this->createStripeSubscription();

        $service = Mockery::mock(StripeSubscriptionManagementService::class);
        $service->shouldReceive('resume')
            ->once()
            ->with(Mockery::on(fn ($sub) => (int) $sub->id === (int) $subscription->id))
            ->andReturn([
                'success' => true,
                'message' => 'Subscription cancellation was removed and the subscription remains active.',
            ]);

        $this->app->instance(StripeSubscriptionManagementService::class, $service);

        $response = $this
            ->actingAs($admin, 'admin')
            ->post(route('admin.subscriptions.resume-on-stripe', $subscription->id));

        $response
            ->assertRedirect(route('admin.subscriptions.show', $subscription->id))
            ->assertSessionHas('success', 'Subscription cancellation was removed and the subscription remains active.');
    }

    protected function createAdmin(): Admin
    {
        return Admin::query()->create([
            'name' => 'Central Admin',
            'email' => 'admin-stripe-actions-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);
    }

    protected function createStripeSubscription(): Subscription
    {
        $plan = Plan::query()->create([
            'name' => 'Growth',
            'slug' => 'growth-' . uniqid(),
            'description' => 'Growth plan',
            'price' => 399,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => 'price_' . uniqid(),
        ]);

        return Subscription::query()->create([
            'tenant_id' => 'tenant-stripe-admin-' . uniqid(),
            'plan_id' => $plan->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_' . uniqid(),
            'gateway_subscription_id' => 'sub_' . uniqid(),
            'gateway_checkout_session_id' => 'cs_' . uniqid(),
            'gateway_price_id' => $plan->stripe_price_id,
            'ends_at' => now()->addMonth(),
        ]);
    }
}
