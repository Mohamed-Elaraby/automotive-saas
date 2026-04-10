<?php

namespace Tests\Feature\Admin\Plans;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Admin;
use App\Models\BillingFeature;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Services\Billing\StripePlanCatalogSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AdminPlanFeatureStorageTest extends TestCase
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

    public function test_admin_store_assigns_selected_catalog_features_to_plan(): void
    {
        $admin = $this->createAdmin();
        $product = Product::query()->create([
            'code' => 'inventory_suite',
            'name' => 'Inventory Suite',
            'slug' => 'inventory-suite',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $inventory = BillingFeature::query()->create([
            'name' => 'Inventory',
            'slug' => 'inventory',
            'description' => 'Inventory management',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $reports = BillingFeature::query()->create([
            'name' => 'Reports',
            'slug' => 'reports',
            'description' => 'Analytics and reports',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $service = Mockery::mock(StripePlanCatalogSyncService::class);
        $service->shouldReceive('syncPlan')
            ->once()
            ->andReturn([
                'ok' => true,
                'skipped' => true,
            ]);
        $this->app->instance(StripePlanCatalogSyncService::class, $service);

        $response = $this
            ->actingAs($admin, 'admin')
            ->post(route('admin.plans.store'), [
                'product_id' => $product->id,
                'name' => 'Growth',
                'slug' => 'growth',
                'price' => 399,
                'currency' => 'usd',
                'billing_period' => 'monthly',
                'stripe_price_id' => 'price_test_growth',
                'is_active' => 1,
                'sort_order' => 1,
                'max_users' => 50,
                'max_branches' => 10,
                'max_products' => 50000,
                'max_storage_mb' => 20480,
                'description' => 'Growth plan',
                'feature_ids' => [$inventory->id, $reports->id, $inventory->id],
            ]);

        $plan = Plan::query()->where('slug', 'growth')->firstOrFail();

        $response
            ->assertRedirect(route('admin.plans.index'))
            ->assertSessionHas('success', 'Plan created successfully. Stripe sync was skipped because Stripe is not configured.');

        $this->assertSame(
            ['Inventory', 'Reports'],
            $plan->billingFeatures()->orderBy('billing_feature_plan.sort_order')->pluck('billing_features.name')->all()
        );
    }

    public function test_plan_form_shows_limits_semantics_guidance(): void
    {
        $admin = $this->createAdmin();

        $response = $this
            ->actingAs($admin, 'admin')
            ->get(route('admin.plans.create'));

        $response->assertOk();
        $response->assertSee('id="admin-plan-form"', false);
        $response->assertSee('Product');
        $response->assertSee('Select a product');
        $response->assertSee('Limits Semantics');
        $response->assertSee('Only filled limits appear in the portal preview and paid plan cards.');
        $response->assertSee('Empty does not mean zero.');
        $response->assertSee('Use empty fields when a cap is not part of the sales message');
    }

    public function test_plans_index_shows_clean_limits_summary(): void
    {
        $admin = $this->createAdmin();

        Plan::query()->create([
            'name' => 'Growth',
            'slug' => 'growth',
            'price' => 399,
            'currency' => 'AED',
            'billing_period' => 'monthly',
            'is_active' => true,
            'sort_order' => 1,
            'max_users' => 50,
            'max_branches' => 10,
            'max_products' => 50000,
            'max_storage_mb' => 20480,
        ]);

        Plan::query()->create([
            'name' => 'Starter',
            'slug' => 'starter',
            'price' => 99,
            'currency' => 'AED',
            'billing_period' => 'monthly',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $response = $this
            ->actingAs($admin, 'admin')
            ->get(route('admin.plans.index'));

        $response->assertOk();
        $response->assertSee('50 users');
        $response->assertSee('10 branches');
        $response->assertSee('50000 products');
        $response->assertSee('20480 MB storage');
        $response->assertSee('No advertised limits');
        $response->assertDontSee('Users: -');
        $response->assertDontSee('Storage: - MB');
    }

    public function test_plans_index_can_filter_by_search_period_status_and_stripe_linkage(): void
    {
        $admin = $this->createAdmin();
        $matchingProduct = Product::query()->create([
            'code' => 'accounting_suite',
            'name' => 'Accounting Suite',
            'slug' => 'accounting-suite',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $otherProduct = Product::query()->create([
            'code' => 'spare_parts',
            'name' => 'Spare Parts',
            'slug' => 'spare-parts',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        Plan::query()->create([
            'product_id' => $matchingProduct->id,
            'name' => 'Growth Monthly',
            'slug' => 'growth-monthly',
            'price' => 399,
            'currency' => 'AED',
            'billing_period' => 'monthly',
            'stripe_price_id' => 'price_growth_monthly',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Plan::query()->create([
            'product_id' => $matchingProduct->id,
            'name' => 'Growth Yearly',
            'slug' => 'growth-yearly',
            'price' => 3999,
            'currency' => 'AED',
            'billing_period' => 'yearly',
            'stripe_price_id' => 'price_growth_yearly',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        Plan::query()->create([
            'product_id' => $otherProduct->id,
            'name' => 'Starter Monthly',
            'slug' => 'starter-monthly',
            'price' => 99,
            'currency' => 'AED',
            'billing_period' => 'monthly',
            'is_active' => false,
            'sort_order' => 3,
        ]);

        $response = $this
            ->actingAs($admin, 'admin')
            ->get(route('admin.plans.index', [
                'q' => 'growth',
                'product_id' => $matchingProduct->id,
                'billing_period' => 'monthly',
                'status' => 'active',
                'stripe' => 'linked',
            ]));

        $response->assertOk();
        $response->assertSee('Accounting Suite');
        $response->assertSee('Growth Monthly');
        $response->assertDontSee('Growth Yearly');
        $response->assertDontSee('Starter Monthly');
    }

    public function test_admin_can_view_plan_usage_drilldown(): void
    {
        $admin = $this->createAdmin();

        $plan = Plan::query()->create([
            'name' => 'Growth Monthly',
            'slug' => 'growth-monthly',
            'price' => 399,
            'currency' => 'AED',
            'billing_period' => 'monthly',
            'stripe_price_id' => 'price_growth_monthly',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $feature = BillingFeature::query()->create([
            'name' => 'Inventory',
            'slug' => 'inventory',
            'description' => 'Inventory management',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $plan->billingFeatures()->attach($feature->id, ['sort_order' => 1]);

        $activeSubscription = Subscription::query()->create([
            'tenant_id' => 'tenant-growth-active',
            'plan_id' => $plan->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_subscription_id' => 'sub_growth_active',
        ]);

        $trialSubscription = Subscription::query()->create([
            'tenant_id' => 'tenant-growth-trial',
            'plan_id' => $plan->id,
            'status' => 'trialing',
            'gateway' => null,
        ]);

        $response = $this
            ->actingAs($admin, 'admin')
            ->get(route('admin.plans.show', $plan));

        $response->assertOk();
        $response->assertSee('Growth Monthly');
        $response->assertSee('Subscription Status Breakdown');
        $response->assertSee('Linked Subscriptions');
        $response->assertSee('Active');
        $response->assertSee('Trialing');
        $response->assertSee('tenant-growth-active');
        $response->assertSee('tenant-growth-trial');
        $response->assertSee(route('admin.subscriptions.show', $activeSubscription->id), false);
        $response->assertSee(route('admin.subscriptions.show', $trialSubscription->id), false);
    }

    protected function createAdmin(): Admin
    {
        return Admin::query()->create([
            'name' => 'Central Admin',
            'email' => 'admin-plan-features-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);
    }
}
