<?php

namespace Tests\Feature\Admin\Plans;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Admin;
use App\Models\BillingFeature;
use App\Models\Plan;
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
        $response->assertSee('Limits Semantics');
        $response->assertSee('Only filled limits appear in the portal preview and paid plan cards.');
        $response->assertSee('Empty does not mean zero.');
        $response->assertSee('Use empty fields when a cap is not part of the sales message');
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
