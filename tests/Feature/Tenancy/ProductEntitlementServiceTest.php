<?php

namespace Tests\Feature\Tenancy;

use App\Models\Plan;
use App\Models\Product;
use App\Models\TenantProductSubscription;
use App\Services\Tenancy\ProductEntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductEntitlementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_product_subscription_can_be_resolved_by_product_key(): void
    {
        [$product, $plan] = $this->createProductPlan();

        TenantProductSubscription::query()->create([
            'tenant_id' => 'tenant-alpha',
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $service = app(ProductEntitlementService::class);

        $this->assertTrue($service->isSubscribed('tenant-alpha', 'automotive_service'));
        $this->assertSame(5, $service->includedSeats('tenant-alpha', 'automotive_service'));
        $this->assertSame(5, $service->seatLimit('tenant-alpha', 'automotive_service'));
        $this->assertSame(2, $service->branchLimit('tenant-alpha', 'automotive_service'));
    }

    public function test_addons_extend_product_entitlement_limits(): void
    {
        [$product, $plan] = $this->createProductPlan();

        TenantProductSubscription::query()->create([
            'tenant_id' => 'tenant-alpha',
            'product_id' => $product->id,
            'product_key' => 'automotive_service',
            'plan_id' => $plan->id,
            'status' => 'trialing',
            'included_seats' => 3,
            'extra_seats' => 0,
            'branch_limit' => 1,
        ]);

        DB::table('subscription_addons')->insert([
            [
                'tenant_id' => 'tenant-alpha',
                'product_key' => 'automotive_service',
                'addon_key' => 'extra_user_seat',
                'quantity' => 2,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tenant_id' => 'tenant-alpha',
                'product_key' => 'automotive_service',
                'addon_key' => 'extra_branch',
                'quantity' => 1,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $service = app(ProductEntitlementService::class);

        $this->assertTrue($service->isSubscribed('tenant-alpha', 'automotive_service'));
        $this->assertSame(5, $service->seatLimit('tenant-alpha', 'automotive_service'));
        $this->assertSame(2, $service->branchLimit('tenant-alpha', 'automotive_service'));
    }

    public function test_inactive_product_subscription_is_not_subscribed(): void
    {
        [$product, $plan] = $this->createProductPlan();

        TenantProductSubscription::query()->create([
            'tenant_id' => 'tenant-alpha',
            'product_id' => $product->id,
            'product_key' => 'automotive_service',
            'plan_id' => $plan->id,
            'status' => 'cancelled',
        ]);

        $this->assertFalse(app(ProductEntitlementService::class)->isSubscribed('tenant-alpha', 'automotive_service'));
    }

    protected function createProductPlan(): array
    {
        $product = Product::query()->firstOrCreate([
            'code' => 'automotive_service',
        ], [
            'name' => 'Automotive Service Management',
            'slug' => 'automotive-service',
            'is_active' => true,
        ]);

        $plan = Plan::query()->firstOrCreate([
            'slug' => 'automotive-growth',
        ], [
            'product_id' => $product->id,
            'name' => 'Automotive Growth',
            'price' => 299,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'max_users' => 5,
            'max_branches' => 2,
            'max_products' => 1000,
            'max_storage_mb' => 2048,
        ]);

        DB::table('plan_limits')->insert([
            [
                'product_key' => 'automotive_service',
                'plan_id' => $plan->id,
                'limit_key' => 'included_seats',
                'limit_value' => '5',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'product_key' => 'automotive_service',
                'plan_id' => $plan->id,
                'limit_key' => 'branch_limit',
                'limit_value' => '2',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        return [$product, $plan];
    }
}
