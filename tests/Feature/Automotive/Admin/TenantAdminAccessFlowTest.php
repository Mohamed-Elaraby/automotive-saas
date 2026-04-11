<?php

namespace Tests\Feature\Automotive\Admin;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductCapability;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Database\Models\Domain;
use Tests\TestCase;

class TenantAdminAccessFlowTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    protected array $tenantDatabaseFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);
        Auth::guard('automotive_admin')->logout();
        $this->flushSession();

        Config::set('tenancy.database.template_tenant_connection', 'sqlite');
    }

    protected function tearDown(): void
    {
        try {
            if (function_exists('tenancy') && tenancy()->initialized) {
                tenancy()->end();
            }
        } catch (\Throwable) {
            //
        }

        foreach ($this->tenantDatabaseFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_active_tenant_admin_can_log_in_and_open_dashboard(): void
    {
        [$tenant, $domain, $email, $password] = $this->prepareTenantWorkspace('active');

        $response = $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $email,
            'password' => $password,
        ]);

        $response->assertRedirect("http://{$domain}/automotive/admin/dashboard");

        $dashboardResponse = $this->get("http://{$domain}/automotive/admin/dashboard");

        $dashboardResponse->assertOk();
        $dashboardResponse->assertSee('Dashboard', false);
        $dashboardResponse->assertSee('Workshop Operations', false);
        $dashboardResponse->assertDontSee('Inventory Adjustments', false);
        $dashboardResponse->assertDontSee('Stock Transfers', false);
        $dashboardResponse->assertDontSee('Inventory Report', false);

        $this->assertAuthenticated('automotive_admin');
    }

    public function test_suspended_tenant_admin_is_redirected_to_billing_after_login(): void
    {
        [, $domain, $email, $password] = $this->prepareTenantWorkspace('suspended');

        $loginResponse = $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $email,
            'password' => $password,
        ]);

        $loginResponse->assertRedirect("http://{$domain}/automotive/admin/dashboard");

        $dashboardResponse = $this->get("http://{$domain}/automotive/admin/dashboard");

        $dashboardResponse->assertRedirect("http://{$domain}/automotive/admin/billing");
    }

    public function test_dashboard_shows_all_workspace_products_for_the_same_tenant(): void
    {
        [$tenant, $domain, $email, $password] = $this->prepareTenantWorkspace('active');

        $accountingProduct = Product::query()->create([
            'code' => 'accounting_suite_' . uniqid(),
            'name' => 'Accounting Suite',
            'slug' => 'accounting-suite-' . uniqid(),
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $sparePartsProduct = Product::query()->create([
            'code' => 'spare_parts_' . uniqid(),
            'name' => 'Spare Parts',
            'slug' => 'spare-parts-' . uniqid(),
            'is_active' => true,
            'sort_order' => 3,
        ]);

        $accountingPlan = Plan::query()->create([
            'product_id' => $accountingProduct->id,
            'name' => 'Accounting Pro',
            'slug' => 'accounting-pro-' . uniqid(),
            'price' => 299,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
        ]);

        $sparePartsPlan = Plan::query()->create([
            'product_id' => $sparePartsProduct->id,
            'name' => 'Spare Parts Pro',
            'slug' => 'spare-parts-pro-' . uniqid(),
            'price' => 249,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $accountingProduct->id,
            'plan_id' => $accountingPlan->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_subscription_id' => 'sub_accounting_' . uniqid(),
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $sparePartsProduct->id,
            'plan_id' => $sparePartsPlan->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_subscription_id' => 'sub_spare_parts_' . uniqid(),
        ]);

        ProductCapability::query()->create([
            'product_id' => $accountingProduct->id,
            'code' => 'general_ledger',
            'name' => 'General Ledger',
            'slug' => 'general-ledger',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $email,
            'password' => $password,
        ])->assertRedirect("http://{$domain}/automotive/admin/dashboard");

        $dashboardResponse = $this->get("http://{$domain}/automotive/admin/dashboard");

        $dashboardResponse->assertOk();
        $dashboardResponse->assertSee('Workspace Products', false);
        $dashboardResponse->assertSee('Focused Workspace Product', false);
        $dashboardResponse->assertSee('Accounting Suite', false);
        $dashboardResponse->assertSee('Spare Parts', false);
        $dashboardResponse->assertSee('Connected', false);

        $focusedResponse = $this->get("http://{$domain}/automotive/admin/dashboard?workspace_product={$accountingProduct->code}");

        $focusedResponse->assertOk();
        $focusedResponse->assertSee('Focused Workspace Product', false);
        $focusedResponse->assertSee('Accounting Suite', false);
        $focusedResponse->assertSee('General Ledger', false);
        $focusedResponse->assertSee("workspace_product={$accountingProduct->code}", false);
    }

    public function test_parts_inventory_focus_shows_inventory_modules_and_routes_are_accessible(): void
    {
        [$tenant, $domain, $email, $password] = $this->prepareTenantWorkspace('active');

        $partsProduct = Product::query()->firstOrCreate(
            ['code' => 'parts_inventory'],
            [
                'name' => 'Spare Parts Inventory',
                'slug' => 'spare-parts-inventory',
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        $partsPlan = Plan::query()->create([
            'product_id' => $partsProduct->id,
            'name' => 'Parts Pro',
            'slug' => 'parts-pro-' . uniqid(),
            'price' => 149,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $partsProduct->id,
            'plan_id' => $partsPlan->id,
            'status' => 'active',
            'gateway' => null,
        ]);

        $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $email,
            'password' => $password,
        ])->assertRedirect("http://{$domain}/automotive/admin/dashboard");

        $dashboardResponse = $this->get("http://{$domain}/automotive/admin/dashboard?workspace_product=parts_inventory");

        $dashboardResponse->assertOk();
        $dashboardResponse->assertSee('Supplier Catalog', false);
        $dashboardResponse->assertSee('Inventory Adjustments', false);
        $dashboardResponse->assertSee('Stock Transfers', false);
        $dashboardResponse->assertSee('Inventory Report', false);
        $dashboardResponse->assertSee('Stock Movement Report', false);
        $dashboardResponse->assertDontSee('Open Workshop', false);

        $productsResponse = $this->get("http://{$domain}/automotive/admin/products");
        $productsResponse->assertOk();
        $productsResponse->assertSee('Stock Items', false);
    }

    public function test_parts_inventory_routes_are_blocked_when_tenant_does_not_have_that_product(): void
    {
        [, $domain, $email, $password] = $this->prepareTenantWorkspace('active');

        $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $email,
            'password' => $password,
        ])->assertRedirect("http://{$domain}/automotive/admin/dashboard");

        $response = $this->get("http://{$domain}/automotive/admin/products");

        $response->assertRedirect("http://{$domain}/automotive/admin/dashboard?workspace_product=parts_inventory");
    }

    /**
     * @return array{0: Tenant, 1: string, 2: string, 3: string}
     */
    protected function prepareTenantWorkspace(string $subscriptionStatus): array
    {
        $tenantId = 'tenant-flow-' . uniqid();
        $domain = $tenantId . '.example.test';
        $password = 'secret-pass';
        $email = $tenantId . '@example.test';

        $automotiveProduct = Product::query()->firstOrCreate(
            ['code' => 'automotive_service'],
            [
                'name' => 'Automotive Service Management',
                'slug' => 'automotive-service-management',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        $plan = Plan::query()->create([
            'product_id' => $automotiveProduct->id,
            'name' => 'Tenant Flow Plan',
            'slug' => 'tenant-flow-plan-' . uniqid(),
            'description' => 'Tenant flow plan',
            'price' => 99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => 'price_' . uniqid(),
        ]);

        $tenant = Tenant::query()->create([
            'id' => $tenantId,
            'data' => [
                'company_name' => 'Tenant Flow Co',
            ],
        ]);

        $this->tenantDatabaseFiles[] = database_path($tenant->database()->getName());

        Domain::query()->create([
            'domain' => $domain,
            'tenant_id' => $tenant->id,
        ]);

        Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => $subscriptionStatus,
            'gateway' => null,
            'gateway_customer_id' => null,
            'gateway_subscription_id' => null,
            'gateway_checkout_session_id' => null,
            'gateway_price_id' => null,
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $automotiveProduct->id,
            'plan_id' => $plan->id,
            'status' => $subscriptionStatus,
            'gateway' => null,
        ]);

        Artisan::call('tenants:migrate', [
            '--tenants' => [$tenant->id],
            '--force' => true,
        ]);

        tenancy()->initialize($tenant);

        try {
            User::query()->create([
                'name' => 'Tenant Owner',
                'email' => $email,
                'password' => bcrypt($password),
            ]);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        return [$tenant, $domain, $email, $password];
    }
}
