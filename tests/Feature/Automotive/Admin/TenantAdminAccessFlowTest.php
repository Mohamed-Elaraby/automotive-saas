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
        $dashboardResponse->assertDontSee('Plans & Billing', false);
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
        ])->assertRedirect("http://{$domain}/automotive/admin/dashboard");

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
        ])->assertRedirect("http://{$domain}/automotive/admin/dashboard");

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
        ])->assertRedirect("http://{$domain}/automotive/admin/dashboard");

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

        $storeResponse->assertRedirect("http://{$domain}/automotive/admin/supplier-catalog?workspace_product=parts_inventory");

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
        ])->assertRedirect("http://{$domain}/automotive/admin/dashboard");

        $response = $this->get("http://{$domain}/automotive/admin/products");

        $response->assertRedirect("http://{$domain}/automotive/admin/dashboard?workspace_product=parts_inventory");
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
        ])->assertRedirect("http://{$domain}/automotive/admin/dashboard");

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
        ])->assertRedirect("http://{$domain}/automotive/admin/dashboard");

        $createCustomerResponse = $this->post("http://{$domain}/automotive/admin/workshop-operations/customers?workspace_product=automotive_service", [
            'workspace_product' => 'automotive_service',
            'name' => 'Ahmed Ali',
            'phone' => '0500000000',
            'email' => 'ahmed@example.test',
        ]);

        $createCustomerResponse->assertRedirect("http://{$domain}/automotive/admin/workshop-operations?workspace_product=automotive_service");

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

        $createVehicleResponse->assertRedirect("http://{$domain}/automotive/admin/workshop-operations?workspace_product=automotive_service");

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

        $createWorkOrderResponse->assertRedirect("http://{$domain}/automotive/admin/workshop-operations?workspace_product=automotive_service");

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

        $response->assertRedirect("http://{$domain}/automotive/admin/workshop-operations?workspace_product=automotive_service");

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

        $addLaborLineResponse->assertRedirect("http://{$domain}/automotive/admin/workshop-operations/work-orders/{$workOrder->id}?workspace_product=automotive_service");

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

        $statusResponse->assertRedirect("http://{$domain}/automotive/admin/workshop-operations/work-orders/{$workOrder->id}?workspace_product=automotive_service");

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
        $generalLedgerResponse->assertSee($workOrder->work_order_number, false);
        $generalLedgerResponse->assertSee('190.00', false);
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
