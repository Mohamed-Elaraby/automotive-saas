<?php

namespace Tests\Feature\Automotive\Admin;

use App\Models\Branch;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductRole;
use App\Models\Tenant;
use App\Models\TenantProductBranch;
use App\Models\TenantProductSubscription;
use App\Models\TenantUserProductAccess;
use App\Models\TenantUserProductBranch;
use App\Models\TenantUserProductRole;
use App\Models\User;
use App\Services\Tenancy\ProductPermissionService;
use App\Services\Tenancy\WorkspaceOwnerAccessService;
use Database\Seeders\TenantAccessControlDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;
use Tests\TestCase;

class TenantAccessControlDemoSeederTest extends TestCase
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

    public function test_seeder_is_idempotent_and_does_not_duplicate_demo_data(): void
    {
        [$tenant] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $this->seed(TenantAccessControlDemoSeeder::class);
        $counts = $this->demoCounts();

        $this->seed(TenantAccessControlDemoSeeder::class);

        $this->assertSame($counts, $this->demoCounts());
    }

    public function test_seeder_creates_demo_users_and_branches(): void
    {
        [$tenant] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $this->seed(TenantAccessControlDemoSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'demo.owner@seven-scapital.test']);
        $this->assertDatabaseHas('users', ['email' => 'demo.manager@seven-scapital.test']);
        $this->assertDatabaseHas('users', ['email' => 'demo.advisor@seven-scapital.test']);
        $this->assertDatabaseHas('users', ['email' => 'demo.technician@seven-scapital.test']);
        $this->assertDatabaseHas('users', ['email' => 'demo.accountant@seven-scapital.test']);
        $this->assertDatabaseHas('users', ['email' => 'demo.viewer@seven-scapital.test']);
        $this->assertDatabaseHas('branches', ['code' => 'DXB-DEMO']);
        $this->assertDatabaseHas('branches', ['code' => 'AJM-DEMO']);
        $this->assertDatabaseHas('branches', ['code' => 'AUH-DEMO']);
    }

    public function test_seeder_respects_product_seat_limits(): void
    {
        [$tenant] = $this->prepareTenantWorkspace(maxUsers: 2, maxBranches: 3);

        tenancy()->initialize($tenant);
        $this->seed(TenantAccessControlDemoSeeder::class);

        $this->assertLessThanOrEqual(2, TenantUserProductAccess::query()
            ->where('product_key', 'automotive_service')
            ->active()
            ->get()
            ->filter(fn (TenantUserProductAccess $access): bool => (bool) (($access->metadata ?? [])['consumes_seat'] ?? true))
            ->count());
    }

    public function test_seeder_respects_product_branch_limits(): void
    {
        [$tenant] = $this->prepareTenantWorkspace(maxUsers: 10, maxBranches: 1);

        tenancy()->initialize($tenant);
        $this->seed(TenantAccessControlDemoSeeder::class);

        $this->assertSame(1, TenantProductBranch::query()
            ->where('product_key', 'automotive_service')
            ->enabled()
            ->count());
    }

    public function test_assigns_roles_only_where_product_access_is_valid(): void
    {
        [$tenant] = $this->prepareTenantWorkspace(maxUsers: 1, maxBranches: 1);

        tenancy()->initialize($tenant);
        $this->seed(TenantAccessControlDemoSeeder::class);

        TenantUserProductRole::query()
            ->where('product_key', 'automotive_service')
            ->active()
            ->get()
            ->each(function (TenantUserProductRole $assignment): void {
                $this->assertTrue(TenantUserProductAccess::query()
                    ->where('user_id', $assignment->user_id)
                    ->where('product_key', 'automotive_service')
                    ->active()
                    ->exists());
            });
    }

    public function test_owner_access_remains_safe_when_owner_is_primary_user(): void
    {
        [$tenant] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $this->seed(TenantAccessControlDemoSeeder::class);
        $owner = User::query()->where('email', 'demo.owner@seven-scapital.test')->firstOrFail();

        $this->assertTrue(app(WorkspaceOwnerAccessService::class)->isWorkspaceOwner($owner));
        $this->assertTrue(app(ProductPermissionService::class)->can($owner, 'automotive_service', 'automotive_service.access.roles.manage'));
    }

    public function test_viewer_role_is_read_only(): void
    {
        [$tenant] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $this->seed(TenantAccessControlDemoSeeder::class);
        $viewer = User::query()->where('email', 'demo.viewer@seven-scapital.test')->firstOrFail();

        $this->assertTrue(app(ProductPermissionService::class)->can($viewer, 'automotive_service', 'automotive_service.reports.view'));
        $this->assertFalse(app(ProductPermissionService::class)->can($viewer, 'automotive_service', 'automotive_service.reports.export'));
        $this->assertFalse(app(ProductPermissionService::class)->can($viewer, 'automotive_service', 'automotive_service.access.users.manage'));
    }

    protected function prepareTenantWorkspace(int $maxUsers = 10, int $maxBranches = 3): array
    {
        $tenant = Tenant::query()->create([
            'id' => 'tenant-demo-seeder-' . Str::uuid(),
            'data' => ['company_name' => 'Demo Seeder Test'],
        ]);

        $this->tenantDatabaseFiles[] = database_path($tenant->database()->getName());

        Domain::query()->create(['domain' => $tenant->id . '.example.test', 'tenant_id' => $tenant->id]);
        $this->attachProductSubscription($tenant, 'automotive_service', $maxUsers, $maxBranches);

        Artisan::call('tenants:migrate', [
            '--tenants' => [$tenant->id],
            '--force' => true,
        ]);

        return [$tenant];
    }

    protected function attachProductSubscription(Tenant $tenant, string $productKey, int $maxUsers, int $maxBranches): void
    {
        $product = Product::query()->firstOrCreate([
            'code' => $productKey,
        ], [
            'name' => Str::headline(str_replace('_', ' ', $productKey)),
            'slug' => Str::slug($productKey) . '-' . Str::uuid(),
            'is_active' => true,
        ]);

        $plan = Plan::query()->create([
            'product_id' => $product->id,
            'name' => Str::headline($productKey) . ' Demo Plan',
            'slug' => Str::slug($productKey) . '-demo-plan-' . Str::uuid(),
            'price' => 99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'max_users' => $maxUsers,
            'max_branches' => $maxBranches,
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'product_key' => $productKey,
            'plan_id' => $plan->id,
            'status' => 'active',
            'included_seats' => $maxUsers,
            'extra_seats' => 0,
            'branch_limit' => $maxBranches,
        ]);
    }

    protected function demoCounts(): array
    {
        return [
            'demo_users' => User::query()->whereIn('email', [
                'demo.owner@seven-scapital.test',
                'demo.manager@seven-scapital.test',
                'demo.advisor@seven-scapital.test',
                'demo.technician@seven-scapital.test',
                'demo.accountant@seven-scapital.test',
                'demo.viewer@seven-scapital.test',
                'demo.missing-branch@seven-scapital.test',
            ])->count(),
            'demo_branches' => Branch::query()->whereIn('code', ['DXB-DEMO', 'AJM-DEMO', 'AUH-DEMO'])->count(),
            'enabled_branches' => TenantProductBranch::query()->where('product_key', 'automotive_service')->enabled()->count(),
            'product_access' => TenantUserProductAccess::query()->where('product_key', 'automotive_service')->active()->count(),
            'branch_access' => TenantUserProductBranch::query()->where('product_key', 'automotive_service')->enabled()->count(),
            'role_assignments' => TenantUserProductRole::query()->where('product_key', 'automotive_service')->active()->count(),
            'roles' => ProductRole::query()->where('product_key', 'automotive_service')->count(),
        ];
    }
}
