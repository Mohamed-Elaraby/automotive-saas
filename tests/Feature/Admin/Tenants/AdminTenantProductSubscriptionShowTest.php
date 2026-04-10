<?php

namespace Tests\Feature\Admin\Tenants;

use App\Models\Admin;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTenantProductSubscriptionShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_displays_product_subscription_details_and_diagnostics(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Central Admin',
            'email' => 'admin-tps-show-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        $tenant = Tenant::query()->create([
            'id' => 'tenant-product-sub-show-' . uniqid(),
            'data' => [
                'company_name' => 'Show Tenant Co',
                'owner_name' => 'Show Owner',
                'owner_email' => 'show-owner@example.test',
            ],
        ]);

        $product = Product::query()->create([
            'code' => 'finance_suite',
            'name' => 'Finance Suite',
            'slug' => 'finance-suite',
            'is_active' => true,
            'sort_order' => 3,
        ]);

        $plan = Plan::query()->create([
            'product_id' => $product->id,
            'name' => 'Finance Scale',
            'slug' => 'finance-scale-' . uniqid(),
            'price' => 499,
            'currency' => 'USD',
            'billing_period' => 'yearly',
            'is_active' => true,
        ]);

        $legacySubscription = Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $subscription = TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'legacy_subscription_id' => $legacySubscription->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_show_tps',
            'gateway_subscription_id' => 'sub_show_tps',
            'gateway_checkout_session_id' => 'cs_show_tps',
            'gateway_price_id' => 'price_show_tps',
            'payment_failures_count' => 1,
            'ends_at' => now()->addMonth(),
        ]);

        $response = $this
            ->actingAs($admin, 'admin')
            ->get(route('admin.tenants.product-subscriptions.show', $subscription->id));

        $response->assertOk();
        $response->assertSee('Product Subscription Details', false);
        $response->assertSee('Finance Suite', false);
        $response->assertSee('Finance Scale', false);
        $response->assertSee('cus_show_tps', false);
        $response->assertSee('sub_show_tps', false);
        $response->assertSee('cs_show_tps', false);
        $response->assertSee('price_show_tps', false);
        $response->assertSee((string) $tenant->id, false);
        $response->assertSee('Tenant Snapshot', false);
        $response->assertSee('Diagnostics', false);
        $response->assertSee('Legacy Subscription ID', false);
    }
}
