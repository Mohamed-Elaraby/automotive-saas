<?php

namespace Tests\Feature\Automotive\Portal;

use App\Contracts\Billing\PaymentGatewayInterface;
use App\Models\AdminNotification;
use App\Models\BillingFeature;
use App\Models\CustomerOnboardingProfile;
use App\Models\CustomerPortalNotification;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductCapability;
use App\Models\ProductEnablementRequest;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\User;
use App\Services\Automotive\StartPaidCheckoutService;
use App\Services\Automotive\StartTrialService;
use App\Services\Billing\PaymentGatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class CustomerPortalBillingOptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_trial_workspace_without_live_stripe_subscription_can_still_start_paid_checkout(): void
    {
        $user = User::query()->create([
            'name' => 'Portal User',
            'email' => 'portal-user-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        CustomerOnboardingProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Portal Co',
            'subdomain' => 'portal-' . uniqid(),
            'base_host' => 'example.test',
        ]);

        $trialPlan = $this->createPlan('Trial Plan', 'trial-plan-' . uniqid(), 'trial', 0);
        $paidPlan = $this->createPlan('Pro Plan', 'pro-plan-' . uniqid(), 'monthly', 149);

        $tenant = Tenant::query()->create([
            'id' => 'tenant-portal-' . uniqid(),
            'data' => [
                'company_name' => 'Portal Co',
                'owner_email' => $user->email,
            ],
        ]);

        DB::table('tenant_users')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $trialPlan->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_checkout_session_id' => 'cs_portal_pending_only',
            'gateway_subscription_id' => null,
            'gateway_price_id' => $paidPlan->stripe_price_id,
            'ends_at' => now()->addDays(5),
        ]);

        $automotiveProduct = Product::query()->where('code', 'automotive_service')->firstOrFail();

        $response = $this->actingAs($user, 'web')->get(route('automotive.portal', [
            'product' => $automotiveProduct->slug,
        ]));

        $response->assertOk();
        $response->assertSee('Select &amp; Continue', false);
        $response->assertDontSee('Billing Managed In System', false);
    }

    public function test_start_trial_ignores_email_like_coupon_values_from_profile(): void
    {
        $email = 'client_1@gmail.com';

        $user = User::query()->create([
            'name' => 'Portal Trial Coupon User',
            'email' => $email,
            'password' => bcrypt('password'),
        ]);

        CustomerOnboardingProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Portal Trial Coupon Co',
            'subdomain' => 'portal-trial-coupon-' . uniqid(),
            'base_host' => 'example.test',
            'coupon_code' => $email,
            'password_payload' => \Illuminate\Support\Facades\Crypt::encryptString('secret-pass'),
        ]);

        $service = Mockery::mock(StartTrialService::class);
        $service->shouldReceive('start')
            ->once()
            ->withArgs(function (array $payload) use ($email): bool {
                return ($payload['email'] ?? null) === $email
                    && ($payload['coupon_code'] ?? null) === '';
            })
            ->andReturn([
                'ok' => true,
                'status' => 201,
                'tenant_id' => 'trial-coupon-tenant',
                'domain' => 'trial-coupon-tenant.example.test',
                'login_url' => 'https://trial-coupon-tenant.example.test/automotive/admin/login',
            ]);
        $this->app->instance(StartTrialService::class, $service);

        $response = $this->actingAs($user, 'web')
            ->withSession(['_token' => 'test-token'])
            ->post(route('automotive.portal.start-trial'), [
                '_token' => 'test-token',
            ]);

        $response->assertRedirect(route('automotive.portal'));
        $response->assertSessionHas('success', 'Your workspace trial is ready now.');
    }

    public function test_portal_paid_plan_cards_show_real_plan_limits(): void
    {
        $user = User::query()->create([
            'name' => 'Portal Limits User',
            'email' => 'portal-limits-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        CustomerOnboardingProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Portal Limits Co',
            'subdomain' => 'portal-limits-' . uniqid(),
            'base_host' => 'example.test',
        ]);

        $automotiveProductId = Product::query()->where('code', 'automotive_service')->value('id');

        $plan = Plan::query()->create([
            'product_id' => $automotiveProductId,
            'name' => 'Growth',
            'slug' => 'growth-' . uniqid(),
            'description' => 'Real plan description',
            'price' => 399,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => 'price_' . uniqid(),
            'max_users' => 12,
            'max_branches' => 4,
            'max_products' => 250,
            'max_storage_mb' => 2048,
        ]);
        $barcode = BillingFeature::query()->create([
            'name' => 'Barcode support',
            'slug' => 'barcode-support-' . uniqid(),
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $reports = BillingFeature::query()->create([
            'name' => 'Inventory reports',
            'slug' => 'inventory-reports-' . uniqid(),
            'is_active' => true,
            'sort_order' => 2,
        ]);
        $plan->billingFeatures()->sync([
            $barcode->id => ['sort_order' => 0],
            $reports->id => ['sort_order' => 1],
        ]);

        $automotiveProduct = Product::query()->where('code', 'automotive_service')->firstOrFail();

        $response = $this->actingAs($user, 'web')->get(route('automotive.portal', [
            'product' => $automotiveProduct->slug,
        ]));

        $response->assertOk();
        $response->assertSee('What you get:', false);
        $response->assertDontSee('Plan Limits', false);
        $response->assertSee('Users', false);
        $response->assertSee('12', false);
        $response->assertSee('Branches', false);
        $response->assertSee('4', false);
        $response->assertSee('Products', false);
        $response->assertSee('250', false);
        $response->assertSee('Storage', false);
        $response->assertSee('2048 MB', false);
        $response->assertSee('Barcode support', false);
        $response->assertSee('Inventory reports', false);
        $response->assertSee(strtoupper((string) $plan->slug), false);
    }

    public function test_portal_billing_page_can_manage_attached_product_subscription(): void
    {
        $user = User::query()->create([
            'name' => 'Portal Billing User',
            'email' => 'portal-billing-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        CustomerOnboardingProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Portal Billing Co',
            'subdomain' => 'portal-billing-' . uniqid(),
            'base_host' => 'example.test',
        ]);

        $tenant = Tenant::query()->create([
            'id' => 'tenant-portal-billing-' . uniqid(),
            'data' => ['company_name' => 'Portal Billing Co'],
        ]);

        DB::table('tenant_users')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $accountingProduct = Product::query()->create([
            'code' => 'accounting_portal_' . uniqid(),
            'name' => 'Accounting Portal',
            'slug' => 'accounting-portal-' . uniqid(),
            'is_active' => true,
        ]);

        $plan = Plan::query()->create([
            'product_id' => $accountingProduct->id,
            'name' => 'Accounting Pro',
            'slug' => 'accounting-pro-' . uniqid(),
            'description' => 'Accounting portal plan',
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
            'gateway_customer_id' => 'cus_portal_billing_' . uniqid(),
            'gateway_subscription_id' => 'sub_portal_billing_' . uniqid(),
            'gateway_price_id' => $plan->stripe_price_id,
        ]);

        $response = $this->actingAs($user, 'web')->get(route('automotive.portal.billing.status', [
            'workspace_product' => $accountingProduct->code,
        ]));

        $response->assertOk();
        $response->assertSee('Accounting Portal Billing', false);
        $response->assertSee('Workspace Products', false);
        $response->assertSee('Accounting Portal', false);
        $response->assertSee('Manage Accounting Portal Billing', false);
        $response->assertSee('Accounting Portal Invoice History', false);
    }

    public function test_portal_does_not_default_to_automotive_plans_without_an_explicit_product_choice(): void
    {
        $user = User::query()->create([
            'name' => 'Portal Product Filter User',
            'email' => 'portal-product-filter-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        CustomerOnboardingProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Portal Product Filter Co',
            'subdomain' => 'portal-product-filter-' . uniqid(),
            'base_host' => 'example.test',
        ]);

        $automotivePlan = $this->createPlan('Automotive Growth', 'automotive-growth-' . uniqid(), 'monthly', 399);
        $accountingProduct = Product::query()->create([
            'code' => 'accounting_' . uniqid(),
            'name' => 'Accounting System',
            'slug' => 'accounting-' . uniqid(),
            'is_active' => true,
        ]);
        $accountingPlan = Plan::query()->create([
            'product_id' => $accountingProduct->id,
            'name' => 'Accounting Pro',
            'slug' => 'accounting-pro-' . uniqid(),
            'description' => 'Accounting only plan',
            'price' => 299,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => 'price_' . uniqid(),
        ]);

        $response = $this->actingAs($user, 'web')->get(route('automotive.portal'));

        $response->assertOk();
        $response->assertDontSee($automotivePlan->name, false);
        $response->assertDontSee($accountingPlan->name, false);
        $response->assertSee('Products Catalog', false);
        $response->assertSee('Automotive Service Management', false);
        $response->assertSee('Accounting System', false);
        $response->assertSee('AVAILABLE NOW', false);
        $response->assertSee('Browse Product Plans', false);
        $response->assertDontSee('Product Subscription Options', false);
        $response->assertDontSee('No product is focused yet.', false);
        $response->assertSee('Choose Product', false);
    }

    public function test_portal_returns_to_neutral_state_after_opening_base_route_without_explicit_product(): void
    {
        $user = User::query()->create([
            'name' => 'Portal Remembered Product User',
            'email' => 'portal-remembered-product-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        CustomerOnboardingProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Portal Remembered Product Co',
            'subdomain' => 'portal-remembered-product-' . uniqid(),
            'base_host' => 'example.test',
        ]);

        $this->createPlan('Automotive Growth', 'automotive-growth-' . uniqid(), 'monthly', 399);

        $accountingProduct = Product::query()->create([
            'code' => 'accounting_' . uniqid(),
            'name' => 'Accounting System',
            'slug' => 'accounting-' . uniqid(),
            'is_active' => true,
        ]);

        $accountingPlan = Plan::query()->create([
            'product_id' => $accountingProduct->id,
            'name' => 'Accounting Pro',
            'slug' => 'accounting-pro-' . uniqid(),
            'description' => 'Accounting only plan',
            'price' => 299,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => 'price_' . uniqid(),
        ]);

        $this->actingAs($user, 'web')
            ->get(route('automotive.portal', ['product' => $accountingProduct->slug]))
            ->assertOk()
            ->assertSee($accountingPlan->name, false);

        $refreshResponse = $this->actingAs($user, 'web')->get(route('automotive.portal'));

        $refreshResponse->assertOk();
        $refreshResponse->assertDontSee($accountingPlan->name, false);
        $refreshResponse->assertDontSee('Product Subscription Options', false);
        $refreshResponse->assertSee('Choose Product', false);
    }

    public function test_portal_hides_coupon_badge_when_coupon_value_is_an_email(): void
    {
        $email = 'client_1@gmail.com';

        $user = User::query()->create([
            'name' => 'Portal Coupon User',
            'email' => $email,
            'password' => bcrypt('password'),
        ]);

        CustomerOnboardingProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Portal Coupon Co',
            'subdomain' => 'portal-coupon-' . uniqid(),
            'base_host' => 'example.test',
            'coupon_code' => $email,
        ]);

        $response = $this->actingAs($user, 'web')->get(route('automotive.portal'));

        $response->assertOk();
        $response->assertDontSee('Coupon Reserved:', false);
        $response->assertDontSee('Reserved Coupon:', false);
        $response->assertDontSee(strtoupper($email), false);
    }

    public function test_portal_can_focus_a_non_automotive_product_as_a_first_subscription(): void
    {
        $user = User::query()->create([
            'name' => 'Portal Product Focus User',
            'email' => 'portal-product-focus-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        CustomerOnboardingProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Portal Product Focus Co',
            'subdomain' => 'portal-product-focus-' . uniqid(),
            'base_host' => 'example.test',
        ]);

        $accountingProduct = Product::query()->create([
            'code' => 'accounting_focus_' . uniqid(),
            'name' => 'Accounting Suite',
            'slug' => 'accounting-suite-' . uniqid(),
            'description' => 'Shared accounting module',
            'is_active' => true,
        ]);

        $accountingPlan = Plan::query()->create([
            'product_id' => $accountingProduct->id,
            'name' => 'Accounting Pro',
            'slug' => 'accounting-pro-' . uniqid(),
            'description' => 'Accounting direct plan',
            'price' => 299,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => 'price_' . uniqid(),
        ]);

        ProductCapability::query()->create([
            'product_id' => $accountingProduct->id,
            'code' => 'general_ledger',
            'name' => 'General Ledger',
            'slug' => 'general-ledger',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Plan::query()->create([
            'product_id' => $accountingProduct->id,
            'name' => 'Accounting Trial',
            'slug' => 'accounting-trial-' . uniqid(),
            'description' => 'Accounting trial plan',
            'price' => 0,
            'currency' => 'USD',
            'billing_period' => 'trial',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'web')->get(route('automotive.portal', ['product' => $accountingProduct->slug]));

        $response->assertOk();
        $response->assertSee('Product Subscription Options', false);
        $response->assertSee('Included Product Capabilities', false);
        $response->assertSee('General Ledger', false);
        $response->assertSee((string) $accountingPlan->name, false);
        $response->assertSee('Select &amp; Continue', false);
        $response->assertSee('Start Accounting Suite Free Trial', false);
    }

    public function test_non_automotive_product_card_links_to_product_specific_enablement_panel(): void
    {
        $user = User::query()->create([
            'name' => 'Portal Product Card User',
            'email' => 'portal-product-card-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        CustomerOnboardingProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Portal Product Card Co',
            'subdomain' => 'portal-product-card-' . uniqid(),
            'base_host' => 'example.test',
        ]);

        $otherProduct = Product::query()->create([
            'code' => 'inventory_focus_' . uniqid(),
            'name' => 'Inventory Control',
            'slug' => 'inventory-control-' . uniqid(),
            'description' => 'Shared inventory module',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'web')->get(route('automotive.portal'));

        $response->assertOk();
        $response->assertSee('Explore Enablement', false);
        $response->assertSee(route('automotive.portal', ['product' => $otherProduct->slug]) . '#paid-plans', false);
    }

    public function test_workspace_additional_product_card_uses_browse_plans_cta_when_paid_plans_exist(): void
    {
        $user = User::query()->create([
            'name' => 'Portal Workspace Product Card User',
            'email' => 'portal-workspace-product-card-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        CustomerOnboardingProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Portal Workspace Product Card Co',
            'subdomain' => 'portal-workspace-product-card-' . uniqid(),
            'base_host' => 'example.test',
        ]);

        $tenant = Tenant::query()->create([
            'id' => 'tenant-workspace-product-card-' . uniqid(),
            'data' => ['company_name' => 'Portal Workspace Product Card Co'],
        ]);

        DB::table('tenant_users')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $accountingProduct = Product::query()->create([
            'code' => 'accounting_portal_card_' . uniqid(),
            'name' => 'Accounting',
            'slug' => 'accounting-portal-card-' . uniqid(),
            'description' => 'Accounting product',
            'is_active' => true,
        ]);

        $accountingPlan = Plan::query()->create([
            'product_id' => $accountingProduct->id,
            'name' => 'Accounting Growth',
            'slug' => 'accounting-growth-' . uniqid(),
            'description' => 'Accounting paid plan',
            'price' => 399,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => 'price_' . uniqid(),
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $accountingProduct->id,
            'plan_id' => $accountingPlan->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_workspace_product_card',
            'gateway_subscription_id' => 'sub_workspace_product_card',
            'gateway_price_id' => $accountingPlan->stripe_price_id,
        ]);

        $partsProduct = Product::query()->create([
            'code' => 'parts_inventory_' . uniqid(),
            'name' => 'Parts Inventory Management',
            'slug' => 'parts-inventory-' . uniqid(),
            'description' => 'Standalone parts product',
            'is_active' => true,
        ]);

        Plan::query()->create([
            'product_id' => $partsProduct->id,
            'name' => 'Parts Starter',
            'slug' => 'parts-starter-' . uniqid(),
            'description' => 'Inventory paid plan',
            'price' => 149,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => 'price_' . uniqid(),
        ]);

        $response = $this->actingAs($user, 'web')->get(route('automotive.portal'));

        $response->assertOk();
        $response->assertSee((string) $partsProduct->name, false);
        $response->assertSee('Browse Product Plans', false);
        $response->assertSee(route('automotive.portal', ['product' => $partsProduct->slug]) . '#paid-plans', false);
    }

    public function test_canonical_parts_product_keeps_browse_plans_cta_after_another_product_is_subscribed(): void
    {
        $user = User::query()->create([
            'name' => 'Portal Canonical Parts User',
            'email' => 'portal-canonical-parts-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        CustomerOnboardingProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Portal Canonical Parts Co',
            'subdomain' => 'portal-canonical-parts-' . uniqid(),
            'base_host' => 'example.test',
        ]);

        $tenant = Tenant::query()->create([
            'id' => 'tenant-canonical-parts-' . uniqid(),
            'data' => ['company_name' => 'Portal Canonical Parts Co'],
        ]);

        DB::table('tenant_users')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $accountingProduct = Product::query()->create([
            'code' => 'accounting',
            'name' => 'Accounting System',
            'slug' => 'accounting',
            'description' => 'Accounting product',
            'is_active' => true,
        ]);

        $accountingPlan = Plan::query()->create([
            'product_id' => $accountingProduct->id,
            'name' => 'Accounting Growth',
            'slug' => 'accounting-growth-' . uniqid(),
            'description' => 'Accounting paid plan',
            'price' => 399,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => 'price_' . uniqid(),
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $accountingProduct->id,
            'plan_id' => $accountingPlan->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_canonical_parts',
            'gateway_subscription_id' => 'sub_canonical_parts',
            'gateway_price_id' => $accountingPlan->stripe_price_id,
        ]);

        $partsProduct = Product::query()->create([
            'code' => 'parts_inventory',
            'name' => 'Parts Inventory Management',
            'slug' => 'parts-inventory',
            'description' => 'Canonical parts product',
            'is_active' => true,
        ]);

        Plan::query()->create([
            'product_id' => $partsProduct->id,
            'name' => 'Parts Starter',
            'slug' => 'canonical-parts-starter-' . uniqid(),
            'description' => 'Parts paid plan',
            'price' => 149,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => 'price_' . uniqid(),
        ]);

        $response = $this->actingAs($user, 'web')->get(route('automotive.portal'));

        $response->assertOk();
        $response->assertSee('Parts Inventory Management', false);
        $response->assertSee('Browse Product Plans', false);
        $response->assertSee(route('automotive.portal', ['product' => 'parts-inventory']) . '#paid-plans', false);
    }

    public function test_direct_billed_additional_product_shows_checkout_instead_of_approval_required(): void
    {
        $user = User::query()->create([
            'name' => 'Portal Direct Billed Additional User',
            'email' => 'portal-direct-billed-additional-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        CustomerOnboardingProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Portal Direct Billed Additional Co',
            'subdomain' => 'portal-direct-billed-additional-' . uniqid(),
            'base_host' => 'example.test',
        ]);

        $tenant = Tenant::query()->create([
            'id' => 'tenant-direct-billed-additional-' . uniqid(),
            'data' => ['company_name' => 'Portal Direct Billed Additional Co'],
        ]);

        DB::table('tenant_users')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $accountingProduct = Product::query()->create([
            'code' => 'accounting_direct_billed_' . uniqid(),
            'name' => 'Accounting Direct Billed',
            'slug' => 'accounting-direct-billed-' . uniqid(),
            'description' => 'Accounting product',
            'is_active' => true,
        ]);

        $accountingPlan = Plan::query()->create([
            'product_id' => $accountingProduct->id,
            'name' => 'Accounting Growth',
            'slug' => 'accounting-growth-' . uniqid(),
            'description' => 'Accounting paid plan',
            'price' => 399,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => 'price_' . uniqid(),
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $accountingProduct->id,
            'plan_id' => $accountingPlan->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_direct_billed_additional',
            'gateway_subscription_id' => 'sub_direct_billed_additional',
            'gateway_price_id' => $accountingPlan->stripe_price_id,
        ]);

        $partsProduct = Product::query()->create([
            'code' => 'parts_inventory',
            'name' => 'Parts Inventory Management',
            'slug' => 'parts-inventory',
            'description' => 'Canonical parts product',
            'is_active' => true,
        ]);

        Plan::query()->create([
            'product_id' => $partsProduct->id,
            'name' => 'Parts Starter',
            'slug' => 'direct-parts-starter-' . uniqid(),
            'description' => 'Parts paid plan',
            'price' => 149,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => 'price_' . uniqid(),
        ]);

        $response = $this->actingAs($user, 'web')->get(route('automotive.portal', ['product' => 'parts-inventory']));

        $response->assertOk();
        $response->assertSee('Product Subscription Options', false);
        $response->assertSee('Parts Starter', false);
        $response->assertSee('Select &amp; Continue', false);
        $response->assertDontSee('Approval Required Before Checkout', false);
        $response->assertDontSee('Submit or review enablement first.', false);
        $response->assertDontSee('This workspace product already has a live Stripe subscription.', false);
    }

    public function test_automotive_plans_are_not_blocked_by_live_subscription_on_another_product(): void
    {
        $user = User::query()->create([
            'name' => 'Portal Automotive Product Focus User',
            'email' => 'portal-automotive-product-focus-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        CustomerOnboardingProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Portal Automotive Product Focus Co',
            'subdomain' => 'portal-automotive-product-focus-' . uniqid(),
            'base_host' => 'example.test',
        ]);

        $tenant = Tenant::query()->create([
            'id' => 'tenant-automotive-product-focus-' . uniqid(),
            'data' => ['company_name' => 'Portal Automotive Product Focus Co'],
        ]);

        DB::table('tenant_users')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $automotiveProduct = Product::query()->where('code', 'automotive_service')->firstOrFail();
        $automotivePlan = Plan::query()->create([
            'product_id' => $automotiveProduct->id,
            'name' => 'Automotive Growth',
            'slug' => 'automotive-growth-' . uniqid(),
            'description' => 'Automotive paid plan',
            'price' => 399,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => 'price_' . uniqid(),
        ]);

        $accountingProduct = Product::query()->create([
            'code' => 'accounting_live_' . uniqid(),
            'name' => 'Accounting Live',
            'slug' => 'accounting-live-' . uniqid(),
            'description' => 'Accounting product',
            'is_active' => true,
        ]);

        $accountingPlan = Plan::query()->create([
            'product_id' => $accountingProduct->id,
            'name' => 'Accounting Growth',
            'slug' => 'accounting-growth-' . uniqid(),
            'description' => 'Accounting paid plan',
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
            'plan_id' => $accountingPlan->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_accounting_live_product',
            'gateway_subscription_id' => 'sub_accounting_live_product',
            'gateway_price_id' => $accountingPlan->stripe_price_id,
        ]);

        $response = $this->actingAs($user, 'web')->get(route('automotive.portal', [
            'product' => $automotiveProduct->slug,
        ]));

        $response->assertOk();
        $response->assertDontSee('This account already has a live Stripe subscription.', false);
        $response->assertDontSee('This workspace product already has a live Stripe subscription.', false);
        $response->assertSee('Automotive Growth', false);
        $response->assertSee('Select &amp; Continue', false);
        $response->assertDontSee('Billing Managed In System', false);
    }

    public function test_user_can_request_enablement_for_non_automotive_product_after_workspace_exists(): void
    {
        $user = User::query()->create([
            'name' => 'Portal Enablement User',
            'email' => 'portal-enable-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        CustomerOnboardingProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Portal Enablement Co',
            'subdomain' => 'portal-enable-' . uniqid(),
            'base_host' => 'example.test',
        ]);

        $tenant = Tenant::query()->create([
            'id' => 'tenant-enable-request-' . uniqid(),
            'data' => ['company_name' => 'Portal Enablement Co'],
        ]);

        DB::table('tenant_users')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $product = Product::query()->create([
            'code' => 'accounting_request_' . uniqid(),
            'name' => 'Accounting Enablement',
            'slug' => 'accounting-enable-' . uniqid(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'web')
            ->withSession(['_token' => 'test-token'])
            ->post(route('automotive.portal.products.request-enable'), [
                '_token' => 'test-token',
                'product_id' => $product->id,
            ]);

        $response->assertRedirect(route('automotive.portal', ['product' => $product->slug]));
        $response->assertSessionHas('success', 'Your product enablement request was submitted successfully.');

        $this->assertDatabaseHas('product_enablement_requests', [
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('admin_notifications', [
            'type' => 'product_enablement_request',
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'severity' => 'info',
        ]);

        $notification = AdminNotification::query()
            ->where('source_id', ProductEnablementRequest::query()->value('id'))
            ->latest('id')
            ->first();

        $this->assertNotNull($notification);
        $this->assertSame('admin.product-enablement-requests.index', $notification->route_name);
        $this->assertSame([
            'status' => 'pending',
            'product_id' => $product->id,
            'q' => $tenant->id,
        ], $notification->route_params);
    }

    public function test_enablement_request_is_blocked_before_primary_workspace_exists(): void
    {
        $user = User::query()->create([
            'name' => 'Portal Enablement Blocked User',
            'email' => 'portal-enable-blocked-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        CustomerOnboardingProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Portal Enablement Blocked Co',
            'subdomain' => 'portal-enable-blocked-' . uniqid(),
            'base_host' => 'example.test',
        ]);

        $product = Product::query()->create([
            'code' => 'inventory_request_' . uniqid(),
            'name' => 'Inventory Request',
            'slug' => 'inventory-request-' . uniqid(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'web')
            ->withSession(['_token' => 'test-token'])
            ->post(route('automotive.portal.products.request-enable'), [
                '_token' => 'test-token',
                'product_id' => $product->id,
            ]);

        $response->assertRedirect(route('automotive.portal', ['product' => $product->slug]));
        $response->assertSessionHasErrors([
            'portal' => 'Start your first workspace product before requesting additional product enablement.',
        ]);

        $this->assertDatabaseMissing('product_enablement_requests', [
            'product_id' => $product->id,
        ]);
    }

    public function test_non_automotive_enablement_panel_shows_pending_request_state(): void
    {
        $user = User::query()->create([
            'name' => 'Portal Enablement Pending User',
            'email' => 'portal-enable-pending-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        CustomerOnboardingProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Portal Enablement Pending Co',
            'subdomain' => 'portal-enable-pending-' . uniqid(),
            'base_host' => 'example.test',
        ]);

        $tenant = Tenant::query()->create([
            'id' => 'tenant-enable-pending-' . uniqid(),
            'data' => ['company_name' => 'Portal Enablement Pending Co'],
        ]);

        DB::table('tenant_users')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $product = Product::query()->create([
            'code' => 'accounting_pending_' . uniqid(),
            'name' => 'Accounting Pending',
            'slug' => 'accounting-pending-' . uniqid(),
            'is_active' => true,
        ]);

        ProductEnablementRequest::query()->create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $response = $this->actingAs($user, 'web')->get(route('automotive.portal', ['product' => $product->slug]));

        $response->assertOk();
        $response->assertSee('Enablement Request Pending', false);
    }

    public function test_portal_shows_customer_notification_when_enablement_request_is_approved(): void
    {
        $user = User::query()->create([
            'name' => 'Portal Notification User',
            'email' => 'portal-notification-approved-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        CustomerOnboardingProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Portal Notification Co',
            'subdomain' => 'portal-notification-' . uniqid(),
            'base_host' => 'example.test',
        ]);

        $tenant = Tenant::query()->create([
            'id' => 'tenant-portal-notification-' . uniqid(),
            'data' => ['company_name' => 'Portal Notification Co'],
        ]);

        DB::table('tenant_users')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $product = Product::query()->create([
            'code' => 'accounting_notification_' . uniqid(),
            'name' => 'Accounting Notification',
            'slug' => 'accounting-notification-' . uniqid(),
            'is_active' => true,
        ]);

        CustomerPortalNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'product_enablement_request',
            'title' => 'Product enablement approved',
            'message' => 'Accounting Notification is now available in your workspace.',
            'severity' => 'success',
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'notified_at' => now(),
        ]);

        $response = $this->actingAs($user, 'web')->get(route('automotive.portal', ['product' => $product->slug]));

        $response->assertOk();
        $response->assertSee('Product enablement approved', false);
        $response->assertSee('Accounting Notification is now available in your workspace.', false);
    }

    public function test_user_can_start_paid_checkout_for_approved_additional_product(): void
    {
        $user = User::query()->create([
            'name' => 'Portal Additional Checkout User',
            'email' => 'portal-additional-checkout-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        CustomerOnboardingProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Portal Additional Checkout Co',
            'subdomain' => 'portal-additional-checkout-' . uniqid(),
            'base_host' => 'example.test',
        ]);

        $tenant = Tenant::query()->create([
            'id' => 'tenant-additional-checkout-' . uniqid(),
            'data' => ['company_name' => 'Portal Additional Checkout Co'],
        ]);

        DB::table('tenant_users')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $product = Product::query()->create([
            'code' => 'accounting_checkout_' . uniqid(),
            'name' => 'Accounting Checkout',
            'slug' => 'accounting-checkout-' . uniqid(),
            'is_active' => true,
        ]);

        $plan = $this->createPlan('Accounting Growth', 'accounting-growth-' . uniqid(), 'monthly', 299, $product->id);

        ProductEnablementRequest::query()->create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'status' => 'approved',
            'requested_at' => now()->subDay(),
            'approved_at' => now()->subHour(),
        ]);

        $productSubscription = TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'status' => 'active',
            'payment_failures_count' => 0,
        ]);

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('createRenewalSession')
            ->once()
            ->withArgs(function ($payload) use ($tenant, $productSubscription, $plan, $product) {
                return ($payload['tenant_id'] ?? null) === $tenant->id
                    && ($payload['tenant_product_subscription_id'] ?? null) === $productSubscription->id
                    && ($payload['plan_id'] ?? null) === $plan->id
                    && ($payload['product_scope'] ?? null) === $product->code;
            })
            ->andReturn([
                'success' => true,
                'checkout_url' => 'https://checkout.stripe.test/session/additional-product',
                'session_id' => 'cs_additional_product_new',
            ]);

        $manager = Mockery::mock(PaymentGatewayManager::class);
        $manager->shouldReceive('driver')->once()->with('stripe')->andReturn($gateway);
        $this->app->instance(PaymentGatewayManager::class, $manager);

        $response = $this->actingAs($user, 'web')
            ->withSession(['_token' => 'test-token'])
            ->post(route('automotive.portal.subscribe'), [
                '_token' => 'test-token',
                'plan_id' => $plan->id,
                'product_id' => $product->id,
            ]);

        $response->assertRedirect('https://checkout.stripe.test/session/additional-product');

        $this->assertDatabaseHas('tenant_product_subscriptions', [
            'id' => $productSubscription->id,
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'gateway_checkout_session_id' => 'cs_additional_product_new',
            'gateway_price_id' => $plan->stripe_price_id,
            'gateway' => 'stripe',
            'status' => 'past_due',
        ]);
    }

    public function test_user_can_start_paid_checkout_for_direct_billed_additional_product_without_enablement_approval(): void
    {
        $user = User::query()->create([
            'name' => 'Portal Direct Checkout User',
            'email' => 'portal-direct-checkout-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        CustomerOnboardingProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Portal Direct Checkout Co',
            'subdomain' => 'portal-direct-checkout-' . uniqid(),
            'base_host' => 'example.test',
        ]);

        $tenant = Tenant::query()->create([
            'id' => 'tenant-direct-checkout-' . uniqid(),
            'data' => ['company_name' => 'Portal Direct Checkout Co'],
        ]);

        DB::table('tenant_users')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $product = Product::query()->create([
            'code' => 'parts_inventory',
            'name' => 'Parts Inventory Management',
            'slug' => 'parts-inventory',
            'is_active' => true,
        ]);

        $plan = $this->createPlan('Parts Growth', 'parts-growth-' . uniqid(), 'monthly', 299, $product->id);

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('createRenewalSession')
            ->once()
            ->withArgs(function ($payload) use ($tenant, $product, $plan) {
                return ($payload['tenant_id'] ?? null) === $tenant->id
                    && ($payload['plan_id'] ?? null) === $plan->id
                    && ($payload['product_scope'] ?? null) === $product->code;
            })
            ->andReturn([
                'success' => true,
                'checkout_url' => 'https://checkout.stripe.test/session/direct-additional-product',
                'session_id' => 'cs_direct_additional_product_new',
            ]);

        $manager = Mockery::mock(PaymentGatewayManager::class);
        $manager->shouldReceive('driver')->once()->with('stripe')->andReturn($gateway);
        $this->app->instance(PaymentGatewayManager::class, $manager);

        $response = $this->actingAs($user, 'web')
            ->withSession(['_token' => 'test-token'])
            ->post(route('automotive.portal.subscribe'), [
                '_token' => 'test-token',
                'plan_id' => $plan->id,
                'product_id' => $product->id,
            ]);

        $response->assertRedirect('https://checkout.stripe.test/session/direct-additional-product');

        $this->assertDatabaseHas('tenant_product_subscriptions', [
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'gateway_checkout_session_id' => 'cs_direct_additional_product_new',
            'gateway_price_id' => $plan->stripe_price_id,
            'gateway' => 'stripe',
            'status' => 'past_due',
        ]);
    }

    public function test_approved_additional_product_with_pending_checkout_shows_resume_message(): void
    {
        $user = User::query()->create([
            'name' => 'Portal Pending Additional Checkout User',
            'email' => 'portal-pending-additional-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        CustomerOnboardingProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Portal Pending Additional Co',
            'subdomain' => 'portal-pending-additional-' . uniqid(),
            'base_host' => 'example.test',
        ]);

        $tenant = Tenant::query()->create([
            'id' => 'tenant-pending-additional-' . uniqid(),
            'data' => ['company_name' => 'Portal Pending Additional Co'],
        ]);

        DB::table('tenant_users')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $product = Product::query()->create([
            'code' => 'accounting_pending_checkout_' . uniqid(),
            'name' => 'Accounting Pending Checkout',
            'slug' => 'accounting-pending-checkout-' . uniqid(),
            'is_active' => true,
        ]);

        $plan = $this->createPlan('Accounting Pending Plan', 'accounting-pending-plan-' . uniqid(), 'monthly', 299, $product->id);

        ProductEnablementRequest::query()->create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'status' => 'approved',
            'requested_at' => now()->subDay(),
            'approved_at' => now()->subHour(),
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'status' => 'past_due',
            'gateway' => 'stripe',
            'gateway_checkout_session_id' => 'cs_pending_additional_only',
            'gateway_subscription_id' => null,
        ]);

        $response = $this->actingAs($user, 'web')->get(route('automotive.portal', ['product' => $product->slug]));

        $response->assertOk();
        $response->assertSee('is still pending', false);
        $response->assertSee('Continue Product Checkout', false);
    }

    public function test_approved_additional_product_with_live_billing_shows_managed_in_system_message(): void
    {
        $user = User::query()->create([
            'name' => 'Portal Live Additional Billing User',
            'email' => 'portal-live-additional-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        CustomerOnboardingProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Portal Live Additional Co',
            'subdomain' => 'portal-live-additional-' . uniqid(),
            'base_host' => 'example.test',
        ]);

        $tenant = Tenant::query()->create([
            'id' => 'tenant-live-additional-' . uniqid(),
            'data' => ['company_name' => 'Portal Live Additional Co'],
        ]);

        DB::table('tenant_users')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $product = Product::query()->create([
            'code' => 'accounting_live_billing_' . uniqid(),
            'name' => 'Accounting Live Billing',
            'slug' => 'accounting-live-billing-' . uniqid(),
            'is_active' => true,
        ]);

        $plan = $this->createPlan('Accounting Live Plan', 'accounting-live-plan-' . uniqid(), 'monthly', 299, $product->id);

        ProductEnablementRequest::query()->create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'status' => 'approved',
            'requested_at' => now()->subDay(),
            'approved_at' => now()->subHour(),
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_checkout_session_id' => 'cs_live_additional_only',
            'gateway_subscription_id' => 'sub_live_additional_only',
        ]);

        $response = $this->actingAs($user, 'web')->get(route('automotive.portal', ['product' => $product->slug]));

        $response->assertOk();
        $response->assertSee('This workspace product already has a live Stripe subscription.', false);
        $response->assertDontSee('Billing Managed In System', false);
        $response->assertDontSee('Select &amp; Continue', false);
    }

    public function test_rejected_non_automotive_enablement_request_can_be_requested_again(): void
    {
        $user = User::query()->create([
            'name' => 'Portal Enablement Rejected User',
            'email' => 'portal-enable-rejected-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        CustomerOnboardingProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Portal Enablement Rejected Co',
            'subdomain' => 'portal-enable-rejected-' . uniqid(),
            'base_host' => 'example.test',
        ]);

        $tenant = Tenant::query()->create([
            'id' => 'tenant-enable-rejected-' . uniqid(),
            'data' => ['company_name' => 'Portal Enablement Rejected Co'],
        ]);

        DB::table('tenant_users')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $product = Product::query()->create([
            'code' => 'accounting_rejected_' . uniqid(),
            'name' => 'Accounting Rejected',
            'slug' => 'accounting-rejected-' . uniqid(),
            'is_active' => true,
        ]);

        ProductEnablementRequest::query()->create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'status' => 'rejected',
            'requested_at' => now(),
            'rejected_at' => now(),
        ]);

        $response = $this->actingAs($user, 'web')->get(route('automotive.portal', ['product' => $product->slug]));

        $response->assertOk();
        $response->assertSee('was rejected', false);
        $response->assertSee('Request Product Enablement Again', false);
        $response->assertDontSee('Enablement Request Pending', false);
    }

    public function test_inactive_non_automotive_product_shows_coming_soon_instead_of_request_button(): void
    {
        $user = User::query()->create([
            'name' => 'Portal Inactive Product User',
            'email' => 'portal-inactive-product-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        CustomerOnboardingProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Portal Inactive Product Co',
            'subdomain' => 'portal-inactive-product-' . uniqid(),
            'base_host' => 'example.test',
        ]);

        $product = Product::query()->create([
            'code' => 'inactive_module_' . uniqid(),
            'name' => 'Inactive Module',
            'slug' => 'inactive-module-' . uniqid(),
            'is_active' => false,
        ]);

        $response = $this->actingAs($user, 'web')->get(route('automotive.portal', ['product' => $product->slug]));

        $response->assertOk();
        $response->assertSee('Product Coming Soon', false);
        $response->assertDontSee('Request Product Enablement', false);
    }

    public function test_terminal_cancelled_stripe_subscription_does_not_block_new_paid_checkout_in_portal(): void
    {
        $user = User::query()->create([
            'name' => 'Portal User',
            'email' => 'portal-user-terminal-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        CustomerOnboardingProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Portal Co',
            'subdomain' => 'portal-terminal-' . uniqid(),
            'base_host' => 'example.test',
        ]);

        $oldPlan = $this->createPlan('Growth', 'growth-plan-' . uniqid(), 'monthly', 399);
        $newPlan = $this->createPlan('Scale', 'scale-plan-' . uniqid(), 'monthly', 599);

        $tenant = Tenant::query()->create([
            'id' => 'tenant-portal-terminal-' . uniqid(),
            'data' => [
                'company_name' => 'Portal Co',
                'owner_email' => $user->email,
            ],
        ]);

        DB::table('tenant_users')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $oldPlan->id,
            'status' => 'canceled',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_terminal_only',
            'gateway_subscription_id' => 'sub_terminal_only',
            'gateway_checkout_session_id' => 'cs_terminal_only',
            'gateway_price_id' => $oldPlan->stripe_price_id,
            'cancelled_at' => now()->subDay(),
            'ends_at' => now()->subMinute(),
        ]);

        $automotiveProduct = Product::query()->where('code', 'automotive_service')->firstOrFail();

        $response = $this->actingAs($user, 'web')->get(route('automotive.portal', [
            'product' => $automotiveProduct->slug,
        ]));

        $response->assertOk();
        $response->assertDontSee('This account already has a live Stripe subscription.', false);
        $response->assertDontSee('Billing Managed In System', false);
        $response->assertSee('Select &amp; Continue', false);
        $response->assertSee((string) $newPlan->name, false);
    }

    public function test_expired_subscription_portal_message_invites_new_checkout(): void
    {
        $user = User::query()->create([
            'name' => 'Expired Portal User',
            'email' => 'portal-expired-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        CustomerOnboardingProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Expired Portal Co',
            'subdomain' => 'portal-expired-' . uniqid(),
            'base_host' => 'example.test',
        ]);

        $plan = $this->createPlan('Growth', 'growth-expired-' . uniqid(), 'monthly', 399);
        $tenant = Tenant::query()->create([
            'id' => 'tenant-portal-expired-' . uniqid(),
            'data' => [
                'company_name' => 'Expired Portal Co',
                'owner_email' => $user->email,
            ],
        ]);

        DB::table('tenant_users')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'expired',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_expired_only',
            'gateway_checkout_session_id' => 'cs_expired_only',
            'gateway_subscription_id' => 'sub_expired_only',
            'gateway_price_id' => $plan->stripe_price_id,
            'cancelled_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($user, 'web')->get(route('automotive.portal'));

        $response->assertOk();
        $response->assertSee('Your previous subscription is', false);
        $response->assertSee('You can choose a paid plan below to start a new Stripe checkout.', false);
        $response->assertDontSee('Please review your plan and billing before opening the system workspace.', false);
    }

    public function test_restartable_terminal_subscription_uses_flat_plan_audit_payload_for_new_checkout(): void
    {
        $user = User::query()->create([
            'name' => 'Restart Portal User',
            'email' => 'portal-restart-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        $profile = CustomerOnboardingProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Restart Portal Co',
            'subdomain' => 'portal-restart-' . uniqid(),
            'base_host' => 'example.test',
        ]);

        $plan = $this->createPlan('Growth', 'growth-restart-' . uniqid(), 'monthly', 399);
        $tenant = Tenant::query()->create([
            'id' => 'tenant-portal-restart-' . uniqid(),
            'data' => [
                'company_name' => 'Restart Portal Co',
                'owner_email' => $user->email,
            ],
        ]);

        DB::table('tenant_users')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $subscription = Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'expired',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_restart_only',
            'gateway_checkout_session_id' => 'cs_restart_old',
            'gateway_subscription_id' => 'sub_restart_old',
            'gateway_price_id' => $plan->stripe_price_id,
            'cancelled_at' => now()->subDays(3),
            'ends_at' => now()->subDay(),
        ]);

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('createRenewalSession')
            ->once()
            ->with(Mockery::on(function (array $payload) use ($plan, $subscription): bool {
                $audit = $payload['plan_for_audit'] ?? [];

                return ($payload['subscription_row_id'] ?? null) === $subscription->id
                    && ($payload['stripe_price_id'] ?? null) === $plan->stripe_price_id
                    && is_array($audit)
                    && ($audit['id'] ?? null) === $plan->id
                    && ($audit['slug'] ?? null) === $plan->slug
                    && ((float) ($audit['price'] ?? 0) === (float) $plan->price)
                    && (($audit['currency'] ?? null) === 'USD')
                    && (($audit['billing_period'] ?? null) === 'monthly')
                    && (($audit['stripe_price_id'] ?? null) === $plan->stripe_price_id);
            }))
            ->andReturn([
                'success' => true,
                'checkout_url' => 'https://checkout.stripe.test/session/new',
                'session_id' => 'cs_restart_new',
            ]);

        $manager = Mockery::mock(PaymentGatewayManager::class);
        $manager->shouldReceive('driver')
            ->once()
            ->with('stripe')
            ->andReturn($gateway);

        $this->app->instance(PaymentGatewayManager::class, $manager);

        $result = app(StartPaidCheckoutService::class)->start($user, $profile, $plan->id);

        $subscription->refresh();

        $this->assertTrue($result['ok']);
        $this->assertSame('https://checkout.stripe.test/session/new', $result['checkout_url']);
        $this->assertSame('cs_restart_new', $subscription->gateway_checkout_session_id);
        $this->assertSame($plan->stripe_price_id, $subscription->gateway_price_id);
        $this->assertSame('past_due', $subscription->status);
        $this->assertNull($subscription->gateway_subscription_id);
        $this->assertNull($subscription->cancelled_at);
        $this->assertNull($subscription->ends_at);
    }

    public function test_paid_checkout_can_start_for_reserved_tenant_before_workspace_exists(): void
    {
        $user = User::query()->create([
            'name' => 'Reserved Portal User',
            'email' => 'portal-reserved-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        $profile = CustomerOnboardingProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Reserved Portal Co',
            'subdomain' => 'reserved-' . uniqid(),
            'base_host' => 'example.test',
        ]);

        $plan = $this->createPlan('Growth', 'growth-reserved-' . uniqid(), 'monthly', 399);

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('createRenewalSession')
            ->once()
            ->with(Mockery::on(function (array $payload) use ($plan, $profile, $user): bool {
                return ($payload['tenant_id'] ?? null) === $profile->subdomain
                    && ($payload['subscription_row_id'] ?? null) === null
                    && ($payload['plan_id'] ?? null) === $plan->id
                    && ($payload['stripe_price_id'] ?? null) === $plan->stripe_price_id
                    && ($payload['customer_email'] ?? null) === $user->email;
            }))
            ->andReturn([
                'success' => true,
                'checkout_url' => 'https://checkout.stripe.test/session/reserved',
                'session_id' => 'cs_reserved_new',
            ]);

        $manager = Mockery::mock(PaymentGatewayManager::class);
        $manager->shouldReceive('driver')
            ->once()
            ->with('stripe')
            ->andReturn($gateway);

        $this->app->instance(PaymentGatewayManager::class, $manager);

        $result = app(StartPaidCheckoutService::class)->start($user, $profile, $plan->id);

        $this->assertTrue($result['ok']);
        $this->assertSame('https://checkout.stripe.test/session/reserved', $result['checkout_url']);
        $this->assertSame($profile->subdomain, $result['tenant_id']);
        $this->assertNull($result['subscription_id']);
        $this->assertDatabaseMissing('subscriptions', [
            'tenant_id' => $profile->subdomain,
            'gateway_checkout_session_id' => 'cs_reserved_new',
        ]);
    }

    public function test_paid_checkout_can_start_for_a_non_automotive_first_product(): void
    {
        $user = User::query()->create([
            'name' => 'Wrong Product Portal User',
            'email' => 'portal-wrong-product-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        $profile = CustomerOnboardingProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Wrong Product Portal Co',
            'subdomain' => 'wrong-product-' . uniqid(),
            'base_host' => 'example.test',
        ]);

        $otherProduct = Product::query()->create([
            'code' => 'accounting_' . uniqid(),
            'name' => 'Accounting System',
            'slug' => 'accounting-' . uniqid(),
            'is_active' => true,
        ]);

        $plan = Plan::query()->create([
            'product_id' => $otherProduct->id,
            'name' => 'Accounting Pro',
            'slug' => 'accounting-pro-' . uniqid(),
            'description' => 'Accounting only plan',
            'price' => 299,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => 'price_' . uniqid(),
        ]);

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('createRenewalSession')
            ->once()
            ->withArgs(function (array $payload) use ($plan, $profile, $user, $otherProduct): bool {
                return ($payload['tenant_id'] ?? null) === $profile->subdomain
                    && ($payload['subscription_row_id'] ?? null) === null
                    && ($payload['plan_id'] ?? null) === $plan->id
                    && ($payload['stripe_price_id'] ?? null) === $plan->stripe_price_id
                    && ($payload['customer_email'] ?? null) === $user->email
                    && ($payload['product_scope'] ?? null) === $otherProduct->code;
            })
            ->andReturn([
                'success' => true,
                'checkout_url' => 'https://checkout.stripe.test/session/accounting-first',
                'session_id' => 'cs_accounting_first',
            ]);

        $manager = Mockery::mock(PaymentGatewayManager::class);
        $manager->shouldReceive('driver')
            ->once()
            ->with('stripe')
            ->andReturn($gateway);

        $this->app->instance(PaymentGatewayManager::class, $manager);

        $result = app(StartPaidCheckoutService::class)->start($user, $profile, $plan->id, $otherProduct->id);

        $this->assertTrue($result['ok']);
        $this->assertSame('https://checkout.stripe.test/session/accounting-first', $result['checkout_url']);
        $this->assertSame($profile->subdomain, $result['tenant_id']);
    }

    public function test_paid_checkout_updates_tenant_product_subscription_when_legacy_subscription_is_created(): void
    {
        $user = User::query()->create([
            'name' => 'Product Mirror User',
            'email' => 'portal-product-mirror-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        $profile = CustomerOnboardingProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Product Mirror Co',
            'subdomain' => 'product-mirror-' . uniqid(),
            'base_host' => 'example.test',
        ]);

        $plan = $this->createPlan('Mirror Growth', 'mirror-growth-' . uniqid(), 'monthly', 399);

        $tenant = Tenant::query()->create([
            'id' => $profile->subdomain,
            'data' => ['company_name' => 'Product Mirror Co'],
        ]);

        DB::table('tenant_users')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('createRenewalSession')
            ->once()
            ->andReturn([
                'success' => true,
                'checkout_url' => 'https://checkout.stripe.test/session/mirror',
                'session_id' => 'cs_mirror_new',
            ]);

        $manager = Mockery::mock(PaymentGatewayManager::class);
        $manager->shouldReceive('driver')->once()->with('stripe')->andReturn($gateway);

        $this->app->instance(PaymentGatewayManager::class, $manager);

        $result = app(StartPaidCheckoutService::class)->start($user, $profile, $plan->id);

        $this->assertTrue($result['ok']);
        $this->assertDatabaseHas('tenant_product_subscriptions', [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'gateway_checkout_session_id' => 'cs_mirror_new',
        ]);

        $productSubscription = TenantProductSubscription::query()
            ->where('tenant_id', $tenant->id)
            ->first();

        $this->assertNotNull($productSubscription);
        $this->assertNotNull($productSubscription->legacy_subscription_id);
    }

    public function test_portal_marks_automotive_product_as_subscribed_when_product_subscription_exists(): void
    {
        $user = User::query()->create([
            'name' => 'Subscribed Product User',
            'email' => 'portal-subscribed-product-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        CustomerOnboardingProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Subscribed Product Co',
            'subdomain' => 'subscribed-product-' . uniqid(),
            'base_host' => 'example.test',
        ]);

        $plan = $this->createPlan('Subscribed Growth', 'subscribed-growth-' . uniqid(), 'monthly', 399);
        $tenant = Tenant::query()->create([
            'id' => 'tenant-subscribed-product-' . uniqid(),
            'data' => ['company_name' => 'Subscribed Product Co'],
        ]);

        DB::table('tenant_users')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \App\Models\TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => Product::query()->where('code', 'automotive_service')->value('id'),
            'plan_id' => $plan->id,
            'status' => 'active',
            'payment_failures_count' => 0,
        ]);

        $response = $this->actingAs($user, 'web')->get(route('automotive.portal'));

        $response->assertOk();
        $response->assertSee('Products Catalog', false);
        $response->assertSee('Open Product Workspace', false);
        $response->assertSee('ACTIVE', false);
        $response->assertSee('This product is already attached to your workspace.', false);
    }

    protected function createPlan(string $name, string $slug, string $billingPeriod, int $price, ?int $productId = null): Plan
    {
        $productId = $productId ?: Product::query()->where('code', 'automotive_service')->value('id');

        return Plan::query()->create([
            'product_id' => $productId,
            'name' => $name,
            'slug' => $slug,
            'description' => $name . ' description',
            'price' => $price,
            'currency' => 'USD',
            'billing_period' => $billingPeriod,
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => 'price_' . uniqid(),
        ]);
    }
}
