<?php

namespace Tests\Feature\Automotive\Admin;

use App\Models\Branch;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductRole;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\User;
use App\Services\Tenancy\BranchScopeService;
use App\Services\Tenancy\AccessControlRouteInspector;
use App\Services\Tenancy\EffectiveUserAccessService;
use App\Services\Tenancy\ProductPermissionService;
use Database\Seeders\TenantAccessControlDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;
use Tests\TestCase;

class AccessControlFinalAcceptanceTest extends TestCase
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

    public function test_all_core_access_control_route_names_exist(): void
    {
        $missingRoutes = app(AccessControlRouteInspector::class)->missingRouteNames();

        $this->assertSame([], $missingRoutes, 'Missing routes: ' . implode(', ', $missingRoutes));
    }

    public function test_all_core_access_control_pages_render_for_workspace_owner(): void
    {
        [$tenant, $domain] = $this->prepareSeededTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = User::query()->orderBy('id')->firstOrFail();
        $target = User::query()->where('email', 'demo.viewer@seven-scapital.test')->firstOrFail();
        $role = ProductRole::query()->where('name', 'Automotive Viewer')->firstOrFail();
        tenancy()->end();

        foreach ([
            '/workspace/admin/access',
            '/workspace/admin/access/users',
            "/workspace/admin/access/users/{$target->id}",
            "/workspace/admin/access/users/{$target->id}/products",
            "/workspace/admin/access/users/{$target->id}/branches",
            "/workspace/admin/access/users/{$target->id}/roles",
            '/workspace/admin/access/roles',
            '/workspace/admin/access/roles/create',
            "/workspace/admin/access/roles/{$role->id}/edit",
            "/workspace/admin/access/roles/{$role->id}/permissions",
            '/workspace/admin/access/products',
            '/workspace/admin/access/products/automotive_service/branches',
            '/workspace/admin/access/audit',
            '/workspace/admin/access/diagnostics',
            '/workspace/admin/access/branch-context/select',
        ] as $path) {
            $this->actingAs($owner, 'automotive_admin')
                ->get("http://{$domain}{$path}")
                ->assertOk();
        }
    }

    public function test_scoped_access_views_do_not_use_legacy_layout(): void
    {
        foreach (File::allFiles(resource_path('views/automotive/admin/access')) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = File::get($file->getPathname());

            $this->assertStringNotContainsString("@extends('layout.mainlayout')", $contents);
            $this->assertStringNotContainsString('@extends("layout.mainlayout")', $contents);
        }
    }

    public function test_owner_flow_is_available_and_safe(): void
    {
        [$tenant, $domain] = $this->prepareSeededTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = User::query()->orderBy('id')->firstOrFail();
        $this->assertTrue(app(ProductPermissionService::class)->can($owner, 'automotive_service', 'automotive_service.access.roles.manage'));
        tenancy()->end();

        $this->actingAs($owner, 'automotive_admin')
            ->post("http://{$domain}/workspace/admin/access/users/{$owner->id}/owner-sync")
            ->assertRedirect();
    }

    public function test_branch_manager_flow_sees_allowed_branch_access_only(): void
    {
        [$tenant] = $this->prepareSeededTenantWorkspace();

        tenancy()->initialize($tenant);
        $manager = User::query()->where('email', 'demo.manager@seven-scapital.test')->firstOrFail();
        $allowedIds = app(BranchScopeService::class)->allowedBranchIdsForUser($manager, 'automotive_service');

        $this->assertNotEmpty($allowedIds);
        $this->assertLessThanOrEqual(2, count($allowedIds));
        $this->assertTrue(app(ProductPermissionService::class)->can($manager, 'automotive_service', 'automotive_service.work_orders.view'));
    }

    public function test_service_advisor_flow_has_product_branch_role_and_effective_permissions(): void
    {
        [$tenant] = $this->prepareSeededTenantWorkspace();

        tenancy()->initialize($tenant);
        $advisor = User::query()->where('email', 'demo.advisor@seven-scapital.test')->firstOrFail();
        $profile = app(EffectiveUserAccessService::class)->profile($advisor);

        $this->assertGreaterThan(0, $profile['summary']['product_count']);
        $this->assertGreaterThan(0, $profile['summary']['branch_count']);
        $this->assertGreaterThan(0, $profile['summary']['role_count']);
        $this->assertTrue(app(ProductPermissionService::class)->can($advisor, 'automotive_service', 'automotive_service.check_ins.create'));
    }

    public function test_technician_flow_is_task_limited(): void
    {
        [$tenant] = $this->prepareSeededTenantWorkspace();

        tenancy()->initialize($tenant);
        $technician = User::query()->where('email', 'demo.technician@seven-scapital.test')->firstOrFail();

        $this->assertTrue(app(ProductPermissionService::class)->can($technician, 'automotive_service', 'automotive_service.jobs.edit'));
        $this->assertFalse(app(ProductPermissionService::class)->can($technician, 'automotive_service', 'automotive_service.invoices.view'));
        $this->assertFalse(app(ProductPermissionService::class)->can($technician, 'automotive_service', 'automotive_service.access.roles.manage'));
    }

    public function test_accountant_flow_has_finance_related_access(): void
    {
        [$tenant] = $this->prepareSeededTenantWorkspace();

        tenancy()->initialize($tenant);
        $accountant = User::query()->where('email', 'demo.accountant@seven-scapital.test')->firstOrFail();

        $this->assertTrue(app(ProductPermissionService::class)->can($accountant, 'automotive_service', 'automotive_service.invoices.view'));
        $this->assertTrue(app(ProductPermissionService::class)->can($accountant, 'automotive_service', 'automotive_service.payments.reconcile'));
        $this->assertFalse(app(ProductPermissionService::class)->can($accountant, 'automotive_service', 'automotive_service.jobs.edit'));
    }

    public function test_viewer_flow_is_read_only_and_cannot_manage_actions(): void
    {
        [$tenant, $domain] = $this->prepareSeededTenantWorkspace();

        tenancy()->initialize($tenant);
        $viewer = User::query()->where('email', 'demo.viewer@seven-scapital.test')->firstOrFail();

        $this->assertTrue(app(ProductPermissionService::class)->can($viewer, 'automotive_service', 'automotive_service.reports.view'));
        $this->assertFalse(app(ProductPermissionService::class)->can($viewer, 'automotive_service', 'automotive_service.reports.export'));
        $this->assertFalse(app(ProductPermissionService::class)->can($viewer, 'automotive_service', 'automotive_service.access.users.manage'));
        tenancy()->end();

        $this->actingAs($viewer, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/access")
            ->assertForbidden();
    }

    public function test_audit_and_diagnostics_routes_render_for_authorized_user(): void
    {
        [$tenant, $domain] = $this->prepareSeededTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = User::query()->orderBy('id')->firstOrFail();
        tenancy()->end();

        $this->actingAs($owner, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/access/audit")
            ->assertOk()
            ->assertSee('Access Audit Logs');

        $this->actingAs($owner, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/access/diagnostics")
            ->assertOk()
            ->assertSee('Access Diagnostics');
    }

    public function test_branch_scoped_data_filtering_still_applies(): void
    {
        [$tenant] = $this->prepareSeededTenantWorkspace();

        tenancy()->initialize($tenant);
        $viewer = User::query()->where('email', 'demo.viewer@seven-scapital.test')->firstOrFail();
        $allowedIds = app(BranchScopeService::class)->allowedBranchIdsForUser($viewer, 'automotive_service');
        $forbiddenBranch = Branch::query()->whereNotIn('id', $allowedIds)->first();

        $this->assertNotEmpty($allowedIds);

        if ($forbiddenBranch) {
            $this->assertFalse(app(BranchScopeService::class)->canAccessBranch($viewer, 'automotive_service', $forbiddenBranch->id));
        }
    }

    public function test_demo_seeder_is_idempotent(): void
    {
        [$tenant] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $this->seed(TenantAccessControlDemoSeeder::class);
        $counts = $this->demoCounts();

        $this->seed(TenantAccessControlDemoSeeder::class);

        $this->assertSame($counts, $this->demoCounts());
    }

    public function test_route_cache_is_not_required_or_introduced_in_deploy_workflows(): void
    {
        $this->assertFileExists(base_path('docs/platform-access-control-ui.md'));
        $this->assertStringContainsString('php artisan route:cache', File::get(base_path('docs/platform-access-control-ui.md')));

        $workflowPath = base_path('.github/workflows');

        if (! File::isDirectory($workflowPath)) {
            $this->assertTrue(true);

            return;
        }

        foreach (File::allFiles($workflowPath) as $file) {
            $this->assertStringNotContainsString('php artisan route:cache', File::get($file->getPathname()));
        }
    }

    protected function prepareSeededTenantWorkspace(): array
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $this->seed(TenantAccessControlDemoSeeder::class);
        tenancy()->end();

        return [$tenant, $domain];
    }

    protected function prepareTenantWorkspace(): array
    {
        $tenant = Tenant::query()->create([
            'id' => 'tenant-final-acceptance-' . Str::uuid(),
            'data' => ['company_name' => 'Final Acceptance Test'],
        ]);

        $this->tenantDatabaseFiles[] = database_path($tenant->database()->getName());

        $domain = $tenant->id . '.example.test';
        Domain::query()->create(['domain' => $domain, 'tenant_id' => $tenant->id]);

        $this->attachProductSubscription($tenant, 'automotive_service');

        Artisan::call('tenants:migrate', [
            '--tenants' => [$tenant->id],
            '--force' => true,
        ]);

        return [$tenant, $domain];
    }

    protected function attachProductSubscription(Tenant $tenant, string $productKey): void
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
            'name' => Str::headline($productKey) . ' Plan',
            'slug' => Str::slug($productKey) . '-plan-' . Str::uuid(),
            'price' => 99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'max_users' => 10,
            'max_branches' => 3,
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'product_key' => $productKey,
            'plan_id' => $plan->id,
            'status' => 'active',
            'included_seats' => 10,
            'extra_seats' => 0,
            'branch_limit' => 3,
        ]);
    }

    protected function demoCounts(): array
    {
        return [
            'branches' => Branch::query()->whereIn('code', ['DXB-DEMO', 'AJM-DEMO', 'AUH-DEMO'])->count(),
            'users' => User::query()->whereIn('email', [
                'demo.manager@seven-scapital.test',
                'demo.advisor@seven-scapital.test',
                'demo.technician@seven-scapital.test',
                'demo.accountant@seven-scapital.test',
                'demo.viewer@seven-scapital.test',
            ])->count(),
            'roles' => ProductRole::query()->where('product_key', 'automotive_service')->count(),
        ];
    }
}
