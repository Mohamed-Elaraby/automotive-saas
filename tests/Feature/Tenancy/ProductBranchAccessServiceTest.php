<?php

namespace Tests\Feature\Tenancy;

use App\Models\Branch;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\User;
use App\Services\Tenancy\ProductBranchAccessService;
use Database\Seeders\TenantBranchDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Stancl\Tenancy\Database\Models\Domain;
use Tests\TestCase;

class ProductBranchAccessServiceTest extends TestCase
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

    public function test_central_branches_can_be_created_inside_tenant(): void
    {
        $tenant = $this->prepareTenantWithSubscription('automotive_service', 2);

        tenancy()->initialize($tenant);

        $branch = $this->branch('AUH-MAIN', 'Abu Dhabi Main Branch');

        $this->assertSame('Abu Dhabi Main Branch', $branch->name);
        $this->assertSame('Abu Dhabi', $branch->emirate);
        $this->assertSame('Asia/Dubai', $branch->timezone);
    }

    public function test_branch_can_be_enabled_for_product_when_limit_allows(): void
    {
        $tenant = $this->prepareTenantWithSubscription('automotive_service', 1);

        tenancy()->initialize($tenant);

        $branch = $this->branch('AUH-MAIN', 'Abu Dhabi Main Branch');
        $activation = app(ProductBranchAccessService::class)->enableBranch($branch, 'automotive_service');

        $this->assertTrue($activation->is_enabled);
        $this->assertSame(1, app(ProductBranchAccessService::class)->countEnabledBranches('automotive_service'));
        $this->assertSame(0, app(ProductBranchAccessService::class)->availableBranches('automotive_service'));
    }

    public function test_branch_activation_is_blocked_when_branch_limit_is_exceeded(): void
    {
        $tenant = $this->prepareTenantWithSubscription('automotive_service', 1);

        tenancy()->initialize($tenant);

        $service = app(ProductBranchAccessService::class);
        $service->enableBranch($this->branch('AUH-MAIN', 'Abu Dhabi Main Branch'), 'automotive_service');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No available branches');

        $service->enableBranch($this->branch('DXB-BR', 'Dubai Branch'), 'automotive_service');
    }

    public function test_extra_branch_addon_increases_product_branch_limit(): void
    {
        $tenant = $this->prepareTenantWithSubscription('automotive_service', 1);

        DB::table('subscription_addons')->insert([
            'tenant_id' => $tenant->id,
            'product_key' => 'automotive_service',
            'addon_key' => 'extra_branch',
            'quantity' => 1,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        tenancy()->initialize($tenant);

        $service = app(ProductBranchAccessService::class);
        $service->enableBranch($this->branch('AUH-MAIN', 'Abu Dhabi Main Branch'), 'automotive_service');
        $service->enableBranch($this->branch('DXB-BR', 'Dubai Branch'), 'automotive_service');

        $this->assertSame(2, $service->countEnabledBranches('automotive_service'));
        $this->assertSame(0, $service->availableBranches('automotive_service'));
    }

    public function test_user_branch_access_is_scoped_by_product(): void
    {
        $tenant = $this->prepareTenantWithSubscription('automotive_service', 2);
        $this->attachProductSubscription($tenant, 'accounting', 1);

        tenancy()->initialize($tenant);

        $service = app(ProductBranchAccessService::class);
        $user = $this->tenantUser('branch-user@example.test');
        $automotiveBranch = $this->branch('AUH-MAIN', 'Abu Dhabi Main Branch');
        $accountingBranch = $this->branch('DXB-BR', 'Dubai Branch');

        $service->enableBranch($automotiveBranch, 'automotive_service');
        $service->enableBranch($accountingBranch, 'accounting');
        $service->grantUserBranchAccess($user, $automotiveBranch, 'automotive_service');
        $service->grantUserBranchAccess($user, $accountingBranch, 'accounting');

        $this->assertSame(['Abu Dhabi Main Branch'], $service->userAllowedBranches($user, 'automotive_service')->pluck('name')->all());
        $this->assertSame(['Dubai Branch'], $service->userAllowedBranches($user, 'accounting')->pluck('name')->all());
    }

    public function test_user_only_sees_allowed_product_branches(): void
    {
        $tenant = $this->prepareTenantWithSubscription('automotive_service', 2);

        tenancy()->initialize($tenant);

        $service = app(ProductBranchAccessService::class);
        $user = $this->tenantUser('limited@example.test');
        $allowed = $this->branch('AUH-MAIN', 'Abu Dhabi Main Branch');
        $notAllowed = $this->branch('DXB-BR', 'Dubai Branch');

        $service->enableBranch($allowed, 'automotive_service');
        $service->enableBranch($notAllowed, 'automotive_service');
        $service->grantUserBranchAccess($user, $allowed, 'automotive_service');

        $this->assertSame(['Abu Dhabi Main Branch'], $service->userAllowedBranches($user, 'automotive_service')->pluck('name')->all());
    }

    public function test_demo_branch_seeder_is_idempotent_and_respects_branch_limit(): void
    {
        $tenant = $this->prepareTenantWithSubscription('automotive_service', 2);

        tenancy()->initialize($tenant);

        $this->seed(TenantBranchDemoSeeder::class);
        $this->seed(TenantBranchDemoSeeder::class);

        $this->assertSame(5, Branch::query()->count());
        $this->assertSame(2, app(ProductBranchAccessService::class)->countEnabledBranches('automotive_service'));
    }

    protected function prepareTenantWithSubscription(string $productKey, int $branchLimit): Tenant
    {
        $tenant = Tenant::query()->create([
            'id' => 'tenant-branch-' . Str::uuid(),
            'data' => ['company_name' => 'Tenant Branch Test'],
        ]);

        $this->tenantDatabaseFiles[] = database_path($tenant->database()->getName());

        Domain::query()->create([
            'domain' => $tenant->id . '.example.test',
            'tenant_id' => $tenant->id,
        ]);

        $this->attachProductSubscription($tenant, $productKey, $branchLimit);

        Artisan::call('tenants:migrate', [
            '--tenants' => [$tenant->id],
            '--force' => true,
        ]);

        return $tenant;
    }

    protected function attachProductSubscription(Tenant $tenant, string $productKey, int $branchLimit): void
    {
        $product = Product::query()->firstOrCreate([
            'code' => $productKey,
        ], [
            'name' => Str::headline($productKey),
            'slug' => Str::slug($productKey),
            'is_active' => true,
        ]);

        $plan = Plan::query()->create([
            'product_id' => $product->id,
            'name' => Str::headline($productKey) . ' Branch Plan',
            'slug' => Str::slug($productKey) . '-branch-plan-' . uniqid(),
            'price' => 99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'max_users' => 5,
            'max_branches' => $branchLimit,
        ]);

        DB::table('plan_limits')->insert([
            'product_key' => $productKey,
            'plan_id' => $plan->id,
            'limit_key' => 'branch_limit',
            'limit_value' => (string) $branchLimit,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'product_key' => $productKey,
            'plan_id' => $plan->id,
            'status' => 'active',
            'included_seats' => 5,
            'extra_seats' => 0,
            'branch_limit' => $branchLimit,
        ]);
    }

    protected function branch(string $code, string $name): Branch
    {
        return Branch::query()->create([
            'name' => $name,
            'code' => $code,
            'address' => $name,
            'emirate' => Str::contains($name, 'Dubai') ? 'Dubai' : 'Abu Dhabi',
            'city' => Str::before($name, ' Branch'),
            'country' => 'United Arab Emirates',
            'timezone' => 'Asia/Dubai',
            'is_active' => true,
        ]);
    }

    protected function tenantUser(string $email): User
    {
        return User::query()->create([
            'name' => Str::headline(Str::before($email, '@')),
            'email' => $email,
            'password' => bcrypt('secret-pass'),
        ]);
    }
}
