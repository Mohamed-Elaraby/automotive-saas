<?php

namespace Tests\Feature\Automotive\Portal;

use App\Contracts\Billing\PaymentGatewayInterface;
use App\Models\AdminNotification;
use App\Models\BillingFeature;
use App\Models\CustomerOnboardingProfile;
use App\Models\CustomerPortalNotification;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductEnablementRequest;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\User;
use App\Services\Automotive\StartPaidCheckoutService;
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

        $response = $this->actingAs($user, 'web')->get(route('automotive.portal'));

        $response->assertOk();
        $response->assertSee('Select &amp; Continue', false);
        $response->assertDontSee('Billing Managed In System', false);
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

        $response = $this->actingAs($user, 'web')->get(route('automotive.portal'));

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

    public function test_portal_only_shows_plans_for_the_automotive_product(): void
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
        $response->assertSee($automotivePlan->name, false);
        $response->assertDontSee($accountingPlan->name, false);
        $response->assertSee('Products Catalog', false);
        $response->assertSee('Automotive Service Management', false);
        $response->assertSee('Accounting System', false);
        $response->assertSee('AVAILABLE NOW', false);
    }

    public function test_portal_can_focus_a_non_automotive_product_enablement_panel(): void
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
            'description' => 'Accounting enablement plan',
            'price' => 299,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => 'price_' . uniqid(),
        ]);

        $response = $this->actingAs($user, 'web')->get(route('automotive.portal', ['product' => $accountingProduct->slug]));

        $response->assertOk();
        $response->assertSee('Accounting Suite Plans &amp; Enablement', false);
        $response->assertSee('Billing checkout for additional products is intentionally not live yet in this portal.', false);
        $response->assertSee((string) $accountingPlan->name, false);
        $response->assertSee('Product Enablement Is Next', false);
        $response->assertDontSee('Select &amp; Continue', false);
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
            'portal' => 'Start your primary workspace first before requesting additional product enablement.',
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

        $response = $this->actingAs($user, 'web')->get(route('automotive.portal'));

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

    public function test_paid_checkout_rejects_plan_from_a_different_product(): void
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

        $result = app(StartPaidCheckoutService::class)->start($user, $profile, $plan->id);

        $this->assertFalse($result['ok']);
        $this->assertSame(422, $result['status']);
        $this->assertSame('The selected paid plan was not found or is not active.', $result['message']);
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
        $response->assertSee('Manage Automotive', false);
        $response->assertSee('ACTIVE', false);
        $response->assertSee('This product is already attached to your workspace.', false);
    }

    protected function createPlan(string $name, string $slug, string $billingPeriod, int $price): Plan
    {
        $productId = Product::query()->where('code', 'automotive_service')->value('id');

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
