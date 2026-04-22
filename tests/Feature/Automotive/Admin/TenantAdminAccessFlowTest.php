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
use Illuminate\Support\Facades\Schema;
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

        $response->assertRedirect("http://{$domain}/workspace/admin/dashboard");

        $dashboardResponse = $this->get("http://{$domain}/automotive/admin/dashboard");

        $dashboardResponse->assertOk();
        $dashboardResponse->assertSee('Dashboard', false);
        $dashboardResponse->assertSee('Workshop Operations', false);
        $dashboardResponse->assertDontSee('Plans & Billing', false);
        $dashboardResponse->assertDontSee('Inventory Adjustments', false);
        $dashboardResponse->assertDontSee('Stock Transfers', false);
        $dashboardResponse->assertDontSee('Inventory Report', false);

        $this->assertAuthenticated('automotive_admin');
    }

    public function test_workspace_root_is_the_canonical_tenant_entry_and_legacy_login_route_still_works(): void
    {
        [, $domain, $email, $password] = $this->prepareTenantWorkspace('active');

        $workspaceRootResponse = $this->get("http://{$domain}/workspace");
        $workspaceRootResponse->assertRedirect("http://{$domain}/workspace/admin/login");

        $legacyLoginResponse = $this->get("http://{$domain}/automotive/admin/login");
        $legacyLoginResponse->assertOk();
        $legacyLoginResponse->assertSee('Login', false);
        $legacyLoginResponse->assertDontSee('Workspace Products', false);
        $legacyLoginResponse->assertDontSee('Logout', false);

        $loginResponse = $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $email,
            'password' => $password,
            ]);

        $loginResponse->assertRedirect("http://{$domain}/workspace/admin/dashboard");

        $workspaceRootAfterLogin = $this->get("http://{$domain}/workspace");
        $workspaceRootAfterLogin->assertRedirect("http://{$domain}/workspace/admin/dashboard");
    }

    public function test_suspended_tenant_admin_is_redirected_to_billing_after_login(): void
    {
        [, $domain, $email, $password] = $this->prepareTenantWorkspace('suspended');

        $loginResponse = $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $email,
            'password' => $password,
        ]);

        $loginResponse->assertRedirect("http://{$domain}/workspace/admin/dashboard");

        $dashboardResponse = $this->get("http://{$domain}/automotive/admin/dashboard");

        $dashboardResponse->assertRedirect("http://{$domain}/workspace/admin/billing");
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
        ])->assertRedirect("http://{$domain}/workspace/admin/dashboard");

        $dashboardResponse = $this->get("http://{$domain}/automotive/admin/dashboard");

        $dashboardResponse->assertOk();
        $dashboardResponse->assertSee('Workspace Products', false);
        $dashboardResponse->assertSee('Focused Workspace Product', false);
        $dashboardResponse->assertSee('Accounting Suite', false);
        $dashboardResponse->assertSee('Spare Parts', false);
        $dashboardResponse->assertSee('Connected', false);
        $dashboardResponse->assertSee('Cross-Product Integrations', false);
        $dashboardResponse->assertSee('Target: Spare Parts', false);
        $dashboardResponse->assertSee('Open Spare Parts', false);

        $focusedResponse = $this->get("http://{$domain}/automotive/admin/dashboard?workspace_product={$accountingProduct->code}");

        $focusedResponse->assertOk();
        $focusedResponse->assertSee('Focused Workspace Product', false);
        $focusedResponse->assertSee('Accounting Suite', false);
        $focusedResponse->assertSee('General Ledger', false);
        $focusedResponse->assertSee('Accounting Focus', false);
        $focusedResponse->assertSee('Cross-Product Integrations', false);
        $focusedResponse->assertSee('Accounting can receive service-side activity', false);
        $focusedResponse->assertSee("workspace_product={$accountingProduct->code}", false);
        $focusedResponse->assertSee('Shared Workspace', false);
        $focusedResponse->assertDontSee('Service Operations', false);
        $focusedResponse->assertDontSee('Inventory Adjustments', false);
        $focusedResponse->assertSee('Open Workshop', false);

        $generalLedgerResponse = $this->get("http://{$domain}/automotive/admin/general-ledger?workspace_product={$accountingProduct->code}");
        $generalLedgerResponse->assertOk();
        $generalLedgerResponse->assertSee('General Ledger', false);
        $generalLedgerResponse->assertSee('Connected Product Integrations', false);
        $generalLedgerResponse->assertSee('Open Workshop', false);
    }

    public function test_accounting_only_tenant_can_use_workspace_without_other_products(): void
    {
        [$tenant, $domain, $email, $password] = $this->prepareAccountingOnlyTenantWorkspace();

        $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $email,
            'password' => $password,
        ])->assertRedirect("http://{$domain}/workspace/admin/dashboard");

        $dashboardResponse = $this->get("http://{$domain}/automotive/admin/dashboard");

        $dashboardResponse->assertOk();
        $dashboardResponse->assertSee('Accounting System', false);
        $dashboardResponse->assertSee('Open General Ledger', false);
        $dashboardResponse->assertSee('General Ledger', false);
        $dashboardResponse->assertDontSee('Automotive Service Management', false);
        $dashboardResponse->assertDontSee('Parts Inventory Management', false);
        $dashboardResponse->assertDontSee('Open Workshop', false);
        $dashboardResponse->assertDontSee('Open Supplier Catalog', false);

        $ledgerResponse = $this->get("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting");

        $ledgerResponse->assertOk();
        $ledgerResponse->assertSee('General Ledger', false);
        $ledgerResponse->assertSee('Accounting Workspace Navigation', false);
        $ledgerResponse->assertSee('Create Manual Journal Entry', false);
        $ledgerResponse->assertDontSee('Recent Workshop Consumptions', false);

        $groupResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/posting-groups?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'code' => 'standalone_revenue',
            'name' => 'Standalone Revenue',
            'receivable_account' => '1100 Accounts Receivable',
            'labor_revenue_account' => '4100 Service Labor Revenue',
            'parts_revenue_account' => '4200 Parts Revenue',
            'currency' => 'USD',
            'is_default' => '1',
        ]);

        $groupResponse->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");

        $workshopResponse = $this->get("http://{$domain}/automotive/admin/workshop-operations?workspace_product=automotive_service");
        $workshopResponse->assertRedirect("http://{$domain}/workspace/admin/dashboard?workspace_product=automotive_service");

        $this->artisan("tenancy:verify-integration-readiness --tenant={$tenant->id}")
            ->expectsOutputToContain('Accounting workspace product')
            ->expectsOutputToContain('Optional integration products')
            ->expectsOutputToContain('Cross-product integration checks skipped for inactive workspace products: automotive_service, parts_inventory.')
            ->expectsOutput('Workspace integration readiness verification passed.')
            ->assertExitCode(0);
    }

    public function test_accounting_first_time_setup_wizard_configures_defaults_idempotently(): void
    {
        [$tenant, $domain, $email, $password] = $this->prepareAccountingOnlyTenantWorkspace();

        $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $email,
            'password' => $password,
        ])->assertRedirect("http://{$domain}/workspace/admin/dashboard");

        $ledgerResponse = $this->get("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting");
        $ledgerResponse->assertOk();
        $ledgerResponse->assertSee('First-Time Setup', false);
        $ledgerResponse->assertSee('Complete Accounting Setup', false);
        $ledgerResponse->assertSee('NEEDS SETUP', false);

        $payload = [
            'workspace_product' => 'accounting',
            'base_currency' => 'USD',
            'fiscal_year_start_month' => 1,
            'fiscal_year_start_day' => 1,
            'tax_mode' => 'vat_standard',
            'default_tax_rate' => 5,
            'chart_template' => 'service_business',
            'default_receivable_account' => '1100 Accounts Receivable',
            'default_payable_account' => '2000 Accounts Payable',
            'default_cash_account' => '1000 Cash On Hand',
            'default_bank_account' => '1010 Bank Account',
            'default_revenue_account' => '4100 Service Revenue',
            'default_expense_account' => '5200 Operating Expense',
            'default_input_tax_account' => '1410 VAT Input Receivable',
            'default_output_tax_account' => '2100 VAT Output Payable',
        ];

        $this->post("http://{$domain}/automotive/admin/general-ledger/first-time-setup?workspace_product=accounting", $payload)
            ->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");

        $this->post("http://{$domain}/automotive/admin/general-ledger/first-time-setup?workspace_product=accounting", $payload)
            ->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");

        tenancy()->initialize($tenant);

        try {
            $this->assertSame(1, DB::connection('tenant')->table('accounting_setup_profiles')->count());
            $this->assertDatabaseHas('accounting_setup_profiles', [
                'base_currency' => 'USD',
                'tax_mode' => 'vat_standard',
                'chart_template' => 'service_business',
                'default_receivable_account' => '1100 Accounts Receivable',
                'default_payable_account' => '2000 Accounts Payable',
            ], 'tenant');
            $this->assertSame(1, DB::connection('tenant')->table('accounting_posting_groups')->where('code', 'default_revenue')->count());
            $this->assertDatabaseHas('accounting_posting_groups', [
                'code' => 'default_revenue',
                'receivable_account' => '1100 Accounts Receivable',
                'labor_revenue_account' => '4100 Service Revenue',
                'parts_revenue_account' => '4100 Service Revenue',
                'is_default' => true,
            ], 'tenant');
            $this->assertDatabaseHas('accounting_bank_accounts', [
                'account_code' => '1000 Cash On Hand',
                'is_default_receipt' => true,
            ], 'tenant');
            $this->assertDatabaseHas('accounting_bank_accounts', [
                'account_code' => '1010 Bank Account',
                'is_default_payment' => true,
            ], 'tenant');
            $this->assertDatabaseHas('accounting_tax_rates', [
                'code' => 'vat_default',
                'rate' => 5,
                'is_default' => true,
            ], 'tenant');
            $this->assertDatabaseHas('accounting_policies', [
                'code' => 'default_inventory_policy',
                'is_default' => true,
            ], 'tenant');
            $this->assertDatabaseHas('accounting_audit_entries', [
                'event_type' => 'accounting_first_time_setup_completed',
            ], 'tenant');
            $this->assertSame(0, DB::connection('tenant')->table('journal_entries')->count());
            $this->assertSame(0, DB::connection('tenant')->table('journal_entry_lines')->count());
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $readyLedgerResponse = $this->get("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting");
        $readyLedgerResponse->assertOk();
        $readyLedgerResponse->assertSee('READY', false);
        $readyLedgerResponse->assertSee('Completed', false);
    }

    public function test_general_ledger_shows_simplified_command_center_for_accounting_users(): void
    {
        [, $domain, $email, $password] = $this->prepareAccountingOnlyTenantWorkspace();

        $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $email,
            'password' => $password,
        ])->assertRedirect("http://{$domain}/workspace/admin/dashboard");

        $response = $this->get("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting");

        $response->assertOk();
        $response->assertSee('Finance Command Center', false);
        $response->assertSee('Setup Needed', false);
        $response->assertSee('Work Queue', false);
        $response->assertSee('Money In', false);
        $response->assertSee('Money Out', false);
        $response->assertSee('Bank Review', false);
        $response->assertSee('Run Reports', false);
        $response->assertSee('href="#accounting-first-time-setup"', false);
        $response->assertSee('href="#accounting-posting-queue"', false);
        $response->assertSee('href="#accounting-receivables"', false);
        $response->assertSee('href="#accounting-payables"', false);
        $response->assertSee('href="#accounting-cash"', false);
        $response->assertSee('href="#accounting-reports"', false);
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
        ])->assertRedirect("http://{$domain}/workspace/admin/dashboard");

        $dashboardResponse = $this->get("http://{$domain}/automotive/admin/dashboard?workspace_product=parts_inventory");

        $dashboardResponse->assertOk();
        $dashboardResponse->assertSee('Supplier Catalog', false);
        $dashboardResponse->assertSee('Inventory Adjustments', false);
        $dashboardResponse->assertSee('Stock Transfers', false);
        $dashboardResponse->assertSee('Inventory Report', false);
        $dashboardResponse->assertSee('Stock Movement Report', false);
        $dashboardResponse->assertSee('Cross-Product Integrations', false);
        $dashboardResponse->assertSee('Spare parts feed workshop operations', false);
        $dashboardResponse->assertSee('Open Workshop', false);

        $productsResponse = $this->get("http://{$domain}/automotive/admin/products");
        $productsResponse->assertOk();
        $productsResponse->assertSee('Stock Items', false);
    }

    public function test_stock_items_use_tenant_spare_parts_not_central_saas_products(): void
    {
        [$tenant, $domain, $email, $password] = $this->prepareTenantWorkspace('active');
        $this->attachPartsWorkspaceToTenant($tenant);

        tenancy()->initialize($tenant);

        try {
            app(\Database\Seeders\TenantSparePartsDemoSeeder::class)->run();
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $email,
            'password' => $password,
        ])->assertRedirect("http://{$domain}/workspace/admin/dashboard");

        $response = $this->get("http://{$domain}/automotive/admin/products?workspace_product=parts_inventory");

        $response->assertOk();
        $response->assertSee('Engine Oil 5W-30', false);
        $response->assertSee('Oil Filter Toyota', false);
        $response->assertSee('Front Brake Pads Set', false);
        $response->assertSee('SP-OIL-5W30', false);
        $response->assertSee('SP-FLT-TOY-OIL', false);
        $response->assertDontSee('Accounting System', false);

        $adjustmentResponse = $this->get("http://{$domain}/automotive/admin/inventory-adjustments/create?workspace_product=parts_inventory");
        $adjustmentResponse->assertOk();
        $adjustmentResponse->assertSee('Engine Oil 5W-30', false);
        $adjustmentResponse->assertDontSee('Accounting System', false);
    }

    public function test_inventory_family_alias_product_can_drive_parts_workspace_focus(): void
    {
        [$tenant, $domain, $email, $password] = $this->prepareTenantWorkspace('active');

        $partsProduct = Product::query()->create([
            'code' => 'stock_hub_' . uniqid(),
            'name' => 'Inventory Hub',
            'slug' => 'inventory-hub-' . uniqid(),
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $partsPlan = Plan::query()->create([
            'product_id' => $partsProduct->id,
            'name' => 'Inventory Hub Pro',
            'slug' => 'inventory-hub-pro-' . uniqid(),
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
        ])->assertRedirect("http://{$domain}/workspace/admin/dashboard");

        $dashboardResponse = $this->get("http://{$domain}/automotive/admin/dashboard?workspace_product={$partsProduct->code}");

        $dashboardResponse->assertOk();
        $dashboardResponse->assertSee('Inventory and stock movement workspace', false);
        $dashboardResponse->assertSee('Supplier Catalog', false);
        $dashboardResponse->assertSee('Inventory Report', false);
        $dashboardResponse->assertSee("workspace_product={$partsProduct->code}", false);
    }

    public function test_supplier_catalog_can_create_supplier_inside_parts_workspace(): void
    {
        [$tenant, $domain, $email, $password] = $this->prepareTenantWorkspace('active');
        $this->attachPartsWorkspaceToTenant($tenant);

        $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $email,
            'password' => $password,
        ])->assertRedirect("http://{$domain}/workspace/admin/dashboard");

        $catalogResponse = $this->get("http://{$domain}/automotive/admin/supplier-catalog?workspace_product=parts_inventory");
        $catalogResponse->assertOk();
        $catalogResponse->assertSee('Create Supplier', false);
        $catalogResponse->assertSee('Supplier Table', false);

        $storeResponse = $this->post("http://{$domain}/automotive/admin/supplier-catalog?workspace_product=parts_inventory", [
            'workspace_product' => 'parts_inventory',
            'name' => 'Prime Parts Vendor',
            'contact_name' => 'Salem',
            'phone' => '0501111111',
            'email' => 'vendor@example.test',
            'address' => 'Dubai Industrial Area',
            'notes' => 'Preferred source for quick deliveries',
            'is_active' => '1',
        ]);

        $storeResponse->assertRedirect("http://{$domain}/workspace/admin/supplier-catalog?workspace_product=parts_inventory");

        tenancy()->initialize($tenant);

        try {
            $supplier = DB::connection('tenant')->table('suppliers')->latest('id')->first();
            $this->assertNotNull($supplier);
            $this->assertSame('Prime Parts Vendor', $supplier->name);
            $this->assertSame('Salem', $supplier->contact_name);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $catalogRefresh = $this->get("http://{$domain}/automotive/admin/supplier-catalog?workspace_product=parts_inventory");
        $catalogRefresh->assertOk();
        $catalogRefresh->assertSee('Prime Parts Vendor', false);
        $catalogRefresh->assertSee('ACTIVE', false);
    }

    public function test_parts_inventory_routes_are_blocked_when_tenant_does_not_have_that_product(): void
    {
        [, $domain, $email, $password] = $this->prepareTenantWorkspace('active');

        $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $email,
            'password' => $password,
        ])->assertRedirect("http://{$domain}/workspace/admin/dashboard");

        $response = $this->get("http://{$domain}/automotive/admin/products");

        $response->assertRedirect("http://{$domain}/workspace/admin/dashboard?workspace_product=parts_inventory");
    }

    public function test_product_runtime_is_blocked_until_activation_state_is_ready(): void
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
            'name' => 'Parts Pending Activation',
            'slug' => 'parts-pending-activation-' . uniqid(),
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
            'activation_status' => 'failed',
            'provisioning_status' => 'failed',
            'activation_error' => 'Tenant migration did not finish.',
            'gateway' => 'stripe',
            'gateway_subscription_id' => 'sub_failed_activation_' . uniqid(),
        ]);

        $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $email,
            'password' => $password,
        ])->assertRedirect("http://{$domain}/workspace/admin/dashboard");

        $response = $this->get("http://{$domain}/automotive/admin/products?workspace_product=parts_inventory");

        $response->assertRedirect("http://{$domain}/workspace/admin/dashboard?workspace_product=parts_inventory");
    }

    public function test_workshop_operations_show_connected_spare_parts_stock_snapshot(): void
    {
        [$tenant, $domain, $email, $password] = $this->prepareTenantWorkspace('active');
        $this->attachPartsWorkspaceToTenant($tenant);

        $this->seedTenantStock($tenant, [
            [
                'branch' => ['name' => 'Main Branch', 'code' => 'MAIN'],
                'product' => ['name' => 'Oil Filter', 'sku' => 'OF-100'],
                'quantity' => 6,
            ],
        ]);

        $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $email,
            'password' => $password,
        ])->assertRedirect("http://{$domain}/workspace/admin/dashboard");

        $response = $this->get("http://{$domain}/automotive/admin/workshop-operations?workspace_product=automotive_service");

        $response->assertOk();
        $response->assertSee('Create Customer', false);
        $response->assertSee('Register Vehicle', false);
        $response->assertSee('Step 3: Create Work Order', false);
        $response->assertSee('Step 4: Consume Spare Parts', false);
        $response->assertSee('Available Spare Parts Stock', false);
        $response->assertSee('Oil Filter', false);
        $response->assertSee('OF-100', false);
    }

    public function test_accounting_runtime_can_create_posting_group_and_post_event_to_journal(): void
    {
        [$tenant, $domain, $email, $password] = $this->prepareTenantWorkspace('active');
        $this->attachAccountingWorkspaceToTenant($tenant);

        tenancy()->initialize($tenant);

        try {
            $eventId = DB::connection('tenant')->table('accounting_events')->insertGetId([
                'event_type' => 'work_order_completed',
                'reference_type' => \App\Models\WorkOrder::class,
                'reference_id' => 10,
                'status' => 'posted',
                'event_date' => now(),
                'currency' => 'USD',
                'labor_amount' => 150,
                'parts_amount' => 40,
                'total_amount' => 190,
                'payload' => json_encode([
                    'work_order_number' => 'WO-ACCOUNTING-1',
                    'title' => 'Accounting review work order',
                    'customer_name' => 'Ledger Customer',
                    'vehicle' => 'Toyota Corolla',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $email,
            'password' => $password,
        ])->assertRedirect("http://{$domain}/workspace/admin/dashboard");

        $ledgerResponse = $this->get("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting");
        $ledgerResponse->assertOk();
        $ledgerResponse->assertSee('Accounting Workspace Navigation', false);
        $ledgerResponse->assertSee('href="#accounting-posting-queue"', false);
        $ledgerResponse->assertSee('href="#accounting-approvals"', false);
        $ledgerResponse->assertSee('href="#accounting-period-close"', false);
        $ledgerResponse->assertSee('href="#accounting-reports"', false);
        $ledgerResponse->assertSee('href="#accounting-receivables"', false);
        $ledgerResponse->assertSee('href="#accounting-payables"', false);
        $ledgerResponse->assertSee('href="#accounting-tax"', false);
        $ledgerResponse->assertSee('href="#accounting-audit"', false);
        $ledgerResponse->assertSee('Create Posting Group', false);
        $ledgerResponse->assertSee('Accounting Event Review', false);
        $ledgerResponse->assertSee('WO-ACCOUNTING-1', false);
        $ledgerResponse->assertSee('Post To Journal', false);

        $groupResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/posting-groups?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'code' => 'service_revenue',
            'name' => 'Service Revenue',
            'receivable_account' => '1100 Accounts Receivable',
            'labor_revenue_account' => '4100 Service Labor Revenue',
            'parts_revenue_account' => '4200 Parts Revenue',
            'currency' => 'USD',
            'is_default' => '1',
        ]);

        $groupResponse->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");

        tenancy()->initialize($tenant);

        try {
            $postingGroup = DB::connection('tenant')->table('accounting_posting_groups')
                ->where('code', 'service_revenue')
                ->first();

            $this->assertNotNull($postingGroup);
            $this->assertSame('Service Revenue', $postingGroup->name);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $postResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/accounting-events/{$eventId}/post?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'posting_group_id' => $postingGroup->id,
        ]);

        $postResponse->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");

        tenancy()->initialize($tenant);

        try {
            $event = DB::connection('tenant')->table('accounting_events')->where('id', $eventId)->first();
            $this->assertSame('journal_posted', $event->status);

            $journal = DB::connection('tenant')->table('journal_entries')
                ->where('accounting_event_id', $eventId)
                ->first();

            $this->assertNotNull($journal);
            $this->assertSame('posted', $journal->status);
            $this->assertSame(190.0, (float) $journal->debit_total);
            $this->assertSame(190.0, (float) $journal->credit_total);

            $lines = DB::connection('tenant')->table('journal_entry_lines')
                ->where('journal_entry_id', $journal->id)
                ->orderBy('id')
                ->get();

            $this->assertCount(3, $lines);
            $this->assertSame(190.0, (float) $lines[0]->debit);
            $this->assertSame(150.0, (float) $lines[1]->credit);
            $this->assertSame(40.0, (float) $lines[2]->credit);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $postedLedgerResponse = $this->get("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting");
        $postedLedgerResponse->assertOk();
        $postedLedgerResponse->assertSee('Recent Journal Entries', false);
        $postedLedgerResponse->assertSee('JE-', false);
        $postedLedgerResponse->assertSee('JOURNAL POSTED', false);
    }

    public function test_accounting_runtime_can_record_customer_payment_and_settle_receivable(): void
    {
        [$tenant, $domain, $email, $password] = $this->prepareTenantWorkspace('active');
        $this->attachAccountingWorkspaceToTenant($tenant);

        tenancy()->initialize($tenant);

        try {
            $eventId = DB::connection('tenant')->table('accounting_events')->insertGetId([
                'event_type' => 'work_order_completed',
                'reference_type' => \App\Models\WorkOrder::class,
                'reference_id' => 20,
                'status' => 'posted',
                'event_date' => '2026-05-04',
                'currency' => 'USD',
                'labor_amount' => 120,
                'parts_amount' => 30,
                'total_amount' => 150,
                'payload' => json_encode([
                    'work_order_number' => 'WO-PAYMENT-1',
                    'title' => 'Payment collection work order',
                    'customer_name' => 'Payment Customer',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $email,
            'password' => $password,
        ])->assertRedirect("http://{$domain}/workspace/admin/dashboard");

        $this->post("http://{$domain}/automotive/admin/general-ledger/accounting-events/{$eventId}/post?workspace_product=accounting", [
            'workspace_product' => 'accounting',
        ])->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");

        $ledgerResponse = $this->get("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting");
        $ledgerResponse->assertOk();
        $ledgerResponse->assertSee('Record Customer Payment', false);
        $ledgerResponse->assertSee('Receivables Aging', false);
        $ledgerResponse->assertSee('Export Payments CSV', false);
        $ledgerResponse->assertSee('WO-PAYMENT-1', false);
        $ledgerResponse->assertSee('Open 150.00 USD', false);

        $paymentResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/payments?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'accounting_event_id' => $eventId,
            'payment_date' => '2026-05-05',
            'amount' => 150,
            'method' => 'cash',
            'payer_name' => 'Payment Customer',
            'reference' => 'RCPT-001',
            'currency' => 'USD',
            'cash_account' => '1000 Cash On Hand',
        ]);

        $paymentResponse->assertRedirect();

        tenancy()->initialize($tenant);

        try {
            $event = DB::connection('tenant')->table('accounting_events')->where('id', $eventId)->first();
            $this->assertSame('paid', $event->status);

            $payment = DB::connection('tenant')->table('accounting_payments')
                ->where('accounting_event_id', $eventId)
                ->first();

            $this->assertNotNull($payment);
            $this->assertStringStartsWith('PMT-', $payment->payment_number);
            $this->assertSame(150.0, (float) $payment->amount);
            $this->assertSame('RCPT-001', $payment->reference);

            $journal = DB::connection('tenant')->table('journal_entries')
                ->where('id', $payment->journal_entry_id)
                ->first();

            $this->assertNotNull($journal);
            $this->assertStringStartsWith('PAY-', $journal->journal_number);
            $this->assertSame(150.0, (float) $journal->debit_total);
            $this->assertSame(150.0, (float) $journal->credit_total);

            $lines = DB::connection('tenant')->table('journal_entry_lines')
                ->where('journal_entry_id', $journal->id)
                ->orderBy('id')
                ->get();

            $this->assertCount(2, $lines);
            $this->assertSame('1000 Cash On Hand', $lines[0]->account_code);
            $this->assertSame(150.0, (float) $lines[0]->debit);
            $this->assertSame('1100 Accounts Receivable', $lines[1]->account_code);
            $this->assertSame(150.0, (float) $lines[1]->credit);
            $this->assertDatabaseHas('accounting_audit_entries', [
                'event_type' => 'customer_payment_recorded',
            ], 'tenant');
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $paymentsCsv = $this->get("http://{$domain}/automotive/admin/general-ledger/exports/payments?workspace_product=accounting&format=csv");
        $paymentsCsv->assertOk();
        $paymentsCsvContent = $paymentsCsv->streamedContent();
        $this->assertStringContainsString('Payment Number', $paymentsCsvContent);
        $this->assertStringContainsString('RCPT-001', $paymentsCsvContent);

        $detailResponse = $this->get("http://{$domain}/automotive/admin/general-ledger/journal-entries/{$journal->id}?workspace_product=accounting");
        $detailResponse->assertOk();
        $detailResponse->assertSee('Customer payment for WO-PAYMENT-1', false);

        $invoiceResponse = $this->get("http://{$domain}/automotive/admin/general-ledger/accounting-events/{$eventId}/invoice?workspace_product=accounting");
        $invoiceResponse->assertOk();
        $invoiceResponse->assertSee('Invoice INV-', false);
        $invoiceResponse->assertSee('Payment Customer', false);
        $invoiceResponse->assertSee('Paid Amount', false);
        $invoiceResponse->assertSee('150.00', false);

        $statementResponse = $this->get("http://{$domain}/automotive/admin/general-ledger/customer-statement?workspace_product=accounting&customer=Payment%20Customer");
        $statementResponse->assertOk();
        $statementResponse->assertSee('Customer Statement', false);
        $statementResponse->assertSee('WO-PAYMENT-1', false);
        $statementResponse->assertSee('Payment', false);

        $voidResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/payments/{$payment->id}/void?workspace_product=accounting", [
            'workspace_product' => 'accounting',
        ]);

        $voidResponse->assertRedirect();

        tenancy()->initialize($tenant);

        try {
            $voidedPayment = DB::connection('tenant')->table('accounting_payments')->where('id', $payment->id)->first();
            $this->assertSame('void', $voidedPayment->status);

            $event = DB::connection('tenant')->table('accounting_events')->where('id', $eventId)->first();
            $this->assertSame('journal_posted', $event->status);

            $voidJournal = DB::connection('tenant')->table('journal_entries')
                ->where('source_type', 'payment_void')
                ->where('source_id', $payment->id)
                ->first();

            $this->assertNotNull($voidJournal);
            $this->assertStringStartsWith('PVOID-', $voidJournal->journal_number);

            $voidLines = DB::connection('tenant')->table('journal_entry_lines')
                ->where('journal_entry_id', $voidJournal->id)
                ->orderBy('id')
                ->get();

            $this->assertSame('1100 Accounts Receivable', $voidLines[0]->account_code);
            $this->assertSame(150.0, (float) $voidLines[0]->debit);
            $this->assertSame('1000 Cash On Hand', $voidLines[1]->account_code);
            $this->assertSame(150.0, (float) $voidLines[1]->credit);
            $this->assertDatabaseHas('accounting_audit_entries', [
                'event_type' => 'customer_payment_voided',
            ], 'tenant');
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $voidedStatementResponse = $this->get("http://{$domain}/automotive/admin/general-ledger/customer-statement?workspace_product=accounting&customer=Payment%20Customer");
        $voidedStatementResponse->assertOk();
        $voidedStatementResponse->assertSee('Voided Payment', false);
        $voidedStatementResponse->assertSee('Open Balance', false);
    }

    public function test_accounting_runtime_can_group_posted_payments_into_deposit_batch(): void
    {
        [$tenant, $domain, $email, $password] = $this->prepareTenantWorkspace('active');
        $this->attachAccountingWorkspaceToTenant($tenant);

        tenancy()->initialize($tenant);

        try {
            $eventId = DB::connection('tenant')->table('accounting_events')->insertGetId([
                'event_type' => 'work_order_completed',
                'reference_type' => \App\Models\WorkOrder::class,
                'reference_id' => 21,
                'status' => 'posted',
                'event_date' => '2026-05-06',
                'currency' => 'USD',
                'labor_amount' => 90,
                'parts_amount' => 0,
                'total_amount' => 90,
                'payload' => json_encode([
                    'work_order_number' => 'WO-DEPOSIT-1',
                    'title' => 'Deposit reconciliation work order',
                    'customer_name' => 'Deposit Customer',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $email,
            'password' => $password,
        ])->assertRedirect("http://{$domain}/workspace/admin/dashboard");

        $this->post("http://{$domain}/automotive/admin/general-ledger/accounting-events/{$eventId}/post?workspace_product=accounting", [
            'workspace_product' => 'accounting',
        ])->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");

        $this->post("http://{$domain}/automotive/admin/general-ledger/payments?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'accounting_event_id' => $eventId,
            'payment_date' => '2026-05-07',
            'amount' => 90,
            'method' => 'bank_transfer',
            'payer_name' => 'Deposit Customer',
            'reference' => 'BANK-DEP-001',
            'currency' => 'USD',
            'cash_account' => '1010 Bank Account',
        ])->assertRedirect();

        tenancy()->initialize($tenant);

        try {
            $payment = DB::connection('tenant')->table('accounting_payments')
                ->where('accounting_event_id', $eventId)
                ->first();

            $this->assertNotNull($payment);
            $this->assertSame('pending', $payment->reconciliation_status);
            $this->assertNull($payment->deposit_batch_id);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $ledgerResponse = $this->get("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting");
        $ledgerResponse->assertOk();
        $ledgerResponse->assertSee('Payment Reconciliation', false);
        $ledgerResponse->assertSee('Create Deposit Batch', false);
        $ledgerResponse->assertSee('BANK-DEP-001', false);
        $ledgerResponse->assertSee('PENDING', false);

        $depositResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/deposit-batches?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'payment_ids' => [$payment->id],
            'deposit_date' => '2026-05-08',
            'deposit_account' => '1010 Bank Account',
            'currency' => 'USD',
            'reference' => 'DEP-SLIP-001',
            'notes' => 'Daily bank deposit.',
        ]);

        $depositResponse->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");

        tenancy()->initialize($tenant);

        try {
            $batch = DB::connection('tenant')->table('accounting_deposit_batches')->first();

            $this->assertNotNull($batch);
            $this->assertStringStartsWith('DEP-', $batch->deposit_number);
            $this->assertSame('DEP-SLIP-001', $batch->reference);
            $this->assertSame(90.0, (float) $batch->total_amount);
            $this->assertSame(1, (int) $batch->payments_count);

            $depositedPayment = DB::connection('tenant')->table('accounting_payments')->where('id', $payment->id)->first();
            $this->assertSame((int) $batch->id, (int) $depositedPayment->deposit_batch_id);
            $this->assertSame('deposited', $depositedPayment->reconciliation_status);
            $this->assertNotNull($depositedPayment->reconciled_at);

            $this->assertDatabaseHas('accounting_audit_entries', [
                'event_type' => 'payment_deposit_batch_posted',
            ], 'tenant');
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $updatedLedgerResponse = $this->get("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting&reconciliation_status=deposited");
        $updatedLedgerResponse->assertOk();
        $updatedLedgerResponse->assertSee('Recent Deposit Batches', false);
        $updatedLedgerResponse->assertSee('DEP-SLIP-001', false);
        $updatedLedgerResponse->assertSee('DEPOSITED', false);

        $paymentsCsv = $this->get("http://{$domain}/automotive/admin/general-ledger/exports/payments?workspace_product=accounting&format=csv&reconciliation_status=deposited");
        $paymentsCsv->assertOk();
        $paymentsCsvContent = $paymentsCsv->streamedContent();
        $this->assertStringContainsString('Reconciliation', $paymentsCsvContent);
        $this->assertStringContainsString('deposited', $paymentsCsvContent);

        $bankReconciliationReport = $this->get("http://{$domain}/automotive/admin/general-ledger/exports/bank-reconciliation?workspace_product=accounting&format=print&status=posted");
        $bankReconciliationReport->assertOk();
        $bankReconciliationReport->assertSee('Bank Reconciliation Report', false);
        $bankReconciliationReport->assertSee('DEP-SLIP-001', false);

        $depositDetailResponse = $this->get("http://{$domain}/automotive/admin/general-ledger/deposit-batches/{$batch->id}?workspace_product=accounting");
        $depositDetailResponse->assertOk();
        $depositDetailResponse->assertSee('Deposit Batch', false);
        $depositDetailResponse->assertSee('Correct Deposit Batch', false);
        $depositDetailResponse->assertSee('BANK-DEP-001', false);

        $correctionResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/deposit-batches/{$batch->id}/correct?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'correction_reason' => 'Bank slip entered against the wrong date.',
        ]);

        $correctionResponse->assertRedirect("http://{$domain}/workspace/admin/general-ledger/deposit-batches/{$batch->id}?workspace_product=accounting");

        tenancy()->initialize($tenant);

        try {
            $correctedBatch = DB::connection('tenant')->table('accounting_deposit_batches')->where('id', $batch->id)->first();

            $this->assertSame('corrected', $correctedBatch->status);
            $this->assertSame('Bank slip entered against the wrong date.', $correctedBatch->correction_reason);
            $this->assertNotNull($correctedBatch->corrected_at);

            $pendingPayment = DB::connection('tenant')->table('accounting_payments')->where('id', $payment->id)->first();
            $this->assertNull($pendingPayment->deposit_batch_id);
            $this->assertSame('pending', $pendingPayment->reconciliation_status);
            $this->assertNull($pendingPayment->reconciled_at);

            $this->assertDatabaseHas('accounting_audit_entries', [
                'event_type' => 'deposit_batch_corrected',
            ], 'tenant');
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $correctedDetailResponse = $this->get("http://{$domain}/automotive/admin/general-ledger/deposit-batches/{$batch->id}?workspace_product=accounting");
        $correctedDetailResponse->assertOk();
        $correctedDetailResponse->assertSee('CORRECTED', false);
        $correctedDetailResponse->assertSee('Bank slip entered against the wrong date.', false);
    }

    public function test_accounting_runtime_can_create_and_post_vendor_bill_to_payables(): void
    {
        [$tenant, $domain, $email, $password] = $this->prepareTenantWorkspace('active');
        $this->attachAccountingWorkspaceToTenant($tenant);

        $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $email,
            'password' => $password,
        ])->assertRedirect("http://{$domain}/workspace/admin/dashboard");

        $ledgerResponse = $this->get("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting");
        $ledgerResponse->assertOk();
        $ledgerResponse->assertSee('Create Vendor Bill', false);
        $ledgerResponse->assertSee('Payables Review', false);
        $ledgerResponse->assertSee('Tax And VAT Settings', false);

        $taxRateResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/tax-rates?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'code' => 'vat_5_test',
            'name' => 'VAT 5 Test',
            'rate' => 5,
            'input_tax_account' => '1410 VAT Input Receivable',
            'output_tax_account' => '2100 VAT Output Payable',
            'is_default' => '1',
            'is_active' => '1',
        ]);

        $taxRateResponse->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");

        tenancy()->initialize($tenant);

        try {
            $taxRate = DB::connection('tenant')->table('accounting_tax_rates')->where('code', 'vat_5_test')->first();

            $this->assertNotNull($taxRate);
            $this->assertSame('1410 VAT Input Receivable', $taxRate->input_tax_account);
            $this->assertSame('2100 VAT Output Payable', $taxRate->output_tax_account);
            $this->assertDatabaseHas('accounting_audit_entries', [
                'event_type' => 'tax_rate_changed',
                'auditable_type' => \App\Models\AccountingTaxRate::class,
                'auditable_id' => $taxRate->id,
            ], 'tenant');
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $createResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/vendor-bills?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'bill_date' => '2026-05-09',
            'due_date' => '2026-05-30',
            'supplier_name' => 'Rent Vendor',
            'reference' => 'BILL-RENT-001',
            'currency' => 'USD',
            'amount' => 240,
            'accounting_tax_rate_id' => $taxRate->id,
            'tax_amount' => 12,
            'expense_account' => '5200 Operating Expense',
            'payable_account' => '2000 Accounts Payable',
            'tax_account' => '1410 VAT Input Receivable',
            'notes' => 'Monthly workshop rent.',
        ]);

        $createResponse->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");

        tenancy()->initialize($tenant);

        try {
            $bill = DB::connection('tenant')->table('accounting_vendor_bills')->first();

            $this->assertNotNull($bill);
            $this->assertStringStartsWith('VBILL-', $bill->bill_number);
            $this->assertSame('draft', $bill->status);
            $this->assertSame('Rent Vendor', $bill->supplier_name);
            $this->assertSame(240.0, (float) $bill->amount);
            $this->assertSame(12.0, (float) $bill->tax_amount);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $reviewResponse = $this->get("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting&vendor_bill_status=draft");
        $reviewResponse->assertOk();
        $reviewResponse->assertSee('Rent Vendor', false);
        $reviewResponse->assertSee('Post To Payables', false);
        $reviewResponse->assertSee('DRAFT', false);

        $postResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/vendor-bills/{$bill->id}/post?workspace_product=accounting", [
            'workspace_product' => 'accounting',
        ]);

        $postResponse->assertRedirect();

        tenancy()->initialize($tenant);

        try {
            $postedBill = DB::connection('tenant')->table('accounting_vendor_bills')->where('id', $bill->id)->first();

            $this->assertSame('posted', $postedBill->status);
            $this->assertNotNull($postedBill->journal_entry_id);
            $this->assertNotNull($postedBill->posted_at);

            $journal = DB::connection('tenant')->table('journal_entries')->where('id', $postedBill->journal_entry_id)->first();
            $this->assertNotNull($journal);
            $this->assertStringStartsWith('AP-', $journal->journal_number);
            $this->assertSame(240.0, (float) $journal->debit_total);
            $this->assertSame(240.0, (float) $journal->credit_total);

            $lines = DB::connection('tenant')->table('journal_entry_lines')
                ->where('journal_entry_id', $journal->id)
                ->orderBy('id')
                ->get();

            $this->assertCount(3, $lines);
            $this->assertSame('5200 Operating Expense', $lines[0]->account_code);
            $this->assertSame(228.0, (float) $lines[0]->debit);
            $this->assertSame('1410 VAT Input Receivable', $lines[1]->account_code);
            $this->assertSame(12.0, (float) $lines[1]->debit);
            $this->assertSame('2000 Accounts Payable', $lines[2]->account_code);
            $this->assertSame(240.0, (float) $lines[2]->credit);

            $this->assertDatabaseHas('accounting_audit_entries', [
                'event_type' => 'vendor_bill_posted',
            ], 'tenant');
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $journalDetailResponse = $this->get("http://{$domain}/automotive/admin/general-ledger/journal-entries/{$journal->id}?workspace_product=accounting");
        $journalDetailResponse->assertOk();
        $journalDetailResponse->assertSee('Vendor bill', false);
        $journalDetailResponse->assertSee('Accounts Payable', false);

        $payablesResponse = $this->get("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting");
        $payablesResponse->assertOk();
        $payablesResponse->assertSee('Pay Vendor Bill', false);
        $payablesResponse->assertSee('Payables Aging', false);
        $payablesResponse->assertSee('Open 240.00 USD', false);

        $partialPaymentResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/vendor-bill-payments?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'accounting_vendor_bill_id' => $bill->id,
            'payment_date' => '2026-05-10',
            'amount' => 100,
            'method' => 'bank_transfer',
            'reference' => 'VEND-PAY-001',
            'currency' => 'USD',
            'cash_account' => '1010 Bank Account',
        ]);

        $partialPaymentResponse->assertRedirect();

        tenancy()->initialize($tenant);

        try {
            $partiallyPaidBill = DB::connection('tenant')->table('accounting_vendor_bills')->where('id', $bill->id)->first();
            $this->assertSame('partial', $partiallyPaidBill->status);

            $vendorPayment = DB::connection('tenant')->table('accounting_vendor_bill_payments')
                ->where('accounting_vendor_bill_id', $bill->id)
                ->first();

            $this->assertNotNull($vendorPayment);
            $this->assertStringStartsWith('VPMT-', $vendorPayment->payment_number);
            $this->assertSame(100.0, (float) $vendorPayment->amount);
            $this->assertSame('VEND-PAY-001', $vendorPayment->reference);

            $paymentJournal = DB::connection('tenant')->table('journal_entries')->where('id', $vendorPayment->journal_entry_id)->first();
            $this->assertStringStartsWith('VPAY-', $paymentJournal->journal_number);
            $this->assertSame(100.0, (float) $paymentJournal->debit_total);
            $this->assertSame(100.0, (float) $paymentJournal->credit_total);

            $paymentLines = DB::connection('tenant')->table('journal_entry_lines')
                ->where('journal_entry_id', $paymentJournal->id)
                ->orderBy('id')
                ->get();

            $this->assertSame('2000 Accounts Payable', $paymentLines[0]->account_code);
            $this->assertSame(100.0, (float) $paymentLines[0]->debit);
            $this->assertSame('1010 Bank Account', $paymentLines[1]->account_code);
            $this->assertSame(100.0, (float) $paymentLines[1]->credit);

            $this->assertDatabaseHas('accounting_audit_entries', [
                'event_type' => 'vendor_bill_payment_recorded',
            ], 'tenant');
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $partialLedgerResponse = $this->get("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting&vendor_bill_status=partial");
        $partialLedgerResponse->assertOk();
        $partialLedgerResponse->assertSee('PARTIAL', false);
        $partialLedgerResponse->assertSee('Paid 100.00', false);
        $partialLedgerResponse->assertSee('Open 140.00', false);
        $partialLedgerResponse->assertSee('Recent Vendor Payments', false);

        $finalPaymentResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/vendor-bill-payments?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'accounting_vendor_bill_id' => $bill->id,
            'payment_date' => '2026-05-11',
            'amount' => 140,
            'method' => 'bank_transfer',
            'reference' => 'VEND-PAY-002',
            'currency' => 'USD',
            'cash_account' => '1010 Bank Account',
        ]);

        $finalPaymentResponse->assertRedirect();

        tenancy()->initialize($tenant);

        try {
            $paidBill = DB::connection('tenant')->table('accounting_vendor_bills')->where('id', $bill->id)->first();
            $this->assertSame('paid', $paidBill->status);
            $this->assertSame(240.0, (float) DB::connection('tenant')->table('accounting_vendor_bill_payments')->where('accounting_vendor_bill_id', $bill->id)->sum('amount'));
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $paidLedgerResponse = $this->get("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting&vendor_bill_status=paid");
        $paidLedgerResponse->assertOk();
        $paidLedgerResponse->assertSee('PAID', false);
        $paidLedgerResponse->assertSee('Paid 240.00', false);

        $profitAndLossPrint = $this->get("http://{$domain}/automotive/admin/general-ledger/exports/profit-and-loss?workspace_product=accounting&format=print");
        $profitAndLossPrint->assertOk();
        $profitAndLossPrint->assertSee('Profit And Loss', false);
        $profitAndLossPrint->assertSee('Operating Expense', false);
        $profitAndLossPrint->assertSee('Net Income', false);

        $profitAndLossCsv = $this->get("http://{$domain}/automotive/admin/general-ledger/exports/profit-and-loss?workspace_product=accounting&format=csv");
        $profitAndLossCsv->assertOk();
        $profitAndLossContent = $profitAndLossCsv->streamedContent();
        $this->assertStringContainsString('Operating Expense', $profitAndLossContent);
        $this->assertStringContainsString('Net Income', $profitAndLossContent);

        $balanceSheetPrint = $this->get("http://{$domain}/automotive/admin/general-ledger/exports/balance-sheet?workspace_product=accounting&format=print");
        $balanceSheetPrint->assertOk();
        $balanceSheetPrint->assertSee('Balance Sheet', false);
        $balanceSheetPrint->assertSee('Bank Account', false);
        $balanceSheetPrint->assertSee('Difference', false);

        $balanceSheetCsv = $this->get("http://{$domain}/automotive/admin/general-ledger/exports/balance-sheet?workspace_product=accounting&format=csv");
        $balanceSheetCsv->assertOk();
        $balanceSheetContent = $balanceSheetCsv->streamedContent();
        $this->assertStringContainsString('Bank Account', $balanceSheetContent);
        $this->assertStringContainsString('Difference', $balanceSheetContent);

        $taxSummaryPrint = $this->get("http://{$domain}/automotive/admin/general-ledger/exports/tax-summary?workspace_product=accounting&format=print");
        $taxSummaryPrint->assertOk();
        $taxSummaryPrint->assertSee('Tax Summary', false);
        $taxSummaryPrint->assertSee('VAT Input Receivable', false);
        $taxSummaryPrint->assertSee('Input Tax Total', false);

        $taxSummaryCsv = $this->get("http://{$domain}/automotive/admin/general-ledger/exports/tax-summary?workspace_product=accounting&format=csv");
        $taxSummaryCsv->assertOk();
        $taxSummaryContent = $taxSummaryCsv->streamedContent();
        $this->assertStringContainsString('VAT Input Receivable', $taxSummaryContent);
        $this->assertStringContainsString('Net Tax Payable', $taxSummaryContent);
    }

    public function test_accounting_ap_enhancements_link_suppliers_attachments_due_filters_and_credit_notes(): void
    {
        [$tenant, $domain, $email, $password] = $this->prepareTenantWorkspace('active');
        $this->attachAccountingWorkspaceToTenant($tenant);
        $dueDate = now()->addDays(3)->toDateString();

        tenancy()->initialize($tenant);

        try {
            $supplierId = DB::connection('tenant')->table('suppliers')->insertGetId([
                'name' => 'Linked Parts Supplier',
                'contact_name' => 'Parts Contact',
                'phone' => '555-0101',
                'email' => 'parts@example.test',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $email,
            'password' => $password,
        ])->assertRedirect("http://{$domain}/workspace/admin/dashboard");

        $createResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/vendor-bills?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'supplier_id' => $supplierId,
            'bill_date' => now()->toDateString(),
            'due_date' => $dueDate,
            'amount' => 300,
            'tax_amount' => 30,
            'currency' => 'USD',
            'expense_account' => '5200 Operating Expense',
            'payable_account' => '2000 Accounts Payable',
            'tax_account' => '1410 VAT Input Receivable',
            'reference' => 'AP-ENH-001',
            'attachment_name' => 'supplier-invoice.pdf',
            'attachment_reference' => 'DOC-AP-001',
            'attachment_url' => 'https://example.test/docs/supplier-invoice.pdf',
        ]);
        $createResponse->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");

        tenancy()->initialize($tenant);

        try {
            $bill = DB::connection('tenant')->table('accounting_vendor_bills')
                ->where('reference', 'AP-ENH-001')
                ->first();

            $this->assertNotNull($bill);
            $this->assertSame((int) $supplierId, (int) $bill->supplier_id);
            $this->assertSame('supplier-invoice.pdf', $bill->attachment_name);
            $this->assertSame('DOC-AP-001', $bill->attachment_reference);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $dueSoonResponse = $this->get("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting&due_status=due_soon&supplier_id={$supplierId}");
        $dueSoonResponse->assertOk();
        $dueSoonResponse->assertSee('Linked Parts Supplier', false);
        $dueSoonResponse->assertSee('Due Soon', false);

        $this->post("http://{$domain}/automotive/admin/general-ledger/vendor-bills/{$bill->id}/post?workspace_product=accounting", [
            'workspace_product' => 'accounting',
        ])->assertRedirect();

        $postedDueSoonResponse = $this->get("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting&due_status=due_soon&supplier_id={$supplierId}");
        $postedDueSoonResponse->assertOk();
        $postedDueSoonResponse->assertSee('Linked Parts Supplier', false);
        $postedDueSoonResponse->assertSee('supplier-invoice.pdf', false);

        $creditResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/vendor-bills/{$bill->id}/credit-notes?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'adjustment_date' => now()->toDateString(),
            'amount' => 60,
            'tax_amount' => 0,
            'reference' => 'VCN-AP-001',
            'reason' => 'Supplier credit note',
        ]);
        $creditResponse->assertRedirect();

        tenancy()->initialize($tenant);

        try {
            $adjustment = DB::connection('tenant')->table('accounting_vendor_bill_adjustments')
                ->where('reference', 'VCN-AP-001')
                ->first();
            $updatedBill = DB::connection('tenant')->table('accounting_vendor_bills')
                ->where('id', $bill->id)
                ->first();
            $journalLines = DB::connection('tenant')->table('journal_entry_lines')
                ->where('journal_entry_id', $adjustment->journal_entry_id)
                ->orderBy('id')
                ->get();

            $this->assertNotNull($adjustment);
            $this->assertSame('credit_note', $adjustment->type);
            $this->assertSame('posted', $adjustment->status);
            $this->assertSame('posted', $updatedBill->status);
            $this->assertSame('2000 Accounts Payable', $journalLines[0]->account_code);
            $this->assertSame(60.0, (float) $journalLines[0]->debit);
            $this->assertSame('5200 Operating Expense', $journalLines[1]->account_code);
            $this->assertSame(60.0, (float) $journalLines[1]->credit);
            $this->assertDatabaseHas('accounting_audit_entries', [
                'event_type' => 'vendor_bill_credit_note_posted',
            ], 'tenant');
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $overpayResponse = $this->from("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting")
            ->post("http://{$domain}/automotive/admin/general-ledger/vendor-bill-payments?workspace_product=accounting", [
                'workspace_product' => 'accounting',
                'accounting_vendor_bill_id' => $bill->id,
                'payment_date' => now()->toDateString(),
                'amount' => 300,
                'method' => 'bank_transfer',
                'reference' => 'OVERPAY-AP-001',
                'currency' => 'USD',
            ]);
        $overpayResponse->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");
        $overpayResponse->assertSessionHasErrors('amount');

        $ledgerResponse = $this->get("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting&vendor_bill_status=posted");
        $ledgerResponse->assertOk();
        $ledgerResponse->assertSee('Credit', false);
        $ledgerResponse->assertSee('VCN-AP-001', false);
        $ledgerResponse->assertSee('Open 240.00', false);
    }


    public function test_accounting_runtime_can_filter_create_manual_journal_and_reverse_entries(): void
    {
        [$tenant, $domain, $email, $password] = $this->prepareTenantWorkspace('active');
        $this->attachAccountingWorkspaceToTenant($tenant);

        $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $email,
            'password' => $password,
        ])->assertRedirect("http://{$domain}/workspace/admin/dashboard");

        $manualResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/manual-journal-entries?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'entry_date' => '2026-04-21',
            'currency' => 'USD',
            'memo' => 'Manual revenue adjustment',
            'lines' => [
                [
                    'account_code' => '1100 Accounts Receivable',
                    'account_name' => 'Accounts Receivable',
                    'debit' => 150,
                    'credit' => 0,
                    'memo' => 'Manual debit',
                ],
                [
                    'account_code' => '4100 Service Revenue',
                    'account_name' => 'Service Revenue',
                    'debit' => 0,
                    'credit' => 150,
                    'memo' => 'Manual revenue',
                ],
            ],
        ]);

        $manualResponse->assertRedirect();

        tenancy()->initialize($tenant);

        try {
            $journal = DB::connection('tenant')->table('journal_entries')
                ->where('source_type', 'manual')
                ->first();

            $this->assertNotNull($journal);
            $this->assertSame('posted', $journal->status);
            $this->assertSame(150.0, (float) $journal->debit_total);
            $this->assertSame(150.0, (float) $journal->credit_total);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $detailResponse = $this->get("http://{$domain}/automotive/admin/general-ledger/journal-entries/{$journal->id}?workspace_product=accounting");
        $detailResponse->assertOk();
        $detailResponse->assertSee('Journal Entry Overview', false);
        $detailResponse->assertSee('Manual revenue adjustment', false);
        $detailResponse->assertSee('Reverse Journal Entry', false);

        $filteredLedgerResponse = $this->get("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting&search=Manual&status=posted&date_from=2026-04-01&date_to=2026-04-30");
        $filteredLedgerResponse->assertOk();
        $filteredLedgerResponse->assertSee('Journal Filters', false);
        $filteredLedgerResponse->assertSee('Trial Balance', false);
        $filteredLedgerResponse->assertSee('Revenue Summary', false);
        $filteredLedgerResponse->assertSee('Manual revenue adjustment', false);
        $filteredLedgerResponse->assertSee('4100 Service Revenue', false);

        $reverseResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/journal-entries/{$journal->id}/reverse?workspace_product=accounting", [
            'workspace_product' => 'accounting',
        ]);

        $reverseResponse->assertRedirect();

        tenancy()->initialize($tenant);

        try {
            $original = DB::connection('tenant')->table('journal_entries')->where('id', $journal->id)->first();
            $reversal = DB::connection('tenant')->table('journal_entries')
                ->where('source_type', 'journal_reversal')
                ->where('source_id', $journal->id)
                ->first();

            $this->assertSame('reversed', $original->status);
            $this->assertNotNull($reversal);
            $this->assertSame(150.0, (float) $reversal->debit_total);
            $this->assertSame(150.0, (float) $reversal->credit_total);

            $reversalLines = DB::connection('tenant')->table('journal_entry_lines')
                ->where('journal_entry_id', $reversal->id)
                ->orderBy('id')
                ->get();

            $this->assertSame(150.0, (float) $reversalLines[0]->credit);
            $this->assertSame(150.0, (float) $reversalLines[1]->debit);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }
    }

    public function test_accounting_runtime_requires_permissions_for_sensitive_actions(): void
    {
        [$tenant, $domain, $email, $password] = $this->prepareTenantWorkspace('active');
        $this->attachAccountingWorkspaceToTenant($tenant);

        tenancy()->initialize($tenant);

        try {
            DB::connection('tenant')->table('users')
                ->where('email', $email)
                ->update([
                    'accounting_role' => 'viewer',
                    'accounting_permissions' => json_encode([]),
                ]);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $email,
            'password' => $password,
        ])->assertRedirect("http://{$domain}/workspace/admin/dashboard");

        $lockResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/period-locks?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
        ]);
        $lockResponse->assertForbidden();

        $manualResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/manual-journal-entries?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'entry_date' => '2026-05-21',
            'currency' => 'USD',
            'memo' => 'Unauthorized manual journal',
            'lines' => [
                ['account_code' => '1100 Accounts Receivable', 'account_name' => 'Accounts Receivable', 'debit' => 150, 'credit' => 0],
                ['account_code' => '4100 Service Revenue', 'account_name' => 'Service Revenue', 'debit' => 0, 'credit' => 150],
            ],
        ]);
        $manualResponse->assertForbidden();

        tenancy()->initialize($tenant);

        try {
            $this->assertDatabaseMissing('accounting_period_locks', [
                'period_start' => '2026-05-01',
            ], 'tenant');
            $this->assertDatabaseMissing('journal_entries', [
                'memo' => 'Unauthorized manual journal',
            ], 'tenant');
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }
    }

    public function test_high_risk_manual_journals_require_approval_before_posting(): void
    {
        [$tenant, $domain, $email, $password] = $this->prepareTenantWorkspace('active');
        $this->attachAccountingWorkspaceToTenant($tenant);
        $approverEmail = 'approver-' . uniqid() . '@example.test';
        $approverPassword = 'secret-pass';

        tenancy()->initialize($tenant);

        try {
            DB::connection('tenant')->table('users')
                ->where('email', $email)
                ->update([
                    'accounting_role' => 'journal_creator',
                    'accounting_permissions' => json_encode([
                        'accounting.manual_journals.create',
                    ]),
                ]);

            User::query()->create([
                'name' => 'Accounting Approver',
                'email' => $approverEmail,
                'password' => bcrypt($approverPassword),
                'accounting_role' => 'controller',
                'accounting_permissions' => [
                    'accounting.manual_journals.approve',
                    'accounting.manual_journals.post',
                    'accounting.reports.export',
                    'accounting.journals.reverse',
                ],
            ]);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $email,
            'password' => $password,
        ])->assertRedirect("http://{$domain}/workspace/admin/dashboard");

        $manualResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/manual-journal-entries?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'entry_date' => '2026-05-21',
            'currency' => 'USD',
            'memo' => 'High-risk accrual',
            'lines' => [
                ['account_code' => '1100 Accounts Receivable', 'account_name' => 'Accounts Receivable', 'debit' => 6000, 'credit' => 0],
                ['account_code' => '4100 Service Revenue', 'account_name' => 'Service Revenue', 'debit' => 0, 'credit' => 6000],
            ],
        ]);
        $manualResponse->assertRedirect();

        tenancy()->initialize($tenant);

        try {
            $journal = DB::connection('tenant')->table('journal_entries')
                ->where('memo', 'High-risk accrual')
                ->first();

            $this->assertNotNull($journal);
            $this->assertSame('pending_approval', $journal->status);
            $this->assertSame('pending_approval', $journal->approval_status);
            $this->assertSame('high', $journal->risk_level);
            $this->assertNull($journal->posted_at);
            $this->assertDatabaseHas('accounting_audit_entries', [
                'event_type' => 'manual_journal_submitted_for_approval',
            ], 'tenant');
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $detailBeforeApproval = $this->get("http://{$domain}/automotive/admin/general-ledger/journal-entries/{$journal->id}?workspace_product=accounting");
        $detailBeforeApproval->assertOk();
        $detailBeforeApproval->assertSee('PENDING_APPROVAL', false);
        $detailBeforeApproval->assertDontSee('Reverse Journal Entry', false);

        $postBeforeApproval = $this->post("http://{$domain}/automotive/admin/general-ledger/journal-entries/{$journal->id}/post-approved?workspace_product=accounting", [
            'workspace_product' => 'accounting',
        ]);
        $postBeforeApproval->assertForbidden();

        Auth::guard('automotive_admin')->logout();
        $this->flushSession();

        $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $approverEmail,
            'password' => $approverPassword,
        ])->assertRedirect("http://{$domain}/workspace/admin/dashboard");

        $ledgerResponse = $this->get("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting");
        $ledgerResponse->assertOk();
        $ledgerResponse->assertSee('Manual Journal Approvals', false);
        $ledgerResponse->assertSee('High-risk accrual', false);

        $controllerPostBeforeApproval = $this->from("http://{$domain}/automotive/admin/general-ledger/journal-entries/{$journal->id}?workspace_product=accounting")
            ->post("http://{$domain}/automotive/admin/general-ledger/journal-entries/{$journal->id}/post-approved?workspace_product=accounting", [
                'workspace_product' => 'accounting',
            ]);
        $controllerPostBeforeApproval->assertRedirect("http://{$domain}/workspace/admin/general-ledger/journal-entries/{$journal->id}?workspace_product=accounting");
        $controllerPostBeforeApproval->assertSessionHasErrors('journal_entry');

        $approveResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/journal-entries/{$journal->id}/approve?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'approval_notes' => 'Reviewed by controller',
        ]);
        $approveResponse->assertRedirect();

        $postApprovedResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/journal-entries/{$journal->id}/post-approved?workspace_product=accounting", [
            'workspace_product' => 'accounting',
        ]);
        $postApprovedResponse->assertRedirect();

        tenancy()->initialize($tenant);

        try {
            $postedJournal = DB::connection('tenant')->table('journal_entries')
                ->where('id', $journal->id)
                ->first();

            $this->assertSame('posted', $postedJournal->status);
            $this->assertSame('posted', $postedJournal->approval_status);
            $this->assertNotNull($postedJournal->approved_at);
            $this->assertNotNull($postedJournal->posted_at);
            $approvalAudit = DB::connection('tenant')->table('accounting_audit_entries')
                ->where('event_type', 'manual_journal_approved')
                ->first();
            $postingAudit = DB::connection('tenant')->table('accounting_audit_entries')
                ->where('event_type', 'manual_journal_posted_after_approval')
                ->first();
            $approver = DB::connection('tenant')->table('users')
                ->where('email', $approverEmail)
                ->first();

            $this->assertNotNull($approvalAudit);
            $this->assertNotNull($postingAudit);
            $this->assertNotNull($approver);
            $payload = json_decode($postingAudit->payload, true);
            $this->assertSame('manual_journal_posted_after_approval', $payload['event_type']);
            $this->assertSame(\App\Models\JournalEntry::class, $payload['source_type']);
            $this->assertSame($postedJournal->id, $payload['source_id']);
            $this->assertSame($approver->id, $payload['actor_id']);
            $this->assertSame('journal_entries_and_journal_entry_lines', $payload['source_of_truth']);
            $this->assertDatabaseHas('accounting_audit_entries', [
                'event_type' => 'manual_journal_approved',
            ], 'tenant');
            $this->assertDatabaseHas('accounting_audit_entries', [
                'event_type' => 'manual_journal_posted_after_approval',
            ], 'tenant');
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $auditQuery = http_build_query([
            'workspace_product' => 'accounting',
            'audit_event_type' => 'manual_journal_approved',
            'audit_actor_id' => $approver->id,
            'audit_source_type' => \App\Models\JournalEntry::class,
            'audit_date_from' => now()->subDay()->toDateString(),
            'audit_date_to' => now()->addDay()->toDateString(),
        ]);
        $auditResponse = $this->get("http://{$domain}/automotive/admin/general-ledger?{$auditQuery}");
        $auditResponse->assertOk();
        $auditResponse->assertSee('Accounting Audit Timeline', false);
        $auditResponse->assertSee('MANUAL JOURNAL APPROVED', false);
        $auditResponse->assertSee('Actor Accounting Approver', false);
        $auditResponse->assertSee('Source JournalEntry #' . $journal->id, false);
    }

    public function test_accounting_chart_of_accounts_hardening_blocks_unsafe_mutation_and_inactive_posting(): void
    {
        [$tenant, $domain, $email, $password] = $this->prepareTenantWorkspace('active');
        $this->attachAccountingWorkspaceToTenant($tenant);

        $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $email,
            'password' => $password,
        ])->assertRedirect("http://{$domain}/workspace/admin/dashboard");

        $invalidBalanceResponse = $this->from("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting")
            ->post("http://{$domain}/automotive/admin/general-ledger/accounts?workspace_product=accounting", [
                'workspace_product' => 'accounting',
                'code' => '2050 Invalid Liability',
                'name' => 'Invalid Liability',
                'type' => 'liability',
                'normal_balance' => 'debit',
            ]);
        $invalidBalanceResponse->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");
        $invalidBalanceResponse->assertSessionHasErrors('normal_balance');

        $accountResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/accounts?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'code' => '1160 Posted Clearing',
            'name' => 'Posted Clearing',
            'type' => 'asset',
            'normal_balance' => 'debit',
        ]);
        $accountResponse->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");

        $manualResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/manual-journal-entries?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'entry_date' => '2026-06-01',
            'currency' => 'USD',
            'memo' => 'Account hardening proof',
            'lines' => [
                ['account_code' => '1160 Posted Clearing', 'account_name' => 'Posted Clearing', 'debit' => 100, 'credit' => 0],
                ['account_code' => '4100 Service Revenue', 'account_name' => 'Service Revenue', 'debit' => 0, 'credit' => 100],
            ],
        ]);
        $manualResponse->assertRedirect();

        tenancy()->initialize($tenant);

        try {
            $account = DB::connection('tenant')->table('accounting_accounts')
                ->where('code', '1160 Posted Clearing')
                ->first();
            $this->assertNotNull($account);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $unsafeUpdateResponse = $this->from("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting")
            ->post("http://{$domain}/automotive/admin/general-ledger/accounts?workspace_product=accounting", [
                'workspace_product' => 'accounting',
                'code' => '1160 Posted Clearing',
                'name' => 'Changed Posted Clearing',
                'type' => 'expense',
                'normal_balance' => 'debit',
            ]);
        $unsafeUpdateResponse->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");
        $unsafeUpdateResponse->assertSessionHasErrors('code');

        $deleteUsedResponse = $this->from("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting")
            ->delete("http://{$domain}/automotive/admin/general-ledger/accounts/{$account->id}?workspace_product=accounting", [
                'workspace_product' => 'accounting',
            ]);
        $deleteUsedResponse->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");
        $deleteUsedResponse->assertSessionHasErrors('account');

        $deactivateResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/accounts/{$account->id}/deactivate?workspace_product=accounting", [
            'workspace_product' => 'accounting',
        ]);
        $deactivateResponse->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");

        $inactiveManualResponse = $this->from("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting")
            ->post("http://{$domain}/automotive/admin/general-ledger/manual-journal-entries?workspace_product=accounting", [
                'workspace_product' => 'accounting',
                'entry_date' => '2026-06-02',
                'currency' => 'USD',
                'memo' => 'Inactive account should fail',
                'lines' => [
                    ['account_code' => '1160 Posted Clearing', 'account_name' => 'Posted Clearing', 'debit' => 50, 'credit' => 0],
                    ['account_code' => '4100 Service Revenue', 'account_name' => 'Service Revenue', 'debit' => 0, 'credit' => 50],
                ],
            ]);
        $inactiveManualResponse->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");
        $inactiveManualResponse->assertSessionHasErrors('lines');

        $unusedAccountResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/accounts?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'code' => '1170 Unused Clearing',
            'name' => 'Unused Clearing',
            'type' => 'asset',
            'normal_balance' => 'debit',
        ]);
        $unusedAccountResponse->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");

        tenancy()->initialize($tenant);

        try {
            $unusedAccount = DB::connection('tenant')->table('accounting_accounts')
                ->where('code', '1170 Unused Clearing')
                ->first();
            $this->assertNotNull($unusedAccount);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $deleteUnusedResponse = $this->delete("http://{$domain}/automotive/admin/general-ledger/accounts/{$unusedAccount->id}?workspace_product=accounting", [
            'workspace_product' => 'accounting',
        ]);
        $deleteUnusedResponse->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");

        $filterResponse = $this->get("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting&account_search=Posted&account_status=inactive&account_type=asset");
        $filterResponse->assertOk();
        $filterResponse->assertSee('Posted Clearing', false);
        $filterResponse->assertSee('INACTIVE', false);

        tenancy()->initialize($tenant);

        try {
            $this->assertDatabaseHas('accounting_accounts', [
                'code' => '1160 Posted Clearing',
                'is_active' => false,
            ], 'tenant');
            $this->assertDatabaseMissing('accounting_accounts', [
                'code' => '1170 Unused Clearing',
            ], 'tenant');
            $this->assertDatabaseHas('accounting_audit_entries', [
                'event_type' => 'account_deactivated',
            ], 'tenant');
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }
    }

    public function test_accounting_fiscal_period_lifecycle_blocks_close_until_checklist_is_ready_or_overridden(): void
    {
        [$tenant, $domain, $email, $password] = $this->prepareTenantWorkspace('active');
        $this->attachAccountingWorkspaceToTenant($tenant);

        tenancy()->initialize($tenant);

        try {
            DB::connection('tenant')->table('accounting_events')->insert([
                'event_type' => 'work_order_completed',
                'reference_type' => \App\Models\WorkOrder::class,
                'reference_id' => 77,
                'status' => 'posted',
                'event_date' => '2026-07-10',
                'currency' => 'USD',
                'labor_amount' => 200,
                'parts_amount' => 0,
                'total_amount' => 200,
                'payload' => json_encode([
                    'work_order_number' => 'WO-CLOSE-1',
                    'title' => 'Unposted close blocker',
                    'customer_name' => 'Close Customer',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $email,
            'password' => $password,
        ])->assertRedirect("http://{$domain}/workspace/admin/dashboard");

        $ledgerResponse = $this->get("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting");
        $ledgerResponse->assertOk();
        $ledgerResponse->assertSee('Close Readiness', false);
        $ledgerResponse->assertSee('Start Close Review', false);

        $closingResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/period-locks/closing?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'notes' => 'July close review',
        ]);
        $closingResponse->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");

        tenancy()->initialize($tenant);

        try {
            $period = DB::connection('tenant')->table('accounting_period_locks')
                ->where('status', 'closing')
                ->first();

            $this->assertNotNull($period);
            $this->assertSame('closing', $period->status);
            $this->assertDatabaseHas('accounting_audit_entries', [
                'event_type' => 'period_close_started',
            ], 'tenant');
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $blockedLockResponse = $this->from("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting")
            ->post("http://{$domain}/automotive/admin/general-ledger/period-locks?workspace_product=accounting", [
                'workspace_product' => 'accounting',
                'period_start' => '2026-07-01',
                'period_end' => '2026-07-31',
                'notes' => 'Block without override',
            ]);
        $blockedLockResponse->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");
        $blockedLockResponse->assertSessionHasErrors('close_checklist');

        $overrideLockResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/period-locks?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'allow_lock_override' => 1,
            'lock_override_reason' => 'Controller approved cutoff with known open item',
            'notes' => 'July locked with override',
        ]);
        $overrideLockResponse->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");

        tenancy()->initialize($tenant);

        try {
            $lockedPeriod = DB::connection('tenant')->table('accounting_period_locks')
                ->where('status', 'locked')
                ->first();

            $this->assertSame('locked', $lockedPeriod->status);
            $this->assertSame(1, (int) $lockedPeriod->lock_override);
            $this->assertStringContainsString('known open item', $lockedPeriod->lock_override_reason);
            $this->assertDatabaseHas('accounting_audit_entries', [
                'event_type' => 'period_locked',
            ], 'tenant');
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $lockedManualResponse = $this->from("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting")
            ->post("http://{$domain}/automotive/admin/general-ledger/manual-journal-entries?workspace_product=accounting", [
                'workspace_product' => 'accounting',
                'entry_date' => '2026-07-15',
                'currency' => 'USD',
                'memo' => 'Locked July entry',
                'lines' => [
                    ['account_code' => '1100 Accounts Receivable', 'account_name' => 'Accounts Receivable', 'debit' => 25, 'credit' => 0],
                    ['account_code' => '4100 Service Revenue', 'account_name' => 'Service Revenue', 'debit' => 0, 'credit' => 25],
                ],
            ]);
        $lockedManualResponse->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");
        $lockedManualResponse->assertSessionHasErrors('entry_date');

        $archiveResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/period-locks/{$lockedPeriod->id}/archive?workspace_product=accounting", [
            'workspace_product' => 'accounting',
        ]);
        $archiveResponse->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");

        tenancy()->initialize($tenant);

        try {
            $archivedPeriod = DB::connection('tenant')->table('accounting_period_locks')
                ->where('id', $lockedPeriod->id)
                ->first();

            $this->assertSame('archived', $archivedPeriod->status);
            $this->assertDatabaseHas('accounting_audit_entries', [
                'event_type' => 'period_archived',
            ], 'tenant');
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }
    }

    public function test_accounting_bank_accounts_control_cash_activity_and_show_journal_balances(): void
    {
        [$tenant, $domain, $email, $password] = $this->prepareTenantWorkspace('active');
        $this->attachAccountingWorkspaceToTenant($tenant);

        tenancy()->initialize($tenant);

        try {
            $eventId = DB::connection('tenant')->table('accounting_events')->insertGetId([
                'event_type' => 'work_order_completed',
                'reference_type' => \App\Models\WorkOrder::class,
                'reference_id' => 88,
                'status' => 'posted',
                'event_date' => '2026-08-10',
                'currency' => 'USD',
                'labor_amount' => 300,
                'parts_amount' => 0,
                'total_amount' => 300,
                'payload' => json_encode([
                    'work_order_number' => 'WO-BANK-1',
                    'title' => 'Bank account cash management',
                    'customer_name' => 'Bank Customer',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $email,
            'password' => $password,
        ])->assertRedirect("http://{$domain}/workspace/admin/dashboard");

        $this->post("http://{$domain}/automotive/admin/general-ledger/accounts?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'code' => '1020 Operating Bank',
            'name' => 'Operating Bank',
            'type' => 'asset',
            'normal_balance' => 'debit',
        ])->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");

        $bankAccountResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/bank-accounts?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'name' => 'Operating Bank Account',
            'type' => 'bank',
            'account_code' => '1020 Operating Bank',
            'currency' => 'USD',
            'reference' => 'BANK-1020',
            'is_default_receipt' => 1,
            'is_default_payment' => 1,
        ]);
        $bankAccountResponse->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");

        $this->post("http://{$domain}/automotive/admin/general-ledger/accounting-events/{$eventId}/post?workspace_product=accounting", [
            'workspace_product' => 'accounting',
        ])->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");

        tenancy()->initialize($tenant);

        try {
            $bankAccount = DB::connection('tenant')->table('accounting_bank_accounts')
                ->where('account_code', '1020 Operating Bank')
                ->first();
            $this->assertNotNull($bankAccount);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $paymentResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/payments?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'accounting_event_id' => $eventId,
            'payment_date' => '2026-08-11',
            'amount' => 300,
            'method' => 'bank_transfer',
            'payer_name' => 'Bank Customer',
            'reference' => 'BANK-RCPT-001',
            'currency' => 'USD',
            'accounting_bank_account_id' => $bankAccount->id,
        ]);
        $paymentResponse->assertRedirect();

        tenancy()->initialize($tenant);

        try {
            $payment = DB::connection('tenant')->table('accounting_payments')
                ->where('accounting_event_id', $eventId)
                ->first();

            $this->assertNotNull($payment);
            $this->assertSame((int) $bankAccount->id, (int) $payment->accounting_bank_account_id);
            $this->assertSame('1020 Operating Bank', $payment->cash_account);

            $bankLine = DB::connection('tenant')->table('journal_entry_lines')
                ->where('journal_entry_id', $payment->journal_entry_id)
                ->where('account_code', '1020 Operating Bank')
                ->first();

            $this->assertNotNull($bankLine);
            $this->assertSame(300.0, (float) $bankLine->debit);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $ledgerResponse = $this->get("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting");
        $ledgerResponse->assertOk();
        $ledgerResponse->assertSee('Bank & Cash Accounts', false);
        $ledgerResponse->assertSee('Operating Bank Account', false);
        $ledgerResponse->assertSee('1020 Operating Bank', false);
        $ledgerResponse->assertSee('300.00 USD', false);
    }

    public function test_accounting_bank_account_migration_can_resume_when_table_already_exists(): void
    {
        [$tenant] = $this->prepareTenantWorkspace('active');

        tenancy()->initialize($tenant);

        try {
            $this->assertTrue(Schema::connection('tenant')->hasTable('accounting_bank_accounts'));

            DB::connection('tenant')
                ->table('migrations')
                ->where('migration', '2026_04_21_110000_create_accounting_bank_accounts_table')
                ->delete();
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        Artisan::call('tenants:migrate', [
            '--tenants' => [$tenant->id],
            '--force' => true,
        ]);

        tenancy()->initialize($tenant);

        try {
            $this->assertTrue(Schema::connection('tenant')->hasTable('accounting_bank_accounts'));
            $this->assertTrue(Schema::connection('tenant')->hasColumn('accounting_payments', 'accounting_bank_account_id'));
            $this->assertTrue(Schema::connection('tenant')->hasColumn('accounting_deposit_batches', 'accounting_bank_account_id'));
            $this->assertTrue(Schema::connection('tenant')->hasColumn('accounting_vendor_bill_payments', 'accounting_bank_account_id'));
            $this->assertDatabaseHas('migrations', [
                'migration' => '2026_04_21_110000_create_accounting_bank_accounts_table',
            ], 'tenant');
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }
    }

    public function test_accounting_ap_enhancement_migration_can_resume_when_columns_already_exist(): void
    {
        [$tenant] = $this->prepareTenantWorkspace('active');

        tenancy()->initialize($tenant);

        try {
            $this->assertTrue(Schema::connection('tenant')->hasTable('accounting_vendor_bill_adjustments'));
            $this->assertTrue(Schema::connection('tenant')->hasColumn('accounting_vendor_bills', 'attachment_name'));

            DB::connection('tenant')
                ->table('migrations')
                ->where('migration', '2026_04_22_100000_add_ap_enhancements_to_accounting_vendor_bills')
                ->delete();
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        Artisan::call('tenants:migrate', [
            '--tenants' => [$tenant->id],
            '--force' => true,
        ]);

        tenancy()->initialize($tenant);

        try {
            $this->assertTrue(Schema::connection('tenant')->hasTable('accounting_vendor_bill_adjustments'));
            $this->assertTrue(Schema::connection('tenant')->hasColumn('accounting_vendor_bills', 'attachment_name'));
            $this->assertTrue(Schema::connection('tenant')->hasColumn('accounting_vendor_bills', 'attachment_reference'));
            $this->assertTrue(Schema::connection('tenant')->hasColumn('accounting_vendor_bills', 'attachment_url'));
            $this->assertDatabaseHas('migrations', [
                'migration' => '2026_04_22_100000_add_ap_enhancements_to_accounting_vendor_bills',
            ], 'tenant');
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }
    }

    public function test_accounting_reconciliation_workflow_matches_cash_activity_and_blocks_direct_corrections(): void
    {
        [$tenant, $domain, $email, $password] = $this->prepareTenantWorkspace('active');
        $this->attachAccountingWorkspaceToTenant($tenant);

        tenancy()->initialize($tenant);

        try {
            $eventId = DB::connection('tenant')->table('accounting_events')->insertGetId([
                'event_type' => 'work_order_completed',
                'reference_type' => \App\Models\WorkOrder::class,
                'reference_id' => 9911,
                'status' => 'posted',
                'event_date' => '2026-09-01 10:00:00',
                'currency' => 'USD',
                'labor_amount' => 180,
                'parts_amount' => 0,
                'total_amount' => 180,
                'payload' => json_encode([
                    'work_order_number' => 'WO-RECON-1',
                    'title' => 'Reconciliation workflow',
                    'customer_name' => 'Recon Customer',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $email,
            'password' => $password,
        ])->assertRedirect("http://{$domain}/workspace/admin/dashboard");

        $this->post("http://{$domain}/automotive/admin/general-ledger/accounting-events/{$eventId}/post?workspace_product=accounting", [
            'workspace_product' => 'accounting',
        ])->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");

        $this->post("http://{$domain}/automotive/admin/general-ledger/payments?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'accounting_event_id' => $eventId,
            'payment_date' => '2026-09-02',
            'amount' => 180,
            'method' => 'bank_transfer',
            'payer_name' => 'Recon Customer',
            'reference' => 'RCPT-RECON-001',
            'currency' => 'USD',
        ])->assertRedirect();

        tenancy()->initialize($tenant);

        try {
            $payment = DB::connection('tenant')->table('accounting_payments')
                ->where('accounting_event_id', $eventId)
                ->first();
            $this->assertNotNull($payment);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $this->post("http://{$domain}/automotive/admin/general-ledger/deposit-batches?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'payment_ids' => [$payment->id],
            'deposit_date' => '2026-09-03',
            'currency' => 'USD',
            'reference' => 'DEP-RECON-001',
        ])->assertRedirect();

        tenancy()->initialize($tenant);

        try {
            $batch = DB::connection('tenant')->table('accounting_deposit_batches')
                ->where('reference', 'DEP-RECON-001')
                ->first();
            $this->assertNotNull($batch);
            $this->assertSame('pending', $batch->reconciliation_status);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $batchDetail = $this->get("http://{$domain}/automotive/admin/general-ledger/deposit-batches/{$batch->id}?workspace_product=accounting");
        $batchDetail->assertOk();
        $batchDetail->assertSee('Mark Reconciled', false);

        $this->post("http://{$domain}/automotive/admin/general-ledger/deposit-batches/{$batch->id}/reconcile?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'bank_reconciliation_date' => '2026-09-04',
            'bank_reference' => 'BANK-STMT-DEP-1',
        ])->assertRedirect("http://{$domain}/workspace/admin/general-ledger/deposit-batches/{$batch->id}?workspace_product=accounting");

        tenancy()->initialize($tenant);

        try {
            $reconciledBatch = DB::connection('tenant')->table('accounting_deposit_batches')->where('id', $batch->id)->first();
            $reconciledPayment = DB::connection('tenant')->table('accounting_payments')->where('id', $payment->id)->first();

            $this->assertSame('reconciled', $reconciledBatch->reconciliation_status);
            $this->assertStringStartsWith('2026-09-04', (string) $reconciledBatch->bank_reconciliation_date);
            $this->assertSame('BANK-STMT-DEP-1', $reconciledBatch->bank_reference);
            $this->assertSame('reconciled', $reconciledPayment->reconciliation_status);
            $this->assertDatabaseHas('accounting_audit_entries', [
                'event_type' => 'deposit_batch_reconciled',
            ], 'tenant');
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $correctionResponse = $this->from("http://{$domain}/automotive/admin/general-ledger/deposit-batches/{$batch->id}?workspace_product=accounting")
            ->post("http://{$domain}/automotive/admin/general-ledger/deposit-batches/{$batch->id}/correct?workspace_product=accounting", [
                'workspace_product' => 'accounting',
                'correction_reason' => 'Bank already reconciled',
            ]);
        $correctionResponse->assertRedirect("http://{$domain}/workspace/admin/general-ledger/deposit-batches/{$batch->id}?workspace_product=accounting");
        $correctionResponse->assertSessionHasErrors('deposit_batch');

        $this->post("http://{$domain}/automotive/admin/general-ledger/vendor-bills?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'supplier_name' => 'Recon Vendor',
            'bill_date' => '2026-09-05',
            'due_date' => '2026-09-20',
            'amount' => 90,
            'currency' => 'USD',
            'reference' => 'VB-RECON-001',
        ])->assertRedirect();

        tenancy()->initialize($tenant);

        try {
            $bill = DB::connection('tenant')->table('accounting_vendor_bills')
                ->where('reference', 'VB-RECON-001')
                ->first();
            $this->assertNotNull($bill);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $this->post("http://{$domain}/automotive/admin/general-ledger/vendor-bills/{$bill->id}/post?workspace_product=accounting", [
            'workspace_product' => 'accounting',
        ])->assertRedirect();

        $this->post("http://{$domain}/automotive/admin/general-ledger/vendor-bill-payments?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'accounting_vendor_bill_id' => $bill->id,
            'payment_date' => '2026-09-06',
            'amount' => 90,
            'method' => 'bank_transfer',
            'reference' => 'VPMT-RECON-001',
            'currency' => 'USD',
        ])->assertRedirect();

        tenancy()->initialize($tenant);

        try {
            $vendorPayment = DB::connection('tenant')->table('accounting_vendor_bill_payments')
                ->where('reference', 'VPMT-RECON-001')
                ->first();
            $this->assertNotNull($vendorPayment);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $this->post("http://{$domain}/automotive/admin/general-ledger/vendor-bill-payments/{$vendorPayment->id}/reconcile?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'bank_reconciliation_date' => '2026-09-07',
            'bank_reference' => 'BANK-STMT-VPAY-1',
        ])->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");

        tenancy()->initialize($tenant);

        try {
            $reconciledVendorPayment = DB::connection('tenant')->table('accounting_vendor_bill_payments')->where('id', $vendorPayment->id)->first();

            $this->assertSame('reconciled', $reconciledVendorPayment->reconciliation_status);
            $this->assertStringStartsWith('2026-09-07', (string) $reconciledVendorPayment->bank_reconciliation_date);
            $this->assertSame('BANK-STMT-VPAY-1', $reconciledVendorPayment->bank_reference);
            $this->assertDatabaseHas('accounting_audit_entries', [
                'event_type' => 'vendor_payment_reconciled',
            ], 'tenant');
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $ledgerResponse = $this->get("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting&reconciliation_status=reconciled");
        $ledgerResponse->assertOk();
        $ledgerResponse->assertSee('Unreconciled Receipts', false);
        $ledgerResponse->assertSee('Reconciled This Period', false);
        $ledgerResponse->assertSee('RECONCILED', false);

        $printResponse = $this->get("http://{$domain}/automotive/admin/general-ledger/exports/bank-reconciliation?workspace_product=accounting&format=print&reconciliation_status=reconciled");
        $printResponse->assertOk();
        $printResponse->assertSee('Bank Reconciliation Report', false);
        $printResponse->assertSee('Vendor Payments', false);
        $printResponse->assertSee('BANK-STMT-DEP-1', false);
        $printResponse->assertSee('BANK-STMT-VPAY-1', false);
    }

    public function test_accounting_ar_invoices_post_to_journals_and_accept_customer_payments(): void
    {
        [$tenant, $domain, $email, $password] = $this->prepareTenantWorkspace('active');
        $this->attachAccountingWorkspaceToTenant($tenant);

        $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $email,
            'password' => $password,
        ])->assertRedirect("http://{$domain}/workspace/admin/dashboard");

        $invoiceResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/invoices?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'customer_name' => 'Invoice Customer',
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-20',
            'currency' => 'USD',
            'tax_amount' => 10,
            'receivable_account' => '1100 Accounts Receivable',
            'tax_account' => '2100 VAT Output Payable',
            'reference' => 'AR-INV-001',
            'lines' => [
                [
                    'description' => 'Consulting service',
                    'account_code' => '4100 Service Labor Revenue',
                    'quantity' => 2,
                    'unit_price' => 95,
                ],
            ],
        ]);
        $invoiceResponse->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");

        tenancy()->initialize($tenant);

        try {
            $invoice = DB::connection('tenant')->table('accounting_invoices')
                ->where('reference', 'AR-INV-001')
                ->first();

            $this->assertNotNull($invoice);
            $this->assertSame('draft', $invoice->status);
            $this->assertSame(190.0, (float) $invoice->subtotal);
            $this->assertSame(200.0, (float) $invoice->total_amount);
            $this->assertDatabaseHas('accounting_invoice_lines', [
                'accounting_invoice_id' => $invoice->id,
                'description' => 'Consulting service',
                'account_code' => '4100 Service Labor Revenue',
            ], 'tenant');
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $postResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/invoices/{$invoice->id}/post?workspace_product=accounting", [
            'workspace_product' => 'accounting',
        ]);
        $postResponse->assertRedirect();

        tenancy()->initialize($tenant);

        try {
            $postedInvoice = DB::connection('tenant')->table('accounting_invoices')
                ->where('id', $invoice->id)
                ->first();
            $event = DB::connection('tenant')->table('accounting_events')
                ->where('id', $postedInvoice->accounting_event_id)
                ->first();
            $journal = DB::connection('tenant')->table('journal_entries')
                ->where('id', $postedInvoice->journal_entry_id)
                ->first();
            $journalLines = DB::connection('tenant')->table('journal_entry_lines')
                ->where('journal_entry_id', $postedInvoice->journal_entry_id)
                ->orderBy('id')
                ->get();

            $this->assertSame('posted', $postedInvoice->status);
            $this->assertNotNull($event);
            $this->assertSame('customer_invoice.posted', $event->event_type);
            $this->assertSame('journal_posted', $event->status);
            $this->assertSame('posted', $journal->status);
            $this->assertSame(200.0, (float) $journal->debit_total);
            $this->assertSame(200.0, (float) $journal->credit_total);
            $this->assertSame('1100 Accounts Receivable', $journalLines[0]->account_code);
            $this->assertSame(200.0, (float) $journalLines[0]->debit);
            $this->assertSame('4100 Service Labor Revenue', $journalLines[1]->account_code);
            $this->assertSame(190.0, (float) $journalLines[1]->credit);
            $this->assertSame('2100 VAT Output Payable', $journalLines[2]->account_code);
            $this->assertSame(10.0, (float) $journalLines[2]->credit);
            $this->assertDatabaseHas('accounting_audit_entries', [
                'event_type' => 'customer_invoice_posted',
            ], 'tenant');
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $ledgerResponse = $this->get("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting");
        $ledgerResponse->assertOk();
        $ledgerResponse->assertSee('Customer Invoices', false);
        $ledgerResponse->assertSee('Invoice Customer', false);
        $ledgerResponse->assertSee('POSTED', false);

        $paymentResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/payments?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'accounting_event_id' => $postedInvoice->accounting_event_id,
            'payment_date' => '2026-10-05',
            'amount' => 200,
            'method' => 'bank_transfer',
            'payer_name' => 'Invoice Customer',
            'reference' => 'AR-PAY-001',
            'currency' => 'USD',
        ]);
        $paymentResponse->assertRedirect();

        tenancy()->initialize($tenant);

        try {
            $paidInvoice = DB::connection('tenant')->table('accounting_invoices')
                ->where('id', $invoice->id)
                ->first();
            $paidEvent = DB::connection('tenant')->table('accounting_events')
                ->where('id', $paidInvoice->accounting_event_id)
                ->first();

            $this->assertSame('paid', $paidInvoice->status);
            $this->assertSame('paid', $paidEvent->status);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $statementResponse = $this->get("http://{$domain}/automotive/admin/general-ledger/customer-statement?workspace_product=accounting&customer=Invoice%20Customer");
        $statementResponse->assertOk();
        $statementResponse->assertSee('Invoice Customer', false);
        $statementResponse->assertSee($invoice->invoice_number, false);
        $statementResponse->assertSee('AR-PAY-001', false);
        $statementResponse->assertSee('0.00', false);

        $invoicePrint = $this->get("http://{$domain}/automotive/admin/general-ledger/accounting-events/{$postedInvoice->accounting_event_id}/invoice?workspace_product=accounting");
        $invoicePrint->assertOk();
        $invoicePrint->assertSee($invoice->invoice_number, false);
        $invoicePrint->assertSee('Consulting service', false);
        $invoicePrint->assertSee('Open Balance', false);
    }

    public function test_accounting_runtime_enforces_account_catalog_period_locks_and_exports_reports(): void
    {
        [$tenant, $domain, $email, $password] = $this->prepareTenantWorkspace('active');
        $this->attachAccountingWorkspaceToTenant($tenant);

        $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $email,
            'password' => $password,
        ])->assertRedirect("http://{$domain}/workspace/admin/dashboard");

        $accountResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/accounts?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'code' => '1150 Clearing Account',
            'name' => 'Clearing Account',
            'type' => 'asset',
            'normal_balance' => 'debit',
        ]);
        $accountResponse->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");

        $lockResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/period-locks?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
            'notes' => 'Month closed',
        ]);
        $lockResponse->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");

        $overlappingLockResponse = $this->from("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting")
            ->post("http://{$domain}/automotive/admin/general-ledger/period-locks?workspace_product=accounting", [
                'workspace_product' => 'accounting',
                'period_start' => '2026-04-15',
                'period_end' => '2026-05-15',
                'notes' => 'Overlapping close',
            ]);
        $overlappingLockResponse->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");
        $overlappingLockResponse->assertSessionHasErrors('period_start');

        $controlsResponse = $this->get("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting");
        $controlsResponse->assertOk();
        $controlsResponse->assertSee('Posting Controls', false);
        $controlsResponse->assertSee('Latest Lock', false);
        $controlsResponse->assertSee('Locked Periods', false);

        $lockedManualResponse = $this->from("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting")
            ->post("http://{$domain}/automotive/admin/general-ledger/manual-journal-entries?workspace_product=accounting", [
                'workspace_product' => 'accounting',
                'entry_date' => '2026-04-21',
                'currency' => 'USD',
                'memo' => 'Locked period entry',
                'lines' => [
                    ['account_code' => '1150 Clearing Account', 'account_name' => 'Clearing Account', 'debit' => 25, 'credit' => 0],
                    ['account_code' => '4100 Service Revenue', 'account_name' => 'Service Revenue', 'debit' => 0, 'credit' => 25],
                ],
            ]);
        $lockedManualResponse->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");
        $lockedManualResponse->assertSessionHasErrors('entry_date');
        $lockedManualResponse->assertSessionHasErrors([
            'entry_date' => 'Accounting period is locked for 2026-04-21; creating manual journal entries is not allowed. Use a reversal or correction entry in an open period.',
        ]);

        $openManualResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/manual-journal-entries?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'entry_date' => '2026-05-02',
            'currency' => 'USD',
            'memo' => 'Exportable manual journal',
            'lines' => [
                ['account_code' => '1150 Clearing Account', 'account_name' => 'Clearing Account', 'debit' => 80, 'credit' => 0],
                ['account_code' => '4100 Service Revenue', 'account_name' => 'Service Revenue', 'debit' => 0, 'credit' => 80],
            ],
        ]);
        $openManualResponse->assertRedirect();

        $journalCsv = $this->get("http://{$domain}/automotive/admin/general-ledger/exports/journal-entries?workspace_product=accounting&format=csv");
        $journalCsv->assertOk();
        $journalCsvContent = $journalCsv->streamedContent();
        $this->assertStringContainsString('Journal Number', $journalCsvContent);
        $this->assertStringContainsString('Exportable manual journal', $journalCsvContent);

        $trialCsv = $this->get("http://{$domain}/automotive/admin/general-ledger/exports/trial-balance?workspace_product=accounting&format=csv");
        $trialCsv->assertOk();
        $this->assertStringContainsString('1150 Clearing Account', $trialCsv->streamedContent());

        $revenueCsv = $this->get("http://{$domain}/automotive/admin/general-ledger/exports/revenue-summary?workspace_product=accounting&format=csv");
        $revenueCsv->assertOk();
        $this->assertStringContainsString('4100 Service Revenue', $revenueCsv->streamedContent());

        $printResponse = $this->get("http://{$domain}/automotive/admin/general-ledger/exports/journal-entries?workspace_product=accounting&format=print");
        $printResponse->assertOk();
        $printResponse->assertSee('Journal Entries', false);
        $printResponse->assertSee('Generated', false);

        $trialPrint = $this->get("http://{$domain}/automotive/admin/general-ledger/exports/trial-balance?workspace_product=accounting&format=print&date_from=2026-05-01&date_to=2026-05-31");
        $trialPrint->assertOk();
        $trialPrint->assertSee('Trial Balance', false);
        $trialPrint->assertSee('Period 2026-05-01 to 2026-05-31', false);

        $revenuePrint = $this->get("http://{$domain}/automotive/admin/general-ledger/exports/revenue-summary?workspace_product=accounting&format=print");
        $revenuePrint->assertOk();
        $revenuePrint->assertSee('Revenue Summary', false);

        $receivablesAgingCsv = $this->get("http://{$domain}/automotive/admin/general-ledger/exports/receivables-aging?workspace_product=accounting&format=csv");
        $receivablesAgingCsv->assertOk();
        $receivablesAgingContent = $receivablesAgingCsv->streamedContent();
        $this->assertStringContainsString('Bucket,"Open Count","Open Amount","Total Open","Overdue Total"', $receivablesAgingContent);
        $this->assertStringContainsString('Current', $receivablesAgingContent);

        $payablesAgingPrint = $this->get("http://{$domain}/automotive/admin/general-ledger/exports/payables-aging?workspace_product=accounting&format=print");
        $payablesAgingPrint->assertOk();
        $payablesAgingPrint->assertSee('Payables Aging', false);
        $payablesAgingPrint->assertSee('Overdue Total', false);

        $bankCsv = $this->get("http://{$domain}/automotive/admin/general-ledger/exports/bank-reconciliation?workspace_product=accounting&format=csv&date_from=2026-05-01&date_to=2026-05-31");
        $bankCsv->assertOk();
        $bankContent = $bankCsv->streamedContent();
        $this->assertStringContainsString('Section,Number,Date,Account,Status,Reference,"Bank Match",Count,Amount,Currency', $bankContent);
        $this->assertStringContainsString('Posted Batches', $bankContent);

        $bankPrint = $this->get("http://{$domain}/automotive/admin/general-ledger/exports/bank-reconciliation?workspace_product=accounting&format=print&date_from=2026-05-01&date_to=2026-05-31");
        $bankPrint->assertOk();
        $bankPrint->assertSee('Bank Reconciliation Report', false);
        $bankPrint->assertSee('Period 2026-05-01 to 2026-05-31', false);

        $reconciliationSummaryCsv = $this->get("http://{$domain}/automotive/admin/general-ledger/exports/reconciliation-summary?workspace_product=accounting&format=csv");
        $reconciliationSummaryCsv->assertOk();
        $reconciliationSummaryContent = $reconciliationSummaryCsv->streamedContent();
        $this->assertStringContainsString('Metric,Count,Amount,"Period Start","Period End"', $reconciliationSummaryContent);
        $this->assertStringContainsString('Unreconciled Receipts', $reconciliationSummaryContent);

        tenancy()->initialize($tenant);

        try {
            $this->assertDatabaseHas('accounting_accounts', [
                'code' => '1150 Clearing Account',
            ], 'tenant');
            $periodLock = DB::connection('tenant')->table('accounting_period_locks')
                ->where('status', 'locked')
                ->first();
            $this->assertNotNull($periodLock);
            $this->assertStringStartsWith('2026-04-01', $periodLock->period_start);
            $this->assertStringStartsWith('2026-04-30', $periodLock->period_end);
            $this->assertDatabaseHas('accounting_audit_entries', [
                'event_type' => 'manual_journal_created',
            ], 'tenant');
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }
    }

    public function test_accounting_runtime_can_post_inventory_valuation_movements(): void
    {
        [$tenant, $domain, $email, $password] = $this->prepareTenantWorkspace('active');
        $this->attachAccountingWorkspaceToTenant($tenant);
        $this->attachPartsWorkspaceToTenant($tenant);

        [$branchId, $productId] = $this->seedTenantStock($tenant, [
            [
                'branch' => ['name' => 'Main Branch', 'code' => 'MAIN'],
                'product' => ['name' => 'Oil Filter', 'sku' => 'OF-VAL'],
                'quantity' => 5,
            ],
        ]);

        tenancy()->initialize($tenant);

        try {
            $movementId = DB::connection('tenant')->table('stock_movements')->insertGetId([
                'branch_id' => $branchId,
                'product_id' => $productId,
                'type' => 'adjustment_out',
                'quantity' => 2,
                'reference_type' => null,
                'reference_id' => null,
                'notes' => 'Inventory count correction',
                'created_by' => null,
                'movement_date' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $email,
            'password' => $password,
        ])->assertRedirect("http://{$domain}/workspace/admin/dashboard");

        $ledgerResponse = $this->get("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting");
        $ledgerResponse->assertOk();
        $ledgerResponse->assertSee('Inventory Valuation Review', false);
        $ledgerResponse->assertSee('Costing method: Current product cost at posting time. FIFO and weighted average costing are not enabled.', false);
        $ledgerResponse->assertSee('Inventory Asset Account', false);
        $ledgerResponse->assertSee('COGS Account', false);
        $ledgerResponse->assertSee('Valuation Method: Current product cost at posting time', false);
        $ledgerResponse->assertSee('Source: Current stock item cost price', false);
        $ledgerResponse->assertSee('Oil Filter', false);
        $ledgerResponse->assertSee('Post Inventory Valuation', false);

        $postResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/inventory-movements/{$movementId}/post?workspace_product=accounting", [
            'workspace_product' => 'accounting',
        ]);

        $postResponse->assertRedirect();

        tenancy()->initialize($tenant);

        try {
            $journal = DB::connection('tenant')->table('journal_entries')
                ->where('source_type', \App\Models\StockMovement::class)
                ->where('source_id', $movementId)
                ->first();

            $this->assertNotNull($journal);
            $this->assertStringStartsWith('INV-', $journal->journal_number);
            $this->assertSame(20.0, (float) $journal->debit_total);
            $this->assertSame(20.0, (float) $journal->credit_total);

            $lines = DB::connection('tenant')->table('journal_entry_lines')
                ->where('journal_entry_id', $journal->id)
                ->orderBy('id')
                ->get();

            $this->assertCount(2, $lines);
            $this->assertSame('5100 Inventory Adjustment Expense', $lines[0]->account_code);
            $this->assertSame(20.0, (float) $lines[0]->debit);
            $this->assertSame('1300 Inventory Asset', $lines[1]->account_code);
            $this->assertSame(20.0, (float) $lines[1]->credit);

            $handoff = DB::connection('tenant')->table('workspace_integration_handoffs')
                ->where('integration_key', 'parts-accounting')
                ->where('event_name', 'stock_movement.valued')
                ->where('source_type', \App\Models\StockMovement::class)
                ->where('source_id', $movementId)
                ->first();

            $this->assertNotNull($handoff);
            $this->assertSame('posted', $handoff->status);
            $this->assertSame(\App\Models\JournalEntry::class, $handoff->target_type);
            $this->assertSame((int) $journal->id, (int) $handoff->target_id);

            $payload = json_decode((string) $handoff->payload, true);
            $this->assertSame('current_product_cost', $payload['valuation_method'] ?? null);
            $this->assertSame('products.cost_price', $payload['valuation_source'] ?? null);
            $this->assertSame(10.0, (float) ($payload['unit_cost'] ?? 0));
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }
    }

    public function test_accounting_inventory_valuation_rejects_transfer_movements(): void
    {
        [$tenant, $domain, $email, $password] = $this->prepareTenantWorkspace('active');
        $this->attachAccountingWorkspaceToTenant($tenant);
        $this->attachPartsWorkspaceToTenant($tenant);

        [$branchId, $productId] = $this->seedTenantStock($tenant, [
            [
                'branch' => ['name' => 'Transfer Branch', 'code' => 'TRF'],
                'product' => ['name' => 'Transfer Filter', 'sku' => 'TF-BLOCK'],
                'quantity' => 5,
            ],
        ]);

        tenancy()->initialize($tenant);

        try {
            $movementId = DB::connection('tenant')->table('stock_movements')->insertGetId([
                'branch_id' => $branchId,
                'product_id' => $productId,
                'type' => 'transfer_out',
                'quantity' => 2,
                'reference_type' => null,
                'reference_id' => null,
                'notes' => 'Operational transfer only',
                'created_by' => null,
                'movement_date' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $email,
            'password' => $password,
        ])->assertRedirect("http://{$domain}/workspace/admin/dashboard");

        $ledgerResponse = $this->get("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting");
        $ledgerResponse->assertOk();
        $ledgerResponse->assertDontSee('Transfer Filter', false);

        $postResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/inventory-movements/{$movementId}/post?workspace_product=accounting", [
            'workspace_product' => 'accounting',
        ]);

        $postResponse->assertSessionHasErrors('stock_movement');

        tenancy()->initialize($tenant);

        try {
            $this->assertDatabaseMissing('journal_entries', [
                'source_type' => \App\Models\StockMovement::class,
                'source_id' => $movementId,
            ], 'tenant');

            $handoff = DB::connection('tenant')->table('workspace_integration_handoffs')
                ->where('source_type', \App\Models\StockMovement::class)
                ->where('source_id', $movementId)
                ->first();

            $this->assertNotNull($handoff);
            $this->assertSame('skipped', $handoff->status);
            $this->assertStringContainsString('Transfer movements are operational stock logistics only', (string) $handoff->error_message);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }
    }

    public function test_accounting_runtime_uses_configured_inventory_policy_accounts(): void
    {
        [$tenant, $domain, $email, $password] = $this->prepareTenantWorkspace('active');
        $this->attachAccountingWorkspaceToTenant($tenant);
        $this->attachPartsWorkspaceToTenant($tenant);

        [$branchId, $productId] = $this->seedTenantStock($tenant, [
            [
                'branch' => ['name' => 'Main Branch', 'code' => 'MAIN'],
                'product' => ['name' => 'Air Filter', 'sku' => 'AF-POL'],
                'quantity' => 5,
            ],
        ]);

        tenancy()->initialize($tenant);

        try {
            $movementId = DB::connection('tenant')->table('stock_movements')->insertGetId([
                'branch_id' => $branchId,
                'product_id' => $productId,
                'type' => 'adjustment_out',
                'quantity' => 1,
                'reference_type' => null,
                'reference_id' => null,
                'notes' => 'Policy account test',
                'created_by' => null,
                'movement_date' => '2026-05-03',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $email,
            'password' => $password,
        ])->assertRedirect("http://{$domain}/workspace/admin/dashboard");

        foreach ([
            ['1310 Workshop Inventory', 'Workshop Inventory', 'asset', 'debit'],
            ['3910 Inventory Offset', 'Inventory Offset', 'equity', 'credit'],
            ['5110 Inventory Shrinkage', 'Inventory Shrinkage', 'expense', 'debit'],
            ['5010 Workshop COGS', 'Workshop COGS', 'expense', 'debit'],
        ] as [$code, $name, $type, $normalBalance]) {
            $this->post("http://{$domain}/automotive/admin/general-ledger/accounts?workspace_product=accounting", [
                'workspace_product' => 'accounting',
                'code' => $code,
                'name' => $name,
                'type' => $type,
                'normal_balance' => $normalBalance,
            ])->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");
        }

        $policyResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/policies?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'code' => 'custom_inventory_policy',
            'name' => 'Custom Inventory Policy',
            'currency' => 'USD',
            'inventory_asset_account' => '1310 Workshop Inventory',
            'inventory_adjustment_offset_account' => '3910 Inventory Offset',
            'inventory_adjustment_expense_account' => '5110 Inventory Shrinkage',
            'cogs_account' => '5010 Workshop COGS',
            'is_default' => 1,
        ]);
        $policyResponse->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");

        $postResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/inventory-movements/{$movementId}/post?workspace_product=accounting", [
            'workspace_product' => 'accounting',
        ]);
        $postResponse->assertRedirect();

        tenancy()->initialize($tenant);

        try {
            $journal = DB::connection('tenant')->table('journal_entries')
                ->where('source_type', \App\Models\StockMovement::class)
                ->where('source_id', $movementId)
                ->first();
            $this->assertNotNull($journal);

            $lines = DB::connection('tenant')->table('journal_entry_lines')
                ->where('journal_entry_id', $journal->id)
                ->orderBy('id')
                ->get();

            $this->assertSame('5110 Inventory Shrinkage', $lines[0]->account_code);
            $this->assertSame('1310 Workshop Inventory', $lines[1]->account_code);
            $this->assertDatabaseHas('accounting_audit_entries', [
                'event_type' => 'accounting_policy_changed',
            ], 'tenant');
            $this->assertDatabaseHas('accounting_audit_entries', [
                'event_type' => 'inventory_valuation_posted',
            ], 'tenant');
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }
    }

    public function test_accounting_end_to_end_production_acceptance_workflows(): void
    {
        [$tenant, $domain, $email, $password] = $this->prepareTenantWorkspace('active');
        $this->attachPartsWorkspaceToTenant($tenant);
        $this->attachAccountingWorkspaceToTenant($tenant);

        [$branchId, $productId] = $this->seedTenantStock($tenant, [
            [
                'branch' => ['name' => 'Main Branch', 'code' => 'MAIN'],
                'product' => ['name' => 'Acceptance Brake Pad', 'sku' => 'ACC-BP-1'],
                'quantity' => 10,
            ],
        ]);

        tenancy()->initialize($tenant);

        try {
            $approverEmail = 'acceptance-approver-' . uniqid() . '@example.test';
            $approverPassword = 'secret-pass';

            User::query()->create([
                'name' => 'Acceptance Controller',
                'email' => $approverEmail,
                'password' => bcrypt($approverPassword),
                'accounting_role' => 'controller',
                'accounting_permissions' => [
                    'accounting.manual_journals.approve',
                    'accounting.manual_journals.create',
                    'accounting.manual_journals.post',
                    'accounting.journals.reverse',
                    'accounting.periods.lock',
                    'accounting.reports.export',
                ],
            ]);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $email,
            'password' => $password,
        ])->assertRedirect("http://{$domain}/workspace/admin/dashboard");

        $this->get("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting")
            ->assertOk()
            ->assertSee('Accounting Workspace Navigation', false);

        $this->post("http://{$domain}/automotive/admin/workshop-operations/customers?workspace_product=automotive_service", [
            'workspace_product' => 'automotive_service',
            'name' => 'Acceptance Customer',
            'phone' => '0501002000',
            'email' => 'acceptance-customer@example.test',
        ])->assertRedirect("http://{$domain}/workspace/admin/workshop-operations?workspace_product=automotive_service");

        tenancy()->initialize($tenant);

        try {
            $customer = DB::connection('tenant')->table('customers')->where('name', 'Acceptance Customer')->first();
            $this->assertNotNull($customer);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $this->post("http://{$domain}/automotive/admin/workshop-operations/vehicles?workspace_product=automotive_service", [
            'workspace_product' => 'automotive_service',
            'customer_id' => $customer->id,
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2024,
            'plate_number' => 'ACC-100',
        ])->assertRedirect("http://{$domain}/workspace/admin/workshop-operations?workspace_product=automotive_service");

        tenancy()->initialize($tenant);

        try {
            $vehicle = DB::connection('tenant')->table('vehicles')->where('plate_number', 'ACC-100')->first();
            $this->assertNotNull($vehicle);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $this->post("http://{$domain}/automotive/admin/workshop-operations/work-orders?workspace_product=automotive_service", [
            'workspace_product' => 'automotive_service',
            'branch_id' => $branchId,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'title' => 'Acceptance service order',
            'notes' => 'Production acceptance workflow',
        ])->assertRedirect("http://{$domain}/workspace/admin/workshop-operations?workspace_product=automotive_service");

        tenancy()->initialize($tenant);

        try {
            $workOrder = DB::connection('tenant')->table('work_orders')->where('title', 'Acceptance service order')->first();
            $this->assertNotNull($workOrder);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $this->post("http://{$domain}/automotive/admin/workshop-operations/consume-part?workspace_product=automotive_service", [
            'workspace_product' => 'automotive_service',
            'work_order_id' => $workOrder->id,
            'branch_id' => $branchId,
            'product_id' => $productId,
            'quantity' => 2,
            'notes' => 'Acceptance stock consumption',
        ])->assertRedirect("http://{$domain}/workspace/admin/workshop-operations?workspace_product=automotive_service");

        $this->post("http://{$domain}/automotive/admin/workshop-operations/work-orders/{$workOrder->id}/labor-lines?workspace_product=automotive_service", [
            'workspace_product' => 'automotive_service',
            'description' => 'Acceptance labor',
            'quantity' => 1,
            'unit_price' => 150,
            'notes' => 'Acceptance labor line',
        ])->assertRedirect("http://{$domain}/workspace/admin/workshop-operations/work-orders/{$workOrder->id}?workspace_product=automotive_service");

        $this->post("http://{$domain}/automotive/admin/workshop-operations/work-orders/{$workOrder->id}/status?workspace_product=automotive_service", [
            'workspace_product' => 'automotive_service',
            'status' => 'completed',
        ])->assertRedirect("http://{$domain}/workspace/admin/workshop-operations/work-orders/{$workOrder->id}?workspace_product=automotive_service");

        tenancy()->initialize($tenant);

        try {
            $accountingEvent = DB::connection('tenant')->table('accounting_events')
                ->where('reference_type', \App\Models\WorkOrder::class)
                ->where('reference_id', $workOrder->id)
                ->first();
            $this->assertNotNull($accountingEvent);
            $this->assertSame('posted', $accountingEvent->status);
            $this->assertSame(190.0, (float) $accountingEvent->total_amount);

            $stockMovement = DB::connection('tenant')->table('stock_movements')
                ->where('reference_type', \App\Models\WorkOrder::class)
                ->where('reference_id', $workOrder->id)
                ->first();
            $this->assertNotNull($stockMovement);

            $handoff = DB::connection('tenant')->table('workspace_integration_handoffs')
                ->where('integration_key', 'automotive-accounting')
                ->where('source_id', $workOrder->id)
                ->first();
            $this->assertNotNull($handoff);
            $this->assertSame('posted', $handoff->status);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $this->post("http://{$domain}/automotive/admin/general-ledger/accounting-events/{$accountingEvent->id}/post?workspace_product=accounting", [
            'workspace_product' => 'accounting',
        ])->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");

        $this->post("http://{$domain}/automotive/admin/general-ledger/inventory-movements/{$stockMovement->id}/post?workspace_product=accounting", [
            'workspace_product' => 'accounting',
        ])->assertRedirect();

        $this->post("http://{$domain}/automotive/admin/general-ledger/payments?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'accounting_event_id' => $accountingEvent->id,
            'payment_date' => '2026-09-02',
            'amount' => 190,
            'method' => 'cash',
            'payer_name' => 'Acceptance Customer',
            'reference' => 'ACC-RCPT-1',
            'currency' => 'USD',
        ])->assertRedirect();

        tenancy()->initialize($tenant);

        try {
            $payment = DB::connection('tenant')->table('accounting_payments')
                ->where('reference', 'ACC-RCPT-1')
                ->first();
            $this->assertNotNull($payment);
            $this->assertSame('posted', $payment->status);

            $workOrderJournal = DB::connection('tenant')->table('journal_entries')
                ->where('accounting_event_id', $accountingEvent->id)
                ->first();
            $this->assertNotNull($workOrderJournal);

            $inventoryJournal = DB::connection('tenant')->table('journal_entries')
                ->where('source_type', \App\Models\StockMovement::class)
                ->where('source_id', $stockMovement->id)
                ->first();
            $this->assertNotNull($inventoryJournal);
            $this->assertSame(20.0, (float) $inventoryJournal->debit_total);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $this->post("http://{$domain}/automotive/admin/general-ledger/deposit-batches?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'payment_ids' => [$payment->id],
            'deposit_date' => '2026-09-03',
            'currency' => 'USD',
            'reference' => 'ACC-DEP-1',
            'notes' => 'Acceptance deposit',
        ])->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");

        $taxRateResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/tax-rates?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'code' => 'acceptance_vat_5',
            'name' => 'Acceptance VAT 5',
            'rate' => 5,
            'input_tax_account' => '1410 VAT Input Receivable',
            'output_tax_account' => '2100 VAT Output Payable',
            'is_default' => '1',
            'is_active' => '1',
        ]);
        $taxRateResponse->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");

        tenancy()->initialize($tenant);

        try {
            $taxRate = DB::connection('tenant')->table('accounting_tax_rates')
                ->where('code', 'acceptance_vat_5')
                ->first();
            $this->assertNotNull($taxRate);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $this->post("http://{$domain}/automotive/admin/general-ledger/vendor-bills?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'bill_date' => '2026-09-04',
            'due_date' => '2026-09-14',
            'supplier_name' => 'Acceptance Vendor',
            'reference' => 'ACC-BILL-1',
            'currency' => 'USD',
            'amount' => 105,
            'accounting_tax_rate_id' => $taxRate->id,
            'tax_amount' => 5,
            'expense_account' => '5200 Operating Expense',
            'payable_account' => '2000 Accounts Payable',
            'tax_account' => '1410 VAT Input Receivable',
        ])->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");

        tenancy()->initialize($tenant);

        try {
            $bill = DB::connection('tenant')->table('accounting_vendor_bills')
                ->where('reference', 'ACC-BILL-1')
                ->first();
            $this->assertNotNull($bill);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $this->post("http://{$domain}/automotive/admin/general-ledger/vendor-bills/{$bill->id}/post?workspace_product=accounting", [
            'workspace_product' => 'accounting',
        ])->assertRedirect();

        $this->post("http://{$domain}/automotive/admin/general-ledger/vendor-bill-payments?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'accounting_vendor_bill_id' => $bill->id,
            'payment_date' => '2026-09-05',
            'amount' => 105,
            'method' => 'bank_transfer',
            'reference' => 'ACC-VPAY-1',
            'currency' => 'USD',
        ])->assertRedirect();

        $manualResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/manual-journal-entries?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'entry_date' => '2026-09-06',
            'currency' => 'USD',
            'memo' => 'Acceptance high-risk journal',
            'lines' => [
                ['account_code' => '1100 Accounts Receivable', 'account_name' => 'Accounts Receivable', 'debit' => 6000, 'credit' => 0],
                ['account_code' => '4100 Service Revenue', 'account_name' => 'Service Revenue', 'debit' => 0, 'credit' => 6000],
            ],
        ]);
        $manualResponse->assertRedirect();

        tenancy()->initialize($tenant);

        try {
            $manualJournal = DB::connection('tenant')->table('journal_entries')
                ->where('memo', 'Acceptance high-risk journal')
                ->first();
            $this->assertNotNull($manualJournal);
            $this->assertSame('pending_approval', $manualJournal->status);

            $vendorPayment = DB::connection('tenant')->table('accounting_vendor_bill_payments')
                ->where('reference', 'ACC-VPAY-1')
                ->first();
            $this->assertNotNull($vendorPayment);

            $postedBill = DB::connection('tenant')->table('accounting_vendor_bills')
                ->where('id', $bill->id)
                ->first();
            $this->assertSame('paid', $postedBill->status);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        Auth::guard('automotive_admin')->logout();
        $this->flushSession();

        $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $approverEmail,
            'password' => $approverPassword,
        ])->assertRedirect("http://{$domain}/workspace/admin/dashboard");

        $this->post("http://{$domain}/automotive/admin/general-ledger/journal-entries/{$manualJournal->id}/approve?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'approval_notes' => 'Production acceptance approved',
        ])->assertRedirect();

        $this->post("http://{$domain}/automotive/admin/general-ledger/journal-entries/{$manualJournal->id}/post-approved?workspace_product=accounting", [
            'workspace_product' => 'accounting',
        ])->assertRedirect();

        $this->post("http://{$domain}/automotive/admin/general-ledger/journal-entries/{$manualJournal->id}/reverse?workspace_product=accounting", [
            'workspace_product' => 'accounting',
        ])->assertRedirect();

        $this->post("http://{$domain}/automotive/admin/general-ledger/period-locks?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'notes' => 'Acceptance closed period',
        ])->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");

        $blockedPosting = $this->from("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting")
            ->post("http://{$domain}/automotive/admin/general-ledger/manual-journal-entries?workspace_product=accounting", [
                'workspace_product' => 'accounting',
                'entry_date' => '2026-10-15',
                'currency' => 'USD',
                'memo' => 'Blocked closed period posting',
                'lines' => [
                    ['account_code' => '1100 Accounts Receivable', 'account_name' => 'Accounts Receivable', 'debit' => 50, 'credit' => 0],
                    ['account_code' => '4100 Service Revenue', 'account_name' => 'Service Revenue', 'debit' => 0, 'credit' => 50],
                ],
            ]);
        $blockedPosting->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");
        $blockedPosting->assertSessionHasErrors('entry_date');

        tenancy()->initialize($tenant);

        try {
            $depositBatch = DB::connection('tenant')->table('accounting_deposit_batches')
                ->where('reference', 'ACC-DEP-1')
                ->first();
            $this->assertNotNull($depositBatch);
            $this->assertSame('posted', $depositBatch->status);

            $depositedPayment = DB::connection('tenant')->table('accounting_payments')
                ->where('id', $payment->id)
                ->first();
            $this->assertSame('deposited', $depositedPayment->reconciliation_status);

            $postedManualJournal = DB::connection('tenant')->table('journal_entries')
                ->where('id', $manualJournal->id)
                ->first();
            $this->assertSame('reversed', $postedManualJournal->status);

            $reversalJournal = DB::connection('tenant')->table('journal_entries')
                ->where('source_type', 'journal_reversal')
                ->where('source_id', $manualJournal->id)
                ->first();
            $this->assertNotNull($reversalJournal);

            $this->assertDatabaseHas('accounting_audit_entries', [
                'event_type' => 'journal_reversed',
            ], 'tenant');
            $this->assertDatabaseMissing('journal_entries', [
                'memo' => 'Blocked closed period posting',
            ], 'tenant');
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $ledgerResponse = $this->get("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting");
        $ledgerResponse->assertOk();
        $ledgerResponse->assertSee('Payment Reconciliation', false);
        $ledgerResponse->assertSee('ACC-DEP-1', false);
        $ledgerResponse->assertSee('Payables Aging', false);
        $ledgerResponse->assertSee('Tax And VAT Settings', false);
        $ledgerResponse->assertSee('Accounting Audit Timeline', false);
        $ledgerResponse->assertSee('Posting Controls', false);

        $trialBalance = $this->get("http://{$domain}/automotive/admin/general-ledger/exports/trial-balance?workspace_product=accounting&format=csv");
        $trialBalance->assertOk();
        $trialBalanceContent = $trialBalance->streamedContent();
        $this->assertStringContainsString('1100 Accounts Receivable', $trialBalanceContent);
        $this->assertStringContainsString('2000 Accounts Payable', $trialBalanceContent);

        $taxSummary = $this->get("http://{$domain}/automotive/admin/general-ledger/exports/tax-summary?workspace_product=accounting&format=csv");
        $taxSummary->assertOk();
        $taxSummaryContent = $taxSummary->streamedContent();
        $this->assertStringContainsString('Input Tax Total', $taxSummaryContent);
        $this->assertStringContainsString('Input,"1410 VAT Input Receivable"', $taxSummaryContent);
        $this->assertStringContainsString('Net Tax Payable', $taxSummaryContent);

        $payablesAging = $this->get("http://{$domain}/automotive/admin/general-ledger/exports/payables-aging?workspace_product=accounting&format=csv");
        $payablesAging->assertOk();
        $this->assertStringContainsString('Total Open', $payablesAging->streamedContent());

        $this->artisan("tenancy:verify-integration-readiness --tenant={$tenant->id}")
            ->expectsOutput('Workspace integration readiness verification passed.')
            ->assertExitCode(0);
    }

    public function test_workshop_operations_can_create_work_order_and_consume_spare_parts_stock(): void
    {
        [$tenant, $domain, $email, $password] = $this->prepareTenantWorkspace('active');
        $this->attachPartsWorkspaceToTenant($tenant);
        $this->attachAccountingWorkspaceToTenant($tenant);

        [$branchId, $productId] = $this->seedTenantStock($tenant, [
            [
                'branch' => ['name' => 'Main Branch', 'code' => 'MAIN'],
                'product' => ['name' => 'Brake Pad', 'sku' => 'BP-200'],
                'quantity' => 5,
            ],
        ]);

        $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $email,
            'password' => $password,
        ])->assertRedirect("http://{$domain}/workspace/admin/dashboard");

        $createCustomerResponse = $this->post("http://{$domain}/automotive/admin/workshop-operations/customers?workspace_product=automotive_service", [
            'workspace_product' => 'automotive_service',
            'name' => 'Ahmed Ali',
            'phone' => '0500000000',
            'email' => 'ahmed@example.test',
        ]);

        $createCustomerResponse->assertRedirect("http://{$domain}/workspace/admin/workshop-operations?workspace_product=automotive_service");

        tenancy()->initialize($tenant);

        try {
            $customer = DB::connection('tenant')->table('customers')->latest('id')->first();
            $this->assertNotNull($customer);
            $this->assertSame('Ahmed Ali', $customer->name);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $createVehicleResponse = $this->post("http://{$domain}/automotive/admin/workshop-operations/vehicles?workspace_product=automotive_service", [
            'workspace_product' => 'automotive_service',
            'customer_id' => $customer->id,
            'make' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2022,
            'plate_number' => 'DUB-12345',
            'vin' => 'VIN-123456',
        ]);

        $createVehicleResponse->assertRedirect("http://{$domain}/workspace/admin/workshop-operations?workspace_product=automotive_service");

        tenancy()->initialize($tenant);

        try {
            $vehicle = DB::connection('tenant')->table('vehicles')->latest('id')->first();
            $this->assertNotNull($vehicle);
            $this->assertSame('Toyota', $vehicle->make);
            $this->assertSame((int) $customer->id, (int) $vehicle->customer_id);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $createWorkOrderResponse = $this->post("http://{$domain}/automotive/admin/workshop-operations/work-orders?workspace_product=automotive_service", [
            'workspace_product' => 'automotive_service',
            'branch_id' => $branchId,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'title' => 'Brake service work order',
            'notes' => 'Customer brake maintenance',
        ]);

        $createWorkOrderResponse->assertRedirect("http://{$domain}/workspace/admin/workshop-operations?workspace_product=automotive_service");

        tenancy()->initialize($tenant);

        try {
            $workOrder = DB::connection('tenant')->table('work_orders')->latest('id')->first();
            $this->assertNotNull($workOrder);
            $this->assertSame('Brake service work order', $workOrder->title);
            $this->assertSame((int) $customer->id, (int) $workOrder->customer_id);
            $this->assertSame((int) $vehicle->id, (int) $workOrder->vehicle_id);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $response = $this->post("http://{$domain}/automotive/admin/workshop-operations/consume-part?workspace_product=automotive_service", [
            'workspace_product' => 'automotive_service',
            'work_order_id' => $workOrder->id,
            'branch_id' => $branchId,
            'product_id' => $productId,
            'quantity' => 2,
            'notes' => 'Used in brake service',
        ]);

        $response->assertRedirect("http://{$domain}/workspace/admin/workshop-operations?workspace_product=automotive_service");

        tenancy()->initialize($tenant);

        try {
            $remaining = DB::connection('tenant')->table('inventories')
                ->where('branch_id', $branchId)
                ->where('product_id', $productId)
                ->value('quantity');

            $this->assertSame('3', rtrim(rtrim((string) $remaining, '0'), '.'));

            $movement = DB::connection('tenant')->table('stock_movements')
                ->where('branch_id', $branchId)
                ->where('product_id', $productId)
                ->where('reference_type', \App\Models\WorkOrder::class)
                ->where('reference_id', $workOrder->id)
                ->latest('id')
                ->first();

            $this->assertNotNull($movement);
            $this->assertSame('adjustment_out', $movement->type);
            $this->assertSame('Used in brake service', $movement->notes);

            $progressWorkOrder = DB::connection('tenant')->table('work_orders')->where('id', $workOrder->id)->first();
            $this->assertSame('in_progress', $progressWorkOrder->status);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $followupResponse = $this->get("http://{$domain}/automotive/admin/workshop-operations?workspace_product=automotive_service");
        $followupResponse->assertOk();
        $followupResponse->assertSee('Recent Work Orders', false);
        $followupResponse->assertSee('Brake service work order', false);
        $followupResponse->assertSee('Ahmed Ali', false);
        $followupResponse->assertSee('Toyota Corolla', false);
        $followupResponse->assertSee('Recent Workshop Consumptions', false);
        $followupResponse->assertSee('Brake Pad', false);
        $followupResponse->assertSee('Used in brake service', false);
        $followupResponse->assertSee($workOrder->work_order_number, false);

        $workOrderShowResponse = $this->get("http://{$domain}/automotive/admin/workshop-operations/work-orders/{$workOrder->id}?workspace_product=automotive_service");
        $workOrderShowResponse->assertOk();
        $workOrderShowResponse->assertSee('Work Order Overview', false);
        $workOrderShowResponse->assertSee('Ahmed Ali', false);
        $workOrderShowResponse->assertSee('Toyota Corolla', false);
        $workOrderShowResponse->assertSee('Financial Summary', false);
        $workOrderShowResponse->assertSee('Work Order Lines', false);
        $workOrderShowResponse->assertSee('Consumed Spare Parts', false);
        $workOrderShowResponse->assertSee('Brake Pad', false);
        $workOrderShowResponse->assertSee('Used in brake service', false);

        $addLaborLineResponse = $this->post("http://{$domain}/automotive/admin/workshop-operations/work-orders/{$workOrder->id}/labor-lines?workspace_product=automotive_service", [
            'workspace_product' => 'automotive_service',
            'description' => 'Brake inspection labor',
            'quantity' => 1,
            'unit_price' => 150,
            'notes' => 'Initial workshop labor',
        ]);

        $addLaborLineResponse->assertRedirect("http://{$domain}/workspace/admin/workshop-operations/work-orders/{$workOrder->id}?workspace_product=automotive_service");

        tenancy()->initialize($tenant);

        try {
            $lines = DB::connection('tenant')->table('work_order_lines')
                ->where('work_order_id', $workOrder->id)
                ->orderBy('id')
                ->get();

            $this->assertCount(2, $lines);
            $this->assertSame('part', $lines[0]->line_type);
            $this->assertSame('labor', $lines[1]->line_type);
            $this->assertSame('Brake inspection labor', $lines[1]->description);
            $this->assertSame(150.0, (float) $lines[1]->total_price);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $statusResponse = $this->post("http://{$domain}/automotive/admin/workshop-operations/work-orders/{$workOrder->id}/status?workspace_product=automotive_service", [
            'workspace_product' => 'automotive_service',
            'status' => 'completed',
        ]);

        $statusResponse->assertRedirect("http://{$domain}/workspace/admin/workshop-operations/work-orders/{$workOrder->id}?workspace_product=automotive_service");

        tenancy()->initialize($tenant);

        try {
            $completedWorkOrder = DB::connection('tenant')->table('work_orders')->where('id', $workOrder->id)->first();
            $this->assertSame('completed', $completedWorkOrder->status);
            $this->assertNotNull($completedWorkOrder->closed_at);

            $accountingEvent = DB::connection('tenant')->table('accounting_events')
                ->where('reference_type', \App\Models\WorkOrder::class)
                ->where('reference_id', $workOrder->id)
                ->where('event_type', 'work_order_completed')
                ->latest('id')
                ->first();

            $this->assertNotNull($accountingEvent);
            $this->assertSame('posted', $accountingEvent->status);
            $this->assertSame(190.0, (float) $accountingEvent->total_amount);

            $handoff = DB::connection('tenant')->table('workspace_integration_handoffs')
                ->where('integration_key', 'automotive-accounting')
                ->where('event_name', 'work_order.completed')
                ->where('source_type', \App\Models\WorkOrder::class)
                ->where('source_id', $workOrder->id)
                ->first();

            $this->assertNotNull($handoff);
            $this->assertSame('posted', $handoff->status);
            $this->assertSame(\App\Models\AccountingEvent::class, $handoff->target_type);
            $this->assertSame((int) $accountingEvent->id, (int) $handoff->target_id);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $completedShowResponse = $this->get("http://{$domain}/automotive/admin/workshop-operations/work-orders/{$workOrder->id}?workspace_product=automotive_service");
        $completedShowResponse->assertOk();
        $completedShowResponse->assertSee('COMPLETED', false);
        $completedShowResponse->assertSee('Accounting Handoff', false);
        $completedShowResponse->assertSee('work_order_completed', false);
        $completedShowResponse->assertSee('Brake inspection labor', false);
        $completedShowResponse->assertSee('40.00', false);
        $completedShowResponse->assertSee('150.00', false);
        $completedShowResponse->assertSee('190.00', false);

        $generalLedgerResponse = $this->get("http://{$domain}/automotive/admin/general-ledger?workspace_product=accounting");
        $generalLedgerResponse->assertOk();
        $generalLedgerResponse->assertSee('Accounting Events Ledger', false);
        $generalLedgerResponse->assertSee('Integration Contracts', false);
        $generalLedgerResponse->assertSee('Integration Handoff Diagnostics', false);
        $generalLedgerResponse->assertSee('work_order.completed', false);
        $generalLedgerResponse->assertSee('POSTED', false);
        $generalLedgerResponse->assertSee($workOrder->work_order_number, false);
        $generalLedgerResponse->assertSee('190.00', false);
    }

    public function test_completed_work_order_records_skipped_handoff_when_accounting_is_not_active(): void
    {
        [$tenant, $domain, $email, $password] = $this->prepareTenantWorkspace('active');

        tenancy()->initialize($tenant);

        try {
            $branchId = DB::connection('tenant')->table('branches')->insertGetId([
                'name' => 'Main Branch',
                'code' => 'MAIN',
                'phone' => null,
                'email' => null,
                'address' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $email,
            'password' => $password,
        ])->assertRedirect("http://{$domain}/workspace/admin/dashboard");

        $this->post("http://{$domain}/automotive/admin/workshop-operations/work-orders?workspace_product=automotive_service", [
            'workspace_product' => 'automotive_service',
            'branch_id' => $branchId,
            'title' => 'Standalone service work order',
        ])->assertRedirect("http://{$domain}/workspace/admin/workshop-operations?workspace_product=automotive_service");

        tenancy()->initialize($tenant);

        try {
            $workOrder = DB::connection('tenant')->table('work_orders')->latest('id')->first();
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $this->post("http://{$domain}/automotive/admin/workshop-operations/work-orders/{$workOrder->id}/labor-lines?workspace_product=automotive_service", [
            'workspace_product' => 'automotive_service',
            'description' => 'Inspection labor',
            'quantity' => 1,
            'unit_price' => 150,
        ])->assertRedirect("http://{$domain}/workspace/admin/workshop-operations/work-orders/{$workOrder->id}?workspace_product=automotive_service");

        $this->post("http://{$domain}/automotive/admin/workshop-operations/work-orders/{$workOrder->id}/status?workspace_product=automotive_service", [
            'workspace_product' => 'automotive_service',
            'status' => 'completed',
        ])->assertRedirect("http://{$domain}/workspace/admin/workshop-operations/work-orders/{$workOrder->id}?workspace_product=automotive_service");

        tenancy()->initialize($tenant);

        try {
            $this->assertFalse(DB::connection('tenant')->table('accounting_events')->exists());

            $handoff = DB::connection('tenant')->table('workspace_integration_handoffs')
                ->where('integration_key', 'automotive-accounting')
                ->where('event_name', 'work_order.completed')
                ->where('source_type', \App\Models\WorkOrder::class)
                ->where('source_id', $workOrder->id)
                ->first();

            $this->assertNotNull($handoff);
            $this->assertSame('skipped', $handoff->status);
            $this->assertStringContainsString('Accounting product is not active', $handoff->error_message);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $this->attachAccountingWorkspaceToTenant($tenant);

        $retryResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/integration-handoffs/{$handoff->id}/retry?workspace_product=accounting", [
            'workspace_product' => 'accounting',
        ]);

        $retryResponse->assertRedirect("http://{$domain}/workspace/admin/general-ledger?workspace_product=accounting");

        tenancy()->initialize($tenant);

        try {
            $accountingEvent = DB::connection('tenant')->table('accounting_events')
                ->where('reference_type', \App\Models\WorkOrder::class)
                ->where('reference_id', $workOrder->id)
                ->where('event_type', 'work_order_completed')
                ->first();

            $this->assertNotNull($accountingEvent);
            $this->assertSame(150.0, (float) $accountingEvent->total_amount);

            $retriedHandoff = DB::connection('tenant')->table('workspace_integration_handoffs')
                ->where('id', $handoff->id)
                ->first();

            $this->assertSame('posted', $retriedHandoff->status);
            $this->assertSame(2, (int) $retriedHandoff->attempts);
            $this->assertSame(\App\Models\AccountingEvent::class, $retriedHandoff->target_type);
            $this->assertSame((int) $accountingEvent->id, (int) $retriedHandoff->target_id);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }
    }

    public function test_new_product_can_use_declared_integration_contract_without_product_specific_runtime_code(): void
    {
        [$tenant, $domain, $email, $password] = $this->prepareTenantWorkspace('active');
        $this->attachAccountingWorkspaceToTenant($tenant);

        $qualityProduct = Product::query()->create([
            'code' => 'quality_control',
            'name' => 'Quality Control System',
            'slug' => 'quality-control-system',
            'is_active' => true,
            'sort_order' => 4,
        ]);

        $qualityPlan = Plan::query()->create([
            'product_id' => $qualityProduct->id,
            'name' => 'Quality Pro',
            'slug' => 'quality-pro-' . uniqid(),
            'price' => 59,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $qualityProduct->id,
            'plan_id' => $qualityPlan->id,
            'status' => 'active',
            'gateway' => null,
        ]);

        \App\Models\AppSetting::query()->create([
            'group_key' => 'workspace_products',
            'key' => 'workspace_products.manifest_writeback_package.quality_control',
            'value' => json_encode([
                'family_key' => 'quality_control',
                'mode' => 'add',
                'family_payload' => [
                    'aliases' => ['quality', 'inspection'],
                    'experience' => ['title' => 'Quality control workspace'],
                    'sidebar_section' => [
                        'key' => 'quality-control',
                        'title' => 'Quality Control',
                        'items' => [
                            [
                                'key' => 'quality.inspections',
                                'label' => 'Inspections',
                                'route' => 'automotive.admin.dashboard',
                            ],
                        ],
                    ],
                    'integrations' => [
                        [
                            'key' => 'quality-accounting',
                            'requires_family' => 'accounting',
                            'title' => 'Quality can hand off inspection charges',
                            'description' => 'Inspection charges can flow into accounting using the shared handoff layer.',
                            'target_label' => 'Open Accounting',
                            'target_route' => 'automotive.admin.modules.general-ledger',
                            'events' => ['inspection.completed'],
                            'source_capabilities' => ['quality.inspections'],
                            'target_capabilities' => ['accounting.journal_posting'],
                            'payload_schema' => ['inspection_id' => 'integer', 'total_amount' => 'decimal'],
                        ],
                    ],
                ],
                'captured_at' => now()->toDateTimeString(),
            ], JSON_UNESCAPED_SLASHES),
            'value_type' => 'json',
        ]);

        $this->post("http://{$domain}/automotive/admin/login", [
            'email' => $email,
            'password' => $password,
        ])->assertRedirect("http://{$domain}/workspace/admin/dashboard");

        $dashboardResponse = $this->get("http://{$domain}/automotive/admin/dashboard");
        $dashboardResponse->assertOk();
        $dashboardResponse->assertSee('Quality Control', false);
        $dashboardResponse->assertSee('Quality Control System', false);

        tenancy()->initialize($tenant);

        try {
            $handoff = app(\App\Services\Tenancy\WorkspaceIntegrationHandoffService::class)->start([
                'integration_key' => 'quality-accounting',
                'event_name' => 'inspection.completed',
                'source_product' => 'quality_control',
                'target_product' => 'accounting',
                'source_type' => 'quality.inspection',
                'source_id' => 501,
                'payload' => [
                    'inspection_id' => 501,
                    'total_amount' => 75,
                ],
            ]);

            $this->assertSame('pending', $handoff->status);
            $this->assertSame('quality-accounting', $handoff->integration_key);
            $this->assertSame('inspection.completed', $handoff->event_name);
            $this->assertSame('quality_control', $handoff->source_product);
            $this->assertSame('accounting', $handoff->target_product);

            $this->expectException(\Illuminate\Validation\ValidationException::class);

            app(\App\Services\Tenancy\WorkspaceIntegrationHandoffService::class)->start([
                'integration_key' => 'quality-accounting',
                'event_name' => 'inspection.cancelled',
                'source_product' => 'quality_control',
                'target_product' => 'accounting',
                'source_type' => 'quality.inspection',
                'source_id' => 502,
            ]);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }
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

    /**
     * @return array{0: Tenant, 1: string, 2: string, 3: string}
     */
    protected function prepareAccountingOnlyTenantWorkspace(): array
    {
        $tenantId = 'accounting-only-' . uniqid();
        $domain = $tenantId . '.example.test';
        $password = 'secret-pass';
        $email = $tenantId . '@example.test';

        $accountingProduct = Product::query()->firstOrCreate(
            ['code' => 'accounting'],
            [
                'name' => 'Accounting System',
                'slug' => 'accounting-system',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        $plan = Plan::query()->create([
            'product_id' => $accountingProduct->id,
            'name' => 'Accounting Only Plan',
            'slug' => 'accounting-only-plan-' . uniqid(),
            'description' => 'Accounting only tenant flow plan',
            'price' => 199,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => 'price_' . uniqid(),
        ]);

        $tenant = Tenant::query()->create([
            'id' => $tenantId,
            'data' => [
                'company_name' => 'Accounting Only Co',
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
            'status' => 'active',
            'gateway' => null,
            'gateway_customer_id' => null,
            'gateway_subscription_id' => null,
            'gateway_checkout_session_id' => null,
            'gateway_price_id' => null,
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $accountingProduct->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'activation_status' => 'active',
            'provisioning_status' => 'active',
            'gateway' => null,
        ]);

        Artisan::call('tenants:migrate', [
            '--tenants' => [$tenant->id],
            '--force' => true,
        ]);

        tenancy()->initialize($tenant);

        try {
            User::query()->create([
                'name' => 'Accounting Owner',
                'email' => $email,
                'password' => bcrypt($password),
            ]);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        return [$tenant, $domain, $email, $password];
    }

    protected function attachPartsWorkspaceToTenant(Tenant $tenant): void
    {
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
    }

    protected function attachAccountingWorkspaceToTenant(Tenant $tenant): void
    {
        $accountingProduct = Product::query()->firstOrCreate(
            ['code' => 'accounting'],
            [
                'name' => 'Accounting System',
                'slug' => 'accounting-system',
                'is_active' => true,
                'sort_order' => 3,
            ]
        );

        $accountingPlan = Plan::query()->create([
            'product_id' => $accountingProduct->id,
            'name' => 'Accounting Pro',
            'slug' => 'accounting-pro-' . uniqid(),
            'price' => 199,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $accountingProduct->id,
            'plan_id' => $accountingPlan->id,
            'status' => 'active',
            'gateway' => null,
        ]);
    }

    /**
     * @param  array<int, array{branch: array{name:string, code:string}, product: array{name:string, sku:string}, quantity:int|float}>  $items
     * @return array{0:int, 1:int}
     */
    protected function seedTenantStock(Tenant $tenant, array $items): array
    {
        tenancy()->initialize($tenant);

        try {
            $lastBranchId = 0;
            $lastProductId = 0;

            foreach ($items as $item) {
                $branchId = DB::connection('tenant')->table('branches')->insertGetId([
                    'name' => $item['branch']['name'],
                    'code' => $item['branch']['code'],
                    'phone' => null,
                    'email' => null,
                    'address' => null,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $productId = DB::connection('tenant')->table('products')->insertGetId([
                    'name' => $item['product']['name'],
                    'sku' => $item['product']['sku'],
                    'barcode' => null,
                    'unit' => 'pcs',
                    'cost_price' => 10,
                    'sale_price' => 20,
                    'min_stock_alert' => 1,
                    'description' => null,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::connection('tenant')->table('inventories')->insert([
                    'branch_id' => $branchId,
                    'product_id' => $productId,
                    'quantity' => $item['quantity'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $lastBranchId = $branchId;
                $lastProductId = $productId;
            }

            return [$lastBranchId, $lastProductId];
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }
    }
}
