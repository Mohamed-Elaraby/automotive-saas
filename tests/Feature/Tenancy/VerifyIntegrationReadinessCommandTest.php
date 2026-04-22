<?php

namespace Tests\Feature\Tenancy;

use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Database\Models\Domain;
use Tests\TestCase;

class VerifyIntegrationReadinessCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    protected array $tenantDatabaseFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_it_verifies_contract_registry_without_tenant_database_checks(): void
    {
        $this->artisan('tenancy:verify-integration-readiness')
            ->expectsOutputToContain('automotive-accounting')
            ->expectsOutputToContain('Tenant checks skipped.')
            ->expectsOutput('Workspace integration readiness verification passed.')
            ->assertExitCode(0);
    }

    public function test_it_verifies_tenant_runtime_tables_and_workspace_products(): void
    {
        $tenant = $this->prepareTenantWithIntegrationProducts();

        $this->artisan("tenancy:verify-integration-readiness --tenant={$tenant->id}")
            ->expectsOutputToContain('automotive-parts')
            ->expectsOutputToContain('automotive-accounting')
            ->expectsOutputToContain('parts-accounting')
            ->expectsOutputToContain('Runtime tables')
            ->expectsOutputToContain('Accounting workspace product')
            ->expectsOutputToContain('Optional integration products')
            ->expectsOutput('Workspace integration readiness verification passed.')
            ->assertExitCode(0);
    }

    public function test_it_verifies_accounting_only_tenant_runtime_readiness(): void
    {
        $tenant = $this->prepareTenantWithIntegrationProducts(includeAutomotive: false, includeParts: false);

        $this->artisan("tenancy:verify-integration-readiness --tenant={$tenant->id}")
            ->expectsOutputToContain('Accounting workspace product')
            ->expectsOutputToContain('Optional integration products')
            ->expectsOutputToContain('Cross-product integration checks skipped for inactive workspace products: automotive_service, parts_inventory.')
            ->expectsOutput('Workspace integration readiness verification passed.')
            ->assertExitCode(0);
    }

    public function test_it_fails_when_tenant_is_missing_required_workspace_products(): void
    {
        $tenant = $this->prepareTenantWithIntegrationProducts(includeParts: false, includeAccounting: false);

        $this->artisan("tenancy:verify-integration-readiness --tenant={$tenant->id}")
            ->expectsOutputToContain('Tenant is missing an active accounting workspace product.')
            ->assertExitCode(1);
    }

    public function test_it_fails_when_accounting_defaults_are_missing(): void
    {
        $tenant = $this->prepareTenantWithIntegrationProducts(seedAccountingDefaults: false);

        $this->artisan("tenancy:verify-integration-readiness --tenant={$tenant->id}")
            ->expectsOutputToContain('Tenant accounting setup is missing required default accounts')
            ->expectsOutputToContain('Tenant accounting setup is missing an active default posting group.')
            ->expectsOutputToContain('Tenant accounting setup is missing an active default accounting policy.')
            ->expectsOutputToContain('Tenant accounting setup is missing an active default tax rate.')
            ->assertExitCode(1);
    }

    public function test_it_warns_about_accounting_data_quality_without_mutating_tenant_data(): void
    {
        $tenant = $this->prepareTenantWithIntegrationProducts();

        tenancy()->initialize($tenant);

        try {
            DB::connection('tenant')
                ->table('accounting_accounts')
                ->where('code', '1000 Cash On Hand')
                ->update(['is_active' => false]);

            DB::connection('tenant')->table('accounting_period_locks')->insert([
                [
                    'period_start' => '2026-01-01',
                    'period_end' => '2026-01-31',
                    'status' => 'locked',
                    'locked_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'period_start' => '2026-01-15',
                    'period_end' => '2026-02-15',
                    'status' => 'locked',
                    'locked_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            DB::connection('tenant')->table('workspace_integration_handoffs')->insert([
                'integration_key' => 'parts-accounting',
                'event_name' => 'stock_movement.valued',
                'source_product' => 'parts_inventory',
                'target_product' => 'accounting',
                'source_type' => 'stock_movements',
                'source_id' => 10,
                'status' => 'pending',
                'idempotency_key' => 'stale-handoff-' . uniqid(),
                'payload' => json_encode(['stock_movement_id' => 10]),
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(5),
            ]);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }

        $this->artisan("tenancy:verify-integration-readiness --tenant={$tenant->id} --stale-handoff-days=2")
            ->expectsOutputToContain('Tenant accounting setup has inactive required accounts: 1000 Cash On Hand.')
            ->expectsOutputToContain('Tenant accounting period locks overlap')
            ->expectsOutputToContain('Tenant has 1 uncompleted integration handoff(s) older than 2 day(s).')
            ->expectsOutput('Workspace integration readiness verification passed.')
            ->assertExitCode(0);

        tenancy()->initialize($tenant);

        try {
            $this->assertDatabaseHas('accounting_accounts', [
                'code' => '1000 Cash On Hand',
                'is_active' => false,
            ], 'tenant');
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }
    }

    protected function prepareTenantWithIntegrationProducts(
        bool $includeAutomotive = true,
        bool $includeParts = true,
        bool $includeAccounting = true,
        bool $seedAccountingDefaults = true
    ): Tenant
    {
        $tenantId = 'verify-integration-' . uniqid();

        $automotiveProduct = $this->createProductWithPlan('automotive_service', 'Automotive Service Management', 'automotive-service');
        $partsProduct = $this->createProductWithPlan('parts_inventory', 'Parts Inventory Management', 'parts-inventory');
        $accountingProduct = $this->createProductWithPlan('accounting', 'Accounting System', 'accounting-system');

        $tenant = Tenant::query()->create([
            'id' => $tenantId,
            'data' => ['company_name' => 'Verification Tenant'],
        ]);
        $this->tenantDatabaseFiles[] = database_path($tenant->database()->getName());

        Domain::query()->create([
            'domain' => $tenantId . '.example.test',
            'tenant_id' => $tenant->id,
        ]);

        $primaryPlan = $includeAccounting ? $accountingProduct['plan'] : $automotiveProduct['plan'];

        Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $primaryPlan->id,
            'status' => 'active',
            'gateway' => null,
        ]);

        if ($includeAutomotive) {
            $this->attachTenantProduct($tenant, $automotiveProduct['product'], $automotiveProduct['plan']);
        }

        if ($includeParts) {
            $this->attachTenantProduct($tenant, $partsProduct['product'], $partsProduct['plan']);
        }

        if ($includeAccounting) {
            $this->attachTenantProduct($tenant, $accountingProduct['product'], $accountingProduct['plan']);
        }

        Artisan::call('tenants:migrate', [
            '--tenants' => [$tenant->id],
            '--force' => true,
        ]);

        if ($seedAccountingDefaults) {
            $this->seedAccountingDefaults($tenant);
        }

        return $tenant;
    }

    /**
     * @return array{product: Product, plan: Plan}
     */
    protected function createProductWithPlan(string $code, string $name, string $slug): array
    {
        $product = Product::query()->firstOrCreate([
            'code' => $code,
        ], [
            'name' => $name,
            'slug' => $slug . '-' . uniqid(),
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $plan = Plan::query()->create([
            'product_id' => $product->id,
            'name' => $name . ' Plan',
            'slug' => $slug . '-plan-' . uniqid(),
            'price' => 99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
        ]);

        return ['product' => $product, 'plan' => $plan];
    }

    protected function attachTenantProduct(Tenant $tenant, Product $product, Plan $plan): void
    {
        TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'gateway' => null,
        ]);
    }

    protected function seedAccountingDefaults(Tenant $tenant): void
    {
        tenancy()->initialize($tenant);

        try {
            foreach ([
                ['1000 Cash On Hand', 'Cash On Hand', 'asset', 'debit'],
                ['1010 Bank Account', 'Bank Account', 'asset', 'debit'],
                ['1100 Accounts Receivable', 'Accounts Receivable', 'asset', 'debit'],
                ['1300 Inventory Asset', 'Inventory Asset', 'asset', 'debit'],
                ['1410 VAT Input Receivable', 'VAT Input Receivable', 'asset', 'debit'],
                ['2000 Accounts Payable', 'Accounts Payable', 'liability', 'credit'],
                ['2100 VAT Output Payable', 'VAT Output Payable', 'liability', 'credit'],
                ['3900 Inventory Adjustment Offset', 'Inventory Adjustment Offset', 'equity', 'credit'],
                ['4100 Service Labor Revenue', 'Service Labor Revenue', 'revenue', 'credit'],
                ['4100 Service Revenue', 'Service Revenue', 'revenue', 'credit'],
                ['4200 Parts Revenue', 'Parts Revenue', 'revenue', 'credit'],
                ['5000 Cost Of Goods Sold', 'Cost Of Goods Sold', 'expense', 'debit'],
                ['5100 Inventory Adjustment Expense', 'Inventory Adjustment Expense', 'expense', 'debit'],
                ['5200 Operating Expense', 'Operating Expense', 'expense', 'debit'],
            ] as [$code, $name, $type, $normalBalance]) {
                DB::connection('tenant')->table('accounting_accounts')->insert([
                    'code' => $code,
                    'name' => $name,
                    'type' => $type,
                    'normal_balance' => $normalBalance,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::connection('tenant')->table('accounting_posting_groups')->insert([
                'code' => 'workshop_revenue',
                'name' => 'Workshop Revenue',
                'receivable_account' => '1100 Accounts Receivable',
                'labor_revenue_account' => '4100 Service Labor Revenue',
                'parts_revenue_account' => '4200 Parts Revenue',
                'currency' => 'USD',
                'is_default' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::connection('tenant')->table('accounting_policies')->insert([
                'code' => 'default_inventory_policy',
                'name' => 'Default Inventory Policy',
                'currency' => 'USD',
                'inventory_asset_account' => '1300 Inventory Asset',
                'inventory_adjustment_offset_account' => '3900 Inventory Adjustment Offset',
                'inventory_adjustment_expense_account' => '5100 Inventory Adjustment Expense',
                'cogs_account' => '5000 Cost Of Goods Sold',
                'is_default' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::connection('tenant')->table('accounting_tax_rates')->insert([
                'code' => 'vat_5',
                'name' => 'VAT 5%',
                'rate' => 5,
                'input_tax_account' => '1410 VAT Input Receivable',
                'output_tax_account' => '2100 VAT Output Payable',
                'is_default' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }
    }
}
