<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\Product;
use App\Services\Billing\BillingPlanCatalogService;
use App\Services\Billing\CheckoutStripePlanRecoveryService;
use App\Services\Billing\StripePlanCatalogSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CheckoutStripePlanRecoveryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_recover_paid_plan_attempts_sync_when_local_plan_has_no_stripe_price(): void
    {
        $product = Product::query()->updateOrCreate(
            ['code' => 'automotive_service'],
            [
                'name' => 'Automotive Service Management',
                'slug' => 'automotive-service-management',
                'is_active' => true,
            ]
        );

        $plan = Plan::query()->create([
            'product_id' => $product->id,
            'name' => 'Automotive Starter',
            'slug' => 'automotive-starter',
            'price' => 149,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_price_id' => null,
        ]);

        $billingCatalog = app(BillingPlanCatalogService::class);

        $sync = Mockery::mock(StripePlanCatalogSyncService::class);
        $sync->shouldReceive('syncPlan')
            ->once()
            ->andReturnUsing(function (Plan $eloquentPlan) {
                $eloquentPlan->forceFill([
                    'stripe_product_id' => 'prod_recovered_123',
                    'stripe_price_id' => 'price_recovered_123',
                ])->save();

                return ['ok' => true];
            });

        $service = new CheckoutStripePlanRecoveryService($billingCatalog, $sync);
        $recovered = $service->recoverPaidPlan($plan->id, 'automotive_service');

        $this->assertNotNull($recovered);
        $this->assertSame('price_recovered_123', $recovered->stripe_price_id);
    }

    public function test_retry_repairs_inactive_or_misaligned_stripe_price_once(): void
    {
        $product = Product::query()->updateOrCreate(
            ['code' => 'parts_inventory'],
            [
                'name' => 'Spare Parts Inventory',
                'slug' => 'spare-parts-inventory',
                'is_active' => true,
            ]
        );

        $plan = Plan::query()->create([
            'product_id' => $product->id,
            'name' => 'Parts Starter',
            'slug' => 'parts-starter',
            'price' => 149,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_old',
            'stripe_price_id' => 'price_old',
        ]);

        $billingCatalog = app(BillingPlanCatalogService::class);

        $sync = Mockery::mock(StripePlanCatalogSyncService::class);
        $sync->shouldReceive('syncPlan')
            ->once()
            ->andReturnUsing(function (Plan $eloquentPlan) {
                $eloquentPlan->forceFill([
                    'stripe_product_id' => 'prod_new',
                    'stripe_price_id' => 'price_new',
                ])->save();

                return ['ok' => true];
            });

        $service = new CheckoutStripePlanRecoveryService($billingCatalog, $sync);
        $attempts = 0;

        $result = $service->retryIfStripePriceNeedsRepair(
            $billingCatalog->findPaidPlanById($plan->id, 'parts_inventory'),
            'parts_inventory',
            function (object $checkoutPlan) use (&$attempts) {
                $attempts++;

                if ($attempts === 1) {
                    return [
                        'success' => false,
                        'message' => 'The selected paid plan is linked to an inactive Stripe price. Sync the plan prices in admin before checkout.',
                    ];
                }

                return [
                    'success' => true,
                    'session_id' => 'cs_test_123',
                    'checkout_url' => 'https://checkout.stripe.test/session/recovered',
                    'used_price' => $checkoutPlan->stripe_price_id,
                ];
            }
        );

        $this->assertSame(2, $attempts);
        $this->assertTrue($result['success']);
        $this->assertSame('price_new', $result['used_price']);
    }
}
