<?php

namespace Tests\Feature\Automotive\Admin;

use App\Models\Branch;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\User;
use App\Services\Admin\AdminTenantLifecycleService;
use App\Services\Tenancy\ProductBranchAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;
use Tests\TestCase;

class ProductBranchPlanUpgradeTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_product_branch_ui_uses_upgraded_plan_branch_limit(): void
    {
        [$tenant, $domain, $growth] = $this->prepareTenantWorkspace();

        app(AdminTenantLifecycleService::class)->changeLatestPlan($tenant->id, $growth->id);

        tenancy()->initialize($tenant);
        $owner = User::query()->create([
            'id' => 1,
            'name' => 'Workspace Owner',
            'email' => 'owner@example.test',
            'password' => Hash::make('password'),
        ]);
        $abuDhabi = Branch::query()->create(['name' => 'Abu Dhabi', 'code' => 'AUH', 'is_active' => true]);
        $ajman = Branch::query()->create(['name' => 'Ajman', 'code' => 'AJM', 'is_active' => true]);
        app(ProductBranchAccessService::class)->enableBranch($abuDhabi, 'automotive_service');
        tenancy()->end();

        $response = $this
            ->actingAs($owner, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/access/products/automotive_service/branches");

        $response->assertOk();
        $response->assertSee('1 / 3', false);
        $response->assertSee('2', false);

        $this
            ->actingAs($owner, 'automotive_admin')
            ->put("http://{$domain}/workspace/admin/access/products/automotive_service/branches", [
                'branches' => [$abuDhabi->id, $ajman->id],
            ])
            ->assertRedirect("http://{$domain}/workspace/admin/access/products/automotive_service/branches");

        tenancy()->initialize($tenant);
        $this->assertTrue(app(ProductBranchAccessService::class)->isBranchEnabled($ajman, 'automotive_service'));
    }

    protected function prepareTenantWorkspace(): array
    {
        $tenant = Tenant::query()->create([
            'id' => 'tenant-branch-upgrade-' . Str::uuid(),
            'data' => ['company_name' => 'Branch Upgrade'],
        ]);

        $this->tenantDatabaseFiles[] = database_path($tenant->database()->getName());
        $domain = $tenant->id . '.example.test';
        Domain::query()->create(['domain' => $domain, 'tenant_id' => $tenant->id]);

        $product = Product::query()->firstOrCreate(['code' => 'automotive_service'], [
            'name' => 'Automotive Service Management',
            'slug' => 'automotive-service-' . Str::uuid(),
            'is_active' => true,
        ]);

        $starter = $this->plan($product, 'Starter', 1);
        $growth = $this->plan($product, 'Growth', 3);

        $legacy = Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $starter->id,
            'status' => 'active',
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'product_key' => 'automotive_service',
            'legacy_subscription_id' => $legacy->id,
            'plan_id' => $starter->id,
            'status' => 'active',
            'included_seats' => 5,
            'branch_limit' => 1,
        ]);

        Artisan::call('tenants:migrate', ['--tenants' => [$tenant->id], '--force' => true]);

        return [$tenant, $domain, $growth];
    }

    protected function plan(Product $product, string $name, int $branchLimit): Plan
    {
        $plan = Plan::query()->create([
            'product_id' => $product->id,
            'name' => 'Automotive Service Management ' . $name,
            'slug' => 'automotive-' . strtolower($name) . '-' . Str::uuid(),
            'price' => 99 * $branchLimit,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'max_users' => 5,
            'max_branches' => $branchLimit,
        ]);

        DB::table('plan_limits')->insert([
            'product_key' => 'automotive_service',
            'plan_id' => $plan->id,
            'limit_key' => 'branch_limit',
            'limit_value' => (string) $branchLimit,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $plan;
    }
}
