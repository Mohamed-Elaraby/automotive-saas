<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\TenantProductSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepairTenantProductSubscriptionMirrorsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_dry_run_actions_for_missing_and_existing_mirrors(): void
    {
        [$product, $plan] = $this->createProductPlan();

        $missing = Subscription::query()->create([
            'tenant_id' => 'tenant-missing',
            'plan_id' => $plan->id,
            'status' => 'active',
            'gateway' => 'stripe',
        ]);

        $existing = Subscription::query()->create([
            'tenant_id' => 'tenant-existing',
            'plan_id' => $plan->id,
            'status' => 'past_due',
            'gateway' => 'stripe',
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $existing->tenant_id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'legacy_subscription_id' => $existing->id,
            'status' => 'active',
            'gateway' => 'stripe',
        ]);

        $this->artisan('billing:repair-product-subscription-mirrors')
            ->expectsTable([
                'Sub ID',
                'Tenant',
                'Plan ID',
                'Product ID',
                'Mirror Before',
                'Action',
            ], [
                [
                    'Sub ID' => $missing->id,
                    'Tenant' => 'tenant-missing',
                    'Plan ID' => $plan->id,
                    'Product ID' => $product->id,
                    'Mirror Before' => 'NO',
                    'Action' => 'WOULD_CREATE',
                ],
                [
                    'Sub ID' => $existing->id,
                    'Tenant' => 'tenant-existing',
                    'Plan ID' => $plan->id,
                    'Product ID' => $product->id,
                    'Mirror Before' => 'YES',
                    'Action' => 'WOULD_REPAIR',
                ],
            ])
            ->expectsOutput('Dry-run only. Re-run with --apply to repair the selected mirror rows.')
            ->assertExitCode(0);
    }

    public function test_it_can_repair_only_missing_mirrors(): void
    {
        [$product, $plan] = $this->createProductPlan();

        $missing = Subscription::query()->create([
            'tenant_id' => 'tenant-repair-missing',
            'plan_id' => $plan->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_repair_missing',
            'gateway_subscription_id' => 'sub_repair_missing',
            'gateway_price_id' => 'price_repair_missing',
        ]);

        $existing = Subscription::query()->create([
            'tenant_id' => 'tenant-repair-existing',
            'plan_id' => $plan->id,
            'status' => 'past_due',
            'gateway' => 'stripe',
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $existing->tenant_id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'legacy_subscription_id' => $existing->id,
            'status' => 'active',
            'gateway' => 'stripe',
        ]);

        $this->artisan('billing:repair-product-subscription-mirrors --apply --only-missing')
            ->expectsOutput('Mirror repair completed. Updated or created 1 row(s).')
            ->assertExitCode(0);

        $this->assertDatabaseHas('tenant_product_subscriptions', [
            'tenant_id' => 'tenant-repair-missing',
            'legacy_subscription_id' => $missing->id,
            'product_id' => $product->id,
            'status' => 'active',
            'gateway_subscription_id' => 'sub_repair_missing',
        ]);

        $existingMirror = TenantProductSubscription::query()
            ->where('legacy_subscription_id', $existing->id)
            ->first();

        $this->assertSame('active', $existingMirror?->status);
    }

    public function test_it_can_repair_an_existing_mismatched_mirror(): void
    {
        [$product, $plan] = $this->createProductPlan();

        $subscription = Subscription::query()->create([
            'tenant_id' => 'tenant-repair-existing',
            'plan_id' => $plan->id,
            'status' => 'past_due',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_fix_existing',
            'gateway_subscription_id' => 'sub_fix_existing',
            'gateway_price_id' => 'price_fix_existing',
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $subscription->tenant_id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'legacy_subscription_id' => $subscription->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_old',
            'gateway_subscription_id' => null,
            'gateway_price_id' => null,
        ]);

        $this->artisan('billing:repair-product-subscription-mirrors --apply --subscription=' . $subscription->id)
            ->expectsOutput('Mirror repair completed. Updated or created 1 row(s).')
            ->assertExitCode(0);

        $this->assertDatabaseHas('tenant_product_subscriptions', [
            'tenant_id' => 'tenant-repair-existing',
            'legacy_subscription_id' => $subscription->id,
            'status' => 'past_due',
            'gateway_customer_id' => 'cus_fix_existing',
            'gateway_subscription_id' => 'sub_fix_existing',
            'gateway_price_id' => 'price_fix_existing',
        ]);
    }

    protected function createProductPlan(): array
    {
        $product = Product::query()->firstOrCreate(
            ['code' => 'automotive_service'],
            [
                'name' => 'Automotive Service Management',
                'slug' => 'automotive-service',
                'is_active' => true,
            ]
        );

        $plan = Plan::query()->create([
            'product_id' => $product->id,
            'name' => 'Mirror Repair Plan',
            'slug' => 'mirror-repair-' . uniqid(),
            'price' => 199,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
        ]);

        return [$product, $plan];
    }
}
