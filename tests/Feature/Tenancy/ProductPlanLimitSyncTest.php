<?php

namespace Tests\Feature\Tenancy;

use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Services\Admin\AdminTenantLifecycleService;
use App\Services\Tenancy\ProductEntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductPlanLimitSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_change_updates_product_branch_limit_snapshot_and_entitlements(): void
    {
        [$tenant, $starter, $growth] = $this->tenantWithPlans();

        $legacy = Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $starter->id,
            'status' => 'active',
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $starter->product_id,
            'product_key' => 'automotive_service',
            'legacy_subscription_id' => $legacy->id,
            'plan_id' => $starter->id,
            'status' => 'active',
            'included_seats' => 2,
            'branch_limit' => 1,
        ]);

        app(AdminTenantLifecycleService::class)->changeLatestPlan($tenant->id, $growth->id);

        $productSubscription = TenantProductSubscription::query()
            ->where('tenant_id', $tenant->id)
            ->where('product_key', 'automotive_service')
            ->firstOrFail();

        $this->assertSame($growth->id, (int) $productSubscription->plan_id);
        $this->assertSame(3, (int) $productSubscription->branch_limit);
        $this->assertSame(3, app(ProductEntitlementService::class)->branchLimit($tenant->id, 'automotive_service'));
    }

    public function test_stale_subscription_limit_does_not_override_current_plan_limit(): void
    {
        [$tenant, $starter, $growth] = $this->tenantWithPlans();

        TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $starter->product_id,
            'product_key' => 'automotive_service',
            'plan_id' => $growth->id,
            'status' => 'active',
            'included_seats' => 2,
            'branch_limit' => 1,
        ]);

        $this->assertSame(3, app(ProductEntitlementService::class)->branchLimit($tenant->id, 'automotive_service'));
    }

    protected function tenantWithPlans(): array
    {
        $tenant = Tenant::query()->create([
            'id' => 'tenant-plan-limit-sync-' . Str::uuid(),
            'data' => ['company_name' => 'Plan Limit Sync'],
        ]);

        $product = Product::query()->firstOrCreate(['code' => 'automotive_service'], [
            'name' => 'Automotive Service',
            'slug' => 'automotive-service-' . Str::uuid(),
            'is_active' => true,
        ]);

        $starter = Plan::query()->create([
            'product_id' => $product->id,
            'name' => 'Automotive Starter',
            'slug' => 'automotive-starter-' . Str::uuid(),
            'price' => 99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'max_users' => 2,
            'max_branches' => 1,
        ]);

        $growth = Plan::query()->create([
            'product_id' => $product->id,
            'name' => 'Automotive Growth',
            'slug' => 'automotive-growth-' . Str::uuid(),
            'price' => 199,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'max_users' => 5,
            'max_branches' => 3,
        ]);

        foreach ([[$starter, 1], [$growth, 3]] as [$plan, $branches]) {
            DB::table('plan_limits')->insert([
                [
                    'product_key' => 'automotive_service',
                    'plan_id' => $plan->id,
                    'limit_key' => 'included_seats',
                    'limit_value' => (string) $plan->max_users,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'product_key' => 'automotive_service',
                    'plan_id' => $plan->id,
                    'limit_key' => 'branch_limit',
                    'limit_value' => (string) $branches,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }

        return [$tenant, $starter, $growth];
    }
}
