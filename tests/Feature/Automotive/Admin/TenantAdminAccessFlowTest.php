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
        $ledgerResponse->assertSee('Create Posting Group', false);
        $ledgerResponse->assertSee('Accounting Event Review', false);
        $ledgerResponse->assertSee('WO-ACCOUNTING-1', false);
        $ledgerResponse->assertSee('Post To Journal', false);

        $groupResponse = $this->post("http://{$domain}/automotive/admin/general-ledger/posting-groups?workspace_product=accounting", [
            'workspace_product' => 'accounting',
            'code' => 'service_revenue',
            'name' => 'Service Revenue',
            'receivable_account' => '1100 Accounts Receivable',
            'labor_revenue_account' => '4100 Labor Revenue',
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
        $printResponse->assertSee('Journal entries', false);

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
                'event_type' => 'inventory_valuation_posted',
            ], 'tenant');
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }
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
