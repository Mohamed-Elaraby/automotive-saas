<?php

namespace Tests\Feature\Admin\Tenants;

use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTenantProductSubscriptionsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_and_filters_product_subscriptions(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Central Admin',
            'email' => 'admin-tps-index-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        $matchingProduct = Product::query()->create([
            'code' => 'inventory_suite',
            'name' => 'Inventory Suite',
            'slug' => 'inventory-suite',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $otherProduct = Product::query()->create([
            'code' => 'hr_suite',
            'name' => 'HR Suite',
            'slug' => 'hr-suite',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $matchingPlan = Plan::query()->create([
            'product_id' => $matchingProduct->id,
            'name' => 'Inventory Pro',
            'slug' => 'inventory-pro-' . uniqid(),
            'price' => 199,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
        ]);

        $otherPlan = Plan::query()->create([
            'product_id' => $otherProduct->id,
            'name' => 'HR Starter',
            'slug' => 'hr-starter-' . uniqid(),
            'price' => 99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
        ]);

        $matchingTenant = Tenant::query()->create([
            'id' => 'tenant-product-sub-match-' . uniqid(),
            'data' => ['company_name' => 'Matching Tenant'],
        ]);

        $otherTenant = Tenant::query()->create([
            'id' => 'tenant-product-sub-other-' . uniqid(),
            'data' => ['company_name' => 'Other Tenant'],
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $matchingTenant->id,
            'product_id' => $matchingProduct->id,
            'plan_id' => $matchingPlan->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_match_tps',
            'gateway_subscription_id' => 'sub_match_tps',
            'last_synced_from_stripe_at' => now()->subDays(10),
            'last_sync_status' => 'success',
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $otherTenant->id,
            'product_id' => $otherProduct->id,
            'plan_id' => $otherPlan->id,
            'status' => 'past_due',
            'gateway' => 'manual',
            'gateway_customer_id' => 'cus_other_tps',
            'gateway_subscription_id' => 'sub_other_tps',
            'last_sync_status' => 'failed',
            'last_sync_error' => 'Stripe lookup failed.',
        ]);

        AdminActivityLog::query()->create([
            'admin_user_id' => $admin->id,
            'admin_email' => $admin->email,
            'action' => 'tenant.product_subscription.bulk_sync_queued_from_stripe',
            'subject_type' => 'tenant_product_subscription',
            'tenant_id' => null,
            'context_payload' => [
                'bulk_sync_action' => 'filtered',
                'summary' => [
                    'queued' => 2,
                    'skipped' => 1,
                ],
            ],
        ]);

        $response = $this
            ->actingAs($admin, 'admin')
            ->get(route('admin.tenants.product-subscriptions.index', [
                'tenant_id' => $matchingTenant->id,
                'product_id' => $matchingProduct->id,
                'status' => 'active',
                'gateway' => 'stripe',
                'last_sync_status' => 'success',
                'sync_freshness' => 'stale_7d',
            ]));

        $response->assertOk();
        $response->assertSee('Product Subscriptions', false);
        $response->assertSee($matchingTenant->id, false);
        $response->assertSee('Inventory Suite', false);
        $response->assertSee('Inventory Pro', false);
        $response->assertSee('cus_match_tps', false);
        $response->assertSee('SUCCESS', false);
        $response->assertSee('STALE', false);
        $response->assertSee('Sync', false);
        $response->assertSee('Recent Bulk Operations', false);
        $response->assertSee('QUEUED', false);
        $response->assertSee('Queued: 2 | Skipped: 1', false);
        $response->assertDontSee($otherTenant->id, false);
        $response->assertDontSee('cus_other_tps', false);
        $response->assertDontSee('FAILED', false);
    }

    public function test_index_can_export_filtered_product_subscriptions_to_csv(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Central Admin',
            'email' => 'admin-tps-export-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        $product = Product::query()->create([
            'code' => 'export_suite',
            'name' => 'Export Suite',
            'slug' => 'export-suite',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $plan = Plan::query()->create([
            'product_id' => $product->id,
            'name' => 'Export Plan',
            'slug' => 'export-plan-' . uniqid(),
            'price' => 129,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
        ]);

        $matchingTenant = Tenant::query()->create([
            'id' => 'tenant-product-sub-export-' . uniqid(),
            'data' => ['company_name' => 'Export Tenant'],
        ]);

        $otherTenant = Tenant::query()->create([
            'id' => 'tenant-product-sub-export-other-' . uniqid(),
            'data' => ['company_name' => 'Export Other Tenant'],
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $matchingTenant->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'status' => 'past_due',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_export_match',
            'gateway_subscription_id' => 'sub_export_match',
            'last_sync_status' => 'failed',
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $otherTenant->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'gateway' => 'manual',
            'gateway_customer_id' => 'cus_export_other',
        ]);

        $response = $this
            ->actingAs($admin, 'admin')
            ->get(route('admin.tenants.product-subscriptions.export', [
                'tenant_id' => $matchingTenant->id,
                'last_sync_status' => 'failed',
            ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $streamedResponse = $response->baseResponse ?? $response;

        ob_start();
        $streamedResponse->sendContent();
        $csv = (string) ob_get_clean();

        $this->assertStringContainsString('record_id', $csv);
        $this->assertStringContainsString($matchingTenant->id, $csv);
        $this->assertStringContainsString('cus_export_match', $csv);
        $this->assertStringNotContainsString($otherTenant->id, $csv);
        $this->assertStringNotContainsString('cus_export_other', $csv);
    }
}
