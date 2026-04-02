<?php

namespace Tests\Feature\Admin\Plans;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Admin;
use App\Models\Plan;
use App\Services\Billing\StripePlanCatalogSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
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

    public function test_admin_store_writes_plan_features_to_separate_table(): void
    {
        $admin = $this->createAdmin();
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
                'features_text' => "Inventory management\r\nAdvanced reports\r\nInventory management\r\nBarcode support",
            ]);

        $plan = Plan::query()->where('slug', 'growth')->firstOrFail();

        $response
            ->assertRedirect(route('admin.plans.index'))
            ->assertSessionHas('success', 'Plan created successfully. Stripe sync was skipped because Stripe is not configured.');

        $this->assertFalse(Schema::hasColumn('plans', 'features'));
        $this->assertSame(
            ['Inventory management', 'Advanced reports', 'Barcode support'],
            $plan->planFeatures()->orderBy('sort_order')->pluck('title')->all()
        );
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
