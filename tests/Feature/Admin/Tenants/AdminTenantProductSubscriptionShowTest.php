<?php

namespace Tests\Feature\Admin\Tenants;

use App\Models\Admin;
use App\Models\Plan;
use App\Models\Product;
use App\Models\BillingInvoice;
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
            'last_synced_from_stripe_at' => now()->subMinutes(30),
            'last_sync_status' => 'success',
            'payment_failures_count' => 1,
            'ends_at' => now()->addMonth(),
        ]);

        BillingInvoice::query()->create([
            'subscription_id' => $legacySubscription->id,
            'tenant_id' => $tenant->id,
            'gateway' => 'stripe',
            'gateway_invoice_id' => 'in_show_tps',
            'gateway_customer_id' => 'cus_show_tps',
            'gateway_subscription_id' => 'sub_show_tps',
            'invoice_number' => 'INV-SHOW-TPS',
            'status' => 'paid',
            'billing_reason' => 'subscription_cycle',
            'currency' => 'usd',
            'total_minor' => 49900,
            'total_decimal' => 499,
            'amount_paid_minor' => 49900,
            'amount_paid_decimal' => 499,
            'amount_due_minor' => 0,
            'amount_due_decimal' => 0,
            'hosted_invoice_url' => 'https://example.test/invoices/show',
            'invoice_pdf' => 'https://example.test/invoices/show.pdf',
            'issued_at' => now()->subDay(),
            'paid_at' => now()->subHours(12),
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
        $response->assertSee('Latest Synced Invoice', false);
        $response->assertSee('INV-SHOW-TPS', false);
        $response->assertSee('in_show_tps', false);
        $response->assertSee('Health Hints', false);
        $response->assertSee('Latest local invoice is paid.', false);
        $response->assertSee('Diagnostics', false);
        $response->assertSee('Legacy Subscription ID', false);
        $response->assertSee('Last Synced From Stripe At', false);
        $response->assertSee('Last Sync Status', false);
        $response->assertSee('success', false);
        $response->assertSee(route('admin.subscriptions.show', $legacySubscription->id), false);
        $response->assertSee('Open Legacy Subscription', false);
    }
}
