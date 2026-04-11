<?php

namespace Tests\Feature\Admin;

use App\Models\Plan;
use App\Models\Product;
use App\Services\Billing\StripePlanCatalogSyncService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PlanSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_seeder_creates_product_aware_plans_for_all_active_products(): void
    {
        Product::query()->updateOrCreate(
            ['code' => 'automotive_service'],
            [
                'name' => 'Automotive Service Management',
                'slug' => 'automotive-service-management',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        Product::query()->updateOrCreate(
            ['code' => 'parts_inventory'],
            [
                'name' => 'Spare Parts Inventory',
                'slug' => 'spare-parts-inventory',
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        Product::query()->updateOrCreate(
            ['code' => 'accounting'],
            [
                'name' => 'Accounting System',
                'slug' => 'accounting-system',
                'is_active' => true,
                'sort_order' => 3,
            ]
        );

        $sync = Mockery::mock(StripePlanCatalogSyncService::class);
        $sync->shouldReceive('syncPlan')
            ->times(9)
            ->andReturn([
                'ok' => true,
                'skipped' => true,
                'message' => 'Stripe sync skipped because Stripe secret key is not configured.',
            ]);
        $this->app->instance(StripePlanCatalogSyncService::class, $sync);

        $this->seed(PlanSeeder::class);

        $this->assertDatabaseHas('plans', [
            'slug' => 'automotive-service-trial',
            'billing_period' => 'trial',
        ]);
        $this->assertDatabaseHas('plans', [
            'slug' => 'parts-inventory-trial',
            'billing_period' => 'trial',
        ]);
        $this->assertDatabaseHas('plans', [
            'slug' => 'accounting-trial',
            'billing_period' => 'trial',
        ]);
        $this->assertDatabaseHas('plans', [
            'slug' => 'parts-inventory-starter-monthly',
            'billing_period' => 'monthly',
        ]);
        $this->assertDatabaseHas('plans', [
            'slug' => 'accounting-pro-yearly',
            'billing_period' => 'yearly',
        ]);

        $this->assertSame(12, Plan::query()->count());
    }
}
