<?php

namespace Tests\Feature\Automotive\Portal;

use App\Models\CustomerOnboardingProfile;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerPortalBillingOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_workspace_without_live_stripe_subscription_can_still_start_paid_checkout(): void
    {
        $user = User::query()->create([
            'name' => 'Portal User',
            'email' => 'portal-user-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        CustomerOnboardingProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Portal Co',
            'subdomain' => 'portal-' . uniqid(),
            'base_host' => 'example.test',
        ]);

        $trialPlan = $this->createPlan('Trial Plan', 'trial-plan-' . uniqid(), 'trial', 0);
        $paidPlan = $this->createPlan('Pro Plan', 'pro-plan-' . uniqid(), 'monthly', 149);

        $tenant = Tenant::query()->create([
            'id' => 'tenant-portal-' . uniqid(),
            'data' => [
                'company_name' => 'Portal Co',
                'owner_email' => $user->email,
            ],
        ]);

        DB::table('tenant_users')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $trialPlan->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_checkout_session_id' => 'cs_portal_pending_only',
            'gateway_subscription_id' => null,
            'gateway_price_id' => $paidPlan->stripe_price_id,
            'ends_at' => now()->addDays(5),
        ]);

        $response = $this->actingAs($user, 'web')->get(route('automotive.portal'));

        $response->assertOk();
        $response->assertSee('Select &amp; Continue', false);
        $response->assertDontSee('Billing Managed In System', false);
    }

    public function test_portal_paid_plan_cards_show_real_plan_limits(): void
    {
        $user = User::query()->create([
            'name' => 'Portal Limits User',
            'email' => 'portal-limits-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        CustomerOnboardingProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Portal Limits Co',
            'subdomain' => 'portal-limits-' . uniqid(),
            'base_host' => 'example.test',
        ]);

        $plan = Plan::query()->create([
            'name' => 'Growth',
            'slug' => 'growth-' . uniqid(),
            'description' => 'Real plan description',
            'price' => 399,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => 'price_' . uniqid(),
            'max_users' => 12,
            'max_branches' => 4,
            'max_products' => 250,
            'max_storage_mb' => 2048,
        ]);
        $plan->planFeatures()->createMany([
            ['title' => 'Barcode support', 'sort_order' => 0],
            ['title' => 'Inventory reports', 'sort_order' => 1],
        ]);

        $response = $this->actingAs($user, 'web')->get(route('automotive.portal'));

        $response->assertOk();
        $response->assertSee('What you get:', false);
        $response->assertDontSee('Plan Limits', false);
        $response->assertSee('Users', false);
        $response->assertSee('12', false);
        $response->assertSee('Branches', false);
        $response->assertSee('4', false);
        $response->assertSee('Products', false);
        $response->assertSee('250', false);
        $response->assertSee('Storage', false);
        $response->assertSee('2048 MB', false);
        $response->assertSee('Barcode support', false);
        $response->assertSee('Inventory reports', false);
        $response->assertSee(strtoupper((string) $plan->slug), false);
    }

    public function test_terminal_cancelled_stripe_subscription_does_not_block_new_paid_checkout_in_portal(): void
    {
        $user = User::query()->create([
            'name' => 'Portal User',
            'email' => 'portal-user-terminal-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        CustomerOnboardingProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Portal Co',
            'subdomain' => 'portal-terminal-' . uniqid(),
            'base_host' => 'example.test',
        ]);

        $oldPlan = $this->createPlan('Growth', 'growth-plan-' . uniqid(), 'monthly', 399);
        $newPlan = $this->createPlan('Scale', 'scale-plan-' . uniqid(), 'monthly', 599);

        $tenant = Tenant::query()->create([
            'id' => 'tenant-portal-terminal-' . uniqid(),
            'data' => [
                'company_name' => 'Portal Co',
                'owner_email' => $user->email,
            ],
        ]);

        DB::table('tenant_users')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $oldPlan->id,
            'status' => 'canceled',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_terminal_only',
            'gateway_subscription_id' => 'sub_terminal_only',
            'gateway_checkout_session_id' => 'cs_terminal_only',
            'gateway_price_id' => $oldPlan->stripe_price_id,
            'cancelled_at' => now()->subDay(),
            'ends_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($user, 'web')->get(route('automotive.portal'));

        $response->assertOk();
        $response->assertDontSee('This account already has a live Stripe subscription.', false);
        $response->assertDontSee('Billing Managed In System', false);
        $response->assertSee('Select &amp; Continue', false);
        $response->assertSee((string) $newPlan->name, false);
    }

    protected function createPlan(string $name, string $slug, string $billingPeriod, int $price): Plan
    {
        return Plan::query()->create([
            'name' => $name,
            'slug' => $slug,
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
