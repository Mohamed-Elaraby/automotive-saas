<?php

namespace Tests\Feature\Automotive\Admin;

use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Models\Domain;
use Tests\TestCase;

class BillingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_related_models_can_be_prepared_for_ui_state_rendering(): void
    {
        Carbon::setTestNow('2026-03-26 12:00:00');

        $starter = $this->createPlan('Starter', 'starter', 199, 'price_starter');
        $growth = $this->createPlan('Growth', 'growth', 399, 'price_growth');

        $subscription = $this->createSubscription([
            'tenant_id' => 'billing-page-test',
            'plan_id' => $starter->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_test_active',
            'gateway_subscription_id' => 'sub_test_active',
            'gateway_price_id' => 'price_starter',
        ]);

        $this->assertSame('active', $subscription->status);
        $this->assertSame($starter->id, $subscription->plan_id);
        $this->assertSame('price_starter', $subscription->gateway_price_id);
        $this->assertSame('Growth', $growth->name);
    }

    public function test_billing_state_can_represent_same_plan_selection_safely(): void
    {
        Carbon::setTestNow('2026-03-26 12:00:00');

        $starter = $this->createPlan('Starter', 'starter', 199, 'price_starter');

        $subscription = $this->createSubscription([
            'tenant_id' => 'billing-page-test',
            'plan_id' => $starter->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_test_same_plan',
            'gateway_subscription_id' => 'sub_test_same_plan',
            'gateway_price_id' => 'price_starter',
        ]);

        $this->assertSame($starter->id, $subscription->plan_id);
        $this->assertSame('active', $subscription->status);
    }

    public function test_billing_state_can_represent_past_due_or_grace_period_subscription(): void
    {
        Carbon::setTestNow('2026-03-26 12:00:00');

        $starter = $this->createPlan('Starter', 'starter', 199, 'price_starter');

        $subscription = $this->createSubscription([
            'tenant_id' => 'billing-page-test',
            'plan_id' => $starter->id,
            'status' => 'past_due',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_test_past_due',
            'gateway_subscription_id' => 'sub_test_past_due',
            'gateway_price_id' => 'price_starter',
            'last_payment_failed_at' => now()->subHour(),
            'past_due_started_at' => now()->subHour(),
            'grace_ends_at' => now()->addDays(2),
        ]);

        $this->assertSame('past_due', $subscription->status);
        $this->assertNotNull($subscription->grace_ends_at);
    }

    public function test_billing_state_can_represent_suspended_subscription(): void
    {
        Carbon::setTestNow('2026-03-26 12:00:00');

        $starter = $this->createPlan('Starter', 'starter', 199, 'price_starter');

        $subscription = $this->createSubscription([
            'tenant_id' => 'billing-page-test',
            'plan_id' => $starter->id,
            'status' => 'suspended',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_test_suspended',
            'gateway_subscription_id' => 'sub_test_suspended',
            'gateway_price_id' => 'price_starter',
            'suspended_at' => now()->subHour(),
        ]);

        $this->assertSame('suspended', $subscription->status);
        $this->assertNotNull($subscription->suspended_at);
    }

    public function test_billing_page_can_focus_attached_non_primary_product_read_only(): void
    {
        $tenant = Tenant::query()->create([
            'id' => 'billing-product-focus-' . uniqid(),
            'data' => ['company_name' => 'Billing Product Focus'],
        ]);

        $user = User::query()->create([
            'name' => 'Billing Admin',
            'email' => 'billing-admin-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        TenantUser::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        $domain = $this->createTenantDomain($tenant);

        $accountingProduct = Product::query()->create([
            'code' => 'accounting_billing_' . uniqid(),
            'name' => 'Accounting Billing',
            'slug' => 'accounting-billing-' . uniqid(),
            'is_active' => true,
        ]);

        $plan = Plan::query()->create([
            'product_id' => $accountingProduct->id,
            'name' => 'Accounting Pro',
            'slug' => 'accounting-pro-' . uniqid(),
            'description' => 'Accounting billing plan',
            'price' => 299,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => 'price_' . uniqid(),
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $accountingProduct->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_accounting_focus',
            'gateway_subscription_id' => 'sub_accounting_focus',
            'gateway_price_id' => $plan->stripe_price_id,
            'payment_failures_count' => 0,
        ]);

        tenancy()->initialize($tenant);

        try {
            $this->actingAs($user, 'automotive_admin');

            $response = $this->get("http://{$domain}/automotive/admin/billing?workspace_product=" . urlencode($accountingProduct->code));

            $response->assertOk();
            $response->assertSee('Accounting Billing Billing', false);
            $response->assertSee('Choose Paid Plan', false);
            $response->assertSee('Accounting Pro', false);
            $response->assertSee('Read-only billing view for this attached workspace product.', false);
            $response->assertSee('shared product portal flow', false);
            $response->assertDontSee('Confirm Plan Change', false);
            $response->assertDontSee('Cancel at Period End', false);
            $response->assertDontSee('Resume Subscription', false);
        } finally {
            tenancy()->end();
            \Illuminate\Support\Facades\DB::purge('tenant');
        }
    }

    public function test_billing_page_uses_primary_product_plan_catalog_for_non_automotive_alias_product(): void
    {
        $tenant = Tenant::query()->create([
            'id' => 'billing-alias-primary-' . uniqid(),
            'data' => ['company_name' => 'Billing Alias Primary'],
        ]);

        $user = User::query()->create([
            'name' => 'Billing Alias Admin',
            'email' => 'billing-alias-admin-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        TenantUser::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        $domain = $this->createTenantDomain($tenant);

        $inventoryProduct = Product::query()->create([
            'code' => 'inventory_hub_' . uniqid(),
            'name' => 'Inventory Hub',
            'slug' => 'inventory-hub-' . uniqid(),
            'is_active' => true,
        ]);

        $inventoryPlan = Plan::query()->create([
            'product_id' => $inventoryProduct->id,
            'name' => 'Inventory Pro',
            'slug' => 'inventory-pro-' . uniqid(),
            'description' => 'Inventory billing plan',
            'price' => 249,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => 'price_' . uniqid(),
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $inventoryProduct->id,
            'plan_id' => $inventoryPlan->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_inventory_primary',
            'gateway_subscription_id' => 'sub_inventory_primary',
            'gateway_price_id' => $inventoryPlan->stripe_price_id,
            'payment_failures_count' => 0,
        ]);

        tenancy()->initialize($tenant);

        try {
            $this->actingAs($user, 'automotive_admin');

            $response = $this->get("http://{$domain}/automotive/admin/billing");

            $response->assertOk();
            $response->assertSee('Inventory Hub Billing', false);
            $response->assertSee('Inventory Pro', false);
            $response->assertDontSee('Starter', false);
        } finally {
            tenancy()->end();
            \Illuminate\Support\Facades\DB::purge('tenant');
        }
    }

    protected function createSubscription(array $overrides = []): Subscription
    {
        return Subscription::query()->create(array_merge([
            'tenant_id' => 'billing-page-test',
            'plan_id' => $this->createPlan('Default', 'default-' . uniqid(), 199, 'price_default_' . uniqid())->id,
            'status' => 'active',
            'trial_ends_at' => null,
            'grace_ends_at' => null,
            'last_payment_failed_at' => null,
            'past_due_started_at' => null,
            'suspended_at' => null,
            'cancelled_at' => null,
            'ends_at' => null,
            'payment_failures_count' => 0,
            'external_id' => null,
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_test_billing_page',
            'gateway_subscription_id' => 'sub_test_billing_page_' . uniqid(),
            'gateway_checkout_session_id' => 'cs_test_billing_page_' . uniqid(),
            'gateway_price_id' => 'price_default',
        ], $overrides));
    }

    protected function createPlan(string $name, string $slug, int|float $price, ?string $stripePriceId): Plan
    {
        $productId = Product::query()->where('code', 'automotive_service')->value('id');

        return Plan::query()->create([
            'product_id' => $productId,
            'name' => $name,
            'slug' => $slug . '-' . uniqid(),
            'description' => $name . ' description',
            'price' => $price,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => $stripePriceId,
        ]);
    }

    protected function createTenantDomain(Tenant $tenant): string
    {
        $domain = 'billing-' . uniqid() . '.example.test';

        Domain::query()->create([
            'domain' => $domain,
            'tenant_id' => $tenant->id,
        ]);

        return $domain;
    }
}
