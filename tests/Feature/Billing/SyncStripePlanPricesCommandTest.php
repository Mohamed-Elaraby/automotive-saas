<?php

namespace Tests\Feature\Billing;

use App\Services\Billing\StripePlanCatalogSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SyncStripePlanPricesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_uses_sync_paid_plans_service_api(): void
    {
        $service = Mockery::mock(StripePlanCatalogSyncService::class);
        $service->shouldReceive('syncPaidPlans')
            ->once()
            ->with(false, null)
            ->andReturn(collect([
                [
                    'plan_name' => 'Growth',
                    'slug' => 'growth',
                    'local_price' => 199,
                    'currency' => 'USD',
                    'billing_period' => 'monthly',
                    'old_price_id' => 'price_old',
                    'new_price_id' => 'price_old',
                    'product_id' => 1,
                    'action' => 'DRY_RUN',
                    'aligned_before' => true,
                    'aligned_after' => true,
                ],
            ]));

        $this->app->instance(StripePlanCatalogSyncService::class, $service);

        $this->artisan('billing:sync-stripe-plan-prices')
            ->expectsOutput('Dry-run only. Re-run with --apply to create/link Stripe prices and update local plans.')
            ->assertExitCode(0);
    }
}
