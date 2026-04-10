<?php

namespace Tests\Feature\Admin\Tenants;

use App\Models\Admin;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTenantShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_displays_product_level_subscription_details(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Central Admin',
            'email' => 'admin-tenant-show-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        $tenant = Tenant::query()->create([
            'id' => 'tenant-show-' . uniqid(),
            'data' => [
                'company_name' => 'Tenant Show Co',
                'owner_email' => 'owner-show@example.test',
            ],
        ]);

        $product = Product::query()->create([
            'code' => 'inventory_suite',
            'name' => 'Inventory Suite',
            'slug' => 'inventory-suite',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $plan = Plan::query()->create([
            'product_id' => $product->id,
            'name' => 'Inventory Growth',
            'slug' => 'inventory-growth-' . uniqid(),
            'price' => 349,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => 'price_' . uniqid(),
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'status' => 'past_due',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_tenant_show',
            'gateway_subscription_id' => 'sub_tenant_show',
            'gateway_checkout_session_id' => 'cs_tenant_show',
            'gateway_price_id' => 'price_tenant_show',
            'payment_failures_count' => 2,
            'past_due_started_at' => now(),
            'ends_at' => now()->addDays(7),
        ]);

        $response = $this
            ->actingAs($admin, 'admin')
            ->get(route('admin.tenants.show', $tenant->id));

        $response->assertOk();
        $response->assertSee('Product Subscriptions', false);
        $response->assertSee('Inventory Suite', false);
        $response->assertSee('Inventory Growth', false);
        $response->assertSee('PAST DUE', false);
        $response->assertSee('cus_tenant_show', false);
        $response->assertSee('sub_tenant_show', false);
        $response->assertSee('cs_tenant_show', false);
        $response->assertSee('Has Product Subscriptions', false);
        $response->assertSee('Product Subscriptions Count', false);
    }
}
