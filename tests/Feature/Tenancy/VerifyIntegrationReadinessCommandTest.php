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
            ->expectsOutputToContain('Workspace products')
            ->expectsOutput('Workspace integration readiness verification passed.')
            ->assertExitCode(0);
    }

    public function test_it_fails_when_tenant_is_missing_required_workspace_products(): void
    {
        $tenant = $this->prepareTenantWithIntegrationProducts(includeParts: false, includeAccounting: false);

        $this->artisan("tenancy:verify-integration-readiness --tenant={$tenant->id}")
            ->expectsOutputToContain('Tenant is missing active workspace products for integration verification')
            ->assertExitCode(1);
    }

    protected function prepareTenantWithIntegrationProducts(bool $includeParts = true, bool $includeAccounting = true): Tenant
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

        Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $automotiveProduct['plan']->id,
            'status' => 'active',
            'gateway' => null,
        ]);

        $this->attachTenantProduct($tenant, $automotiveProduct['product'], $automotiveProduct['plan']);

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
}
