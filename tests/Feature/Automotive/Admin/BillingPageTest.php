<?php

namespace Tests\Feature\Automotive\Admin;

use App\Http\Middleware\VerifyCsrfToken;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

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

    public function test_billing_page_now_renders_transition_message_for_attached_product(): void
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
            $response->assertSee('Subscription Access', false);
            $response->assertSee('Accounting Pro', false);
            $response->assertSee('Billing Moved To Customer Portal', false);
            $response->assertSee('Accounting Billing', false);
            $response->assertSee('Open Customer Portal Billing', false);
            $response->assertDontSee('Confirm Plan Change', false);
            $response->assertDontSee('Cancel at Period End', false);
        } finally {
            tenancy()->end();
            \Illuminate\Support\Facades\DB::purge('tenant');
        }
    }

    public function test_workspace_billing_route_is_canonical_and_legacy_automotive_billing_route_still_works(): void
    {
        [$tenant, $domain, $user] = $this->prepareTenantBillingWorkspace();

        tenancy()->initialize($tenant);

        try {
            $this->actingAs($user, 'automotive_admin');

            $canonicalResponse = $this->get("http://{$domain}/workspace/admin/billing");
            $legacyResponse = $this->get("http://{$domain}/automotive/admin/billing");

            $canonicalResponse->assertOk();
            $legacyResponse->assertRedirect("http://{$domain}/workspace/admin/billing");
            $canonicalResponse->assertSee('Billing Moved To Customer Portal', false);
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

            $response = $this->get("http://{$domain}/workspace/admin/billing");

            $response->assertOk();
            $response->assertSee('Subscription Access', false);
            $response->assertSee('Inventory Hub', false);
            $response->assertSee('Inventory Pro', false);
            $response->assertSee('Billing Moved To Customer Portal', false);
            $response->assertDontSee('Starter', false);
        } finally {
            tenancy()->end();
            \Illuminate\Support\Facades\DB::purge('tenant');
        }
    }

    public function test_attached_product_change_plan_redirects_back_with_portal_message(): void
    {
        [$tenant, $domain, $user] = $this->prepareTenantBillingWorkspace();

        $product = Product::query()->create([
            'code' => 'accounting_change_' . uniqid(),
            'name' => 'Accounting Change',
            'slug' => 'accounting-change-' . uniqid(),
            'is_active' => true,
        ]);

        $currentPlan = $this->createProductPlan($product->id, 'Accounting Current', 'accounting-current', 299);
        $targetPlan = $this->createProductPlan($product->id, 'Accounting Growth', 'accounting-growth', 399);

        $productSubscription = TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'plan_id' => $currentPlan->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_attached_change',
            'gateway_subscription_id' => 'sub_attached_change',
            'gateway_price_id' => $currentPlan->stripe_price_id,
            'payment_failures_count' => 0,
        ]);

        tenancy()->initialize($tenant);

        try {
            $this->actingAs($user, 'automotive_admin');

            $response = $this->post("http://{$domain}/workspace/admin/billing/change-plan", [
                'workspace_product' => $product->code,
                'target_plan_id' => $targetPlan->id,
            ]);

            $response->assertStatus(302);
            $this->assertStringContainsString('/workspace/admin/billing', (string) $response->headers->get('Location'));
            $response->assertSessionHas('error');
        } finally {
            tenancy()->end();
            \Illuminate\Support\Facades\DB::purge('tenant');
        }
    }

    public function test_attached_product_renew_redirects_back_with_portal_message(): void
    {
        [$tenant, $domain, $user] = $this->prepareTenantBillingWorkspace();

        $product = Product::query()->create([
            'code' => 'accounting_checkout_' . uniqid(),
            'name' => 'Accounting Checkout',
            'slug' => 'accounting-checkout-' . uniqid(),
            'is_active' => true,
        ]);

        $plan = $this->createProductPlan($product->id, 'Accounting Pro', 'accounting-pro', 299);

        $productSubscription = TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'plan_id' => null,
            'status' => 'past_due',
            'gateway' => null,
            'payment_failures_count' => 0,
        ]);

        tenancy()->initialize($tenant);

        try {
            $this->actingAs($user, 'automotive_admin');

            $response = $this->post("http://{$domain}/workspace/admin/billing/renew", [
                'workspace_product' => $product->code,
                'target_plan_id' => $plan->id,
            ]);

            $response->assertStatus(302);
            $this->assertStringContainsString('/workspace/admin/billing', (string) $response->headers->get('Location'));
            $response->assertSessionHas('error');

            $this->assertNotNull($productSubscription->fresh());
            $this->assertNull($productSubscription->fresh()->plan_id);
            $this->assertNull($productSubscription->fresh()->gateway);
            $this->assertNull($productSubscription->fresh()->gateway_checkout_session_id);
        } finally {
            tenancy()->end();
            \Illuminate\Support\Facades\DB::purge('tenant');
        }
    }

    public function test_attached_product_setup_intent_endpoint_returns_gone_message(): void
    {
        [$tenant, $domain, $user] = $this->prepareTenantBillingWorkspace();

        $product = Product::query()->create([
            'code' => 'accounting_pm_' . uniqid(),
            'name' => 'Accounting Payment Method',
            'slug' => 'accounting-payment-method-' . uniqid(),
            'is_active' => true,
        ]);

        $plan = $this->createProductPlan($product->id, 'Accounting PM', 'accounting-pm', 199);

        TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_attached_pm',
            'gateway_subscription_id' => 'sub_attached_pm',
            'gateway_price_id' => $plan->stripe_price_id,
            'payment_failures_count' => 0,
        ]);

        tenancy()->initialize($tenant);

        try {
            $this->actingAs($user, 'automotive_admin');

            $response = $this->postJson("http://{$domain}/workspace/admin/billing/payment-method/setup-intent", [
                'workspace_product' => $product->code,
            ]);

            $response->assertStatus(410);
            $response->assertJson([
                'ok' => false,
                'message' => 'Billing and payment method changes moved to the customer portal.',
            ]);
        } finally {
            tenancy()->end();
            \Illuminate\Support\Facades\DB::purge('tenant');
        }
    }

    public function test_attached_product_save_default_payment_method_returns_gone_message(): void
    {
        [$tenant, $domain, $user] = $this->prepareTenantBillingWorkspace();

        $product = Product::query()->create([
            'code' => 'accounting_default_pm_' . uniqid(),
            'name' => 'Accounting Default PM',
            'slug' => 'accounting-default-pm-' . uniqid(),
            'is_active' => true,
        ]);

        $plan = $this->createProductPlan($product->id, 'Accounting PM Save', 'accounting-pm-save', 199);

        $productSubscription = TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_attached_default_pm',
            'gateway_subscription_id' => 'sub_attached_default_pm',
            'gateway_price_id' => $plan->stripe_price_id,
            'payment_failures_count' => 0,
        ]);

        tenancy()->initialize($tenant);

        try {
            $this->actingAs($user, 'automotive_admin');

            $response = $this->postJson("http://{$domain}/workspace/admin/billing/payment-method/default", [
                'workspace_product' => $product->code,
                'payment_method_id' => 'pm_attached_default',
            ]);

            $response->assertStatus(410);
            $response->assertJson([
                'ok' => false,
                'message' => 'Billing and payment method changes moved to the customer portal.',
            ]);
        } finally {
            tenancy()->end();
            \Illuminate\Support\Facades\DB::purge('tenant');
        }
    }

    public function test_attached_product_billing_portal_post_redirects_back_with_portal_message(): void
    {
        [$tenant, $domain, $user] = $this->prepareTenantBillingWorkspace();

        $product = Product::query()->create([
            'code' => 'accounting_portal_' . uniqid(),
            'name' => 'Accounting Portal',
            'slug' => 'accounting-portal-' . uniqid(),
            'is_active' => true,
        ]);

        $plan = $this->createProductPlan($product->id, 'Accounting Portal Plan', 'accounting-portal-plan', 199);

        TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_attached_portal',
            'gateway_subscription_id' => 'sub_attached_portal',
            'gateway_price_id' => $plan->stripe_price_id,
            'payment_failures_count' => 0,
        ]);

        tenancy()->initialize($tenant);

        try {
            $this->actingAs($user, 'automotive_admin');

            $response = $this->post("http://{$domain}/workspace/admin/billing/portal", [
                'workspace_product' => $product->code,
            ]);

            $response->assertRedirect("http://{$domain}/workspace/admin/billing?workspace_product={$product->code}");
            $response->assertSessionHas('error', 'Billing actions moved to the customer portal. Tenant admin is now runtime-only.');
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

    protected function prepareTenantBillingWorkspace(): array
    {
        $tenant = Tenant::query()->create([
            'id' => 'billing-attached-action-' . uniqid(),
            'data' => ['company_name' => 'Billing Attached Action'],
        ]);

        $user = User::query()->create([
            'name' => 'Billing Attached Admin',
            'email' => 'billing-attached-admin-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        TenantUser::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        return [$tenant, $this->createTenantDomain($tenant), $user];
    }

    protected function createProductPlan(int $productId, string $name, string $slug, int|float $price): Plan
    {
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
            'stripe_price_id' => 'price_' . uniqid(),
        ]);
    }
}
