<?php

namespace Tests\Feature\Automotive\Admin;

use App\Models\Branch;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductRole;
use App\Models\Tenant;
use App\Models\TenantProductBranch;
use App\Models\TenantProductSubscription;
use App\Models\User;
use App\Services\Tenancy\ProductBranchAccessService;
use App\Services\Tenancy\ProductPermissionService;
use App\Services\Tenancy\TenantUserProductAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;
use Tests\TestCase;

class BackendPermissionEnforcementTest extends TestCase
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

    public function test_owner_can_access_protected_access_control_routes(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $target = $this->tenantUser('target@example.test');
        $role = $this->roleWithPermissions('Owner Managed Role', ['automotive_service.work_orders.view']);
        tenancy()->end();

        foreach ([
            "/workspace/admin/access",
            "/workspace/admin/access/users",
            "/workspace/admin/access/users/{$target->id}",
            "/workspace/admin/access/users/{$target->id}/products",
            "/workspace/admin/access/users/{$target->id}/branches",
            "/workspace/admin/access/users/{$target->id}/roles",
            "/workspace/admin/access/roles",
            "/workspace/admin/access/roles/create",
            "/workspace/admin/access/roles/{$role->id}/edit",
            "/workspace/admin/access/roles/{$role->id}/permissions",
            "/workspace/admin/access/products/automotive_service/branches",
        ] as $path) {
            $this->actingAs($owner, 'automotive_admin')
                ->get("http://{$domain}{$path}")
                ->assertOk();
        }
    }

    public function test_user_without_access_manage_cannot_access_access_dashboard_directly(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $this->tenantUser('owner@example.test');
        $user = $this->tenantUser('regular@example.test');
        tenancy()->end();

        $this->actingAs($user, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/access")
            ->assertForbidden();
    }

    public function test_user_without_roles_manage_cannot_open_roles_index_directly(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $this->tenantUser('owner@example.test');
        $user = $this->userWithPermissions('access-manager@example.test', ['automotive_service.access.manage']);
        tenancy()->end();

        $this->actingAs($user, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/access/roles")
            ->assertForbidden();
    }

    public function test_user_without_roles_manage_cannot_create_role_via_post(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $this->tenantUser('owner@example.test');
        $user = $this->userWithPermissions('access-manager@example.test', ['automotive_service.access.manage']);
        tenancy()->end();

        $this->actingAs($user, 'automotive_admin')
            ->post("http://{$domain}/workspace/admin/access/roles", [
                'product_key' => 'automotive_service',
                'name' => 'Forbidden Role',
                'is_active' => '1',
            ])
            ->assertForbidden();

        tenancy()->initialize($tenant);
        $this->assertDatabaseMissing('product_roles', ['name' => 'Forbidden Role']);
    }

    public function test_user_without_roles_manage_cannot_update_permission_matrix_via_put(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $this->tenantUser('owner@example.test');
        $user = $this->userWithPermissions('access-manager@example.test', ['automotive_service.access.manage']);
        $role = $this->roleWithPermissions('Matrix Role', ['automotive_service.work_orders.view']);
        tenancy()->end();

        $this->actingAs($user, 'automotive_admin')
            ->put("http://{$domain}/workspace/admin/access/roles/{$role->id}/permissions", [
                'permissions' => ['automotive_service.work_orders.create'],
            ])
            ->assertForbidden();

        tenancy()->initialize($tenant);
        $this->assertSame(['automotive_service.work_orders.view'], $role->refresh()->permissions()->pluck('permission_key')->all());
    }

    public function test_user_without_users_manage_cannot_grant_or_revoke_product_access(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $this->tenantUser('owner@example.test');
        $actor = $this->userWithPermissions('roles-manager@example.test', ['automotive_service.access.roles.manage']);
        $target = $this->tenantUser('target@example.test');
        tenancy()->end();

        $this->actingAs($actor, 'automotive_admin')
            ->put("http://{$domain}/workspace/admin/access/users/{$target->id}/products", [
                'products' => ['automotive_service'],
            ])
            ->assertForbidden();

        tenancy()->initialize($tenant);
        $this->assertDatabaseMissing('tenant_user_product_access', [
            'user_id' => $target->id,
            'product_key' => 'automotive_service',
            'status' => 'active',
        ]);
    }

    public function test_user_without_branches_manage_cannot_assign_branches(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $actor = $this->userWithPermissions('roles-manager@example.test', ['automotive_service.access.roles.manage']);
        $target = $this->tenantUser('target@example.test');
        $branch = $this->branch('Dubai');
        app(TenantUserProductAccessService::class)->grantAccess($target, 'automotive_service', $owner);
        app(ProductBranchAccessService::class)->enableBranch($branch, 'automotive_service');
        tenancy()->end();

        $this->actingAs($actor, 'automotive_admin')
            ->put("http://{$domain}/workspace/admin/access/users/{$target->id}/branches", [
                'branches' => ['automotive_service' => [$branch->id]],
            ])
            ->assertForbidden();
    }

    public function test_user_without_branches_manage_cannot_enable_product_branch(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $this->tenantUser('owner@example.test');
        $actor = $this->userWithPermissions('roles-manager@example.test', ['automotive_service.access.roles.manage']);
        $branch = $this->branch('Abu Dhabi');
        tenancy()->end();

        $this->actingAs($actor, 'automotive_admin')
            ->put("http://{$domain}/workspace/admin/access/products/automotive_service/branches", [
                'branches' => [$branch->id],
            ])
            ->assertForbidden();

        tenancy()->initialize($tenant);
        $this->assertSame(0, TenantProductBranch::query()->enabled()->count());
    }

    public function test_revoked_product_access_blocks_protected_route(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $user = $this->userWithPermissions('roles-manager@example.test', ['automotive_service.access.roles.manage']);
        app(TenantUserProductAccessService::class)->revokeAccess($user, 'automotive_service');
        tenancy()->end();

        $this->actingAs($user, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/access/roles")
            ->assertForbidden();

        $this->assertNotNull($owner);
    }

    public function test_missing_branch_access_blocks_branch_scoped_protected_route_when_required(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();
        $this->registerBranchScopedProbeRoute();

        tenancy()->initialize($tenant);
        $this->tenantUser('owner@example.test');
        $user = $this->userWithPermissions('branch-manager@example.test', ['automotive_service.work_orders.view']);
        tenancy()->end();

        $this->actingAs($user, 'automotive_admin')
            ->get("http://{$domain}/package-16-branch-probe")
            ->assertForbidden();
    }

    public function test_forbidden_post_does_not_modify_database(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $this->tenantUser('owner@example.test');
        $actor = $this->userWithPermissions('access-manager@example.test', ['automotive_service.access.manage']);
        tenancy()->end();

        $this->actingAs($actor, 'automotive_admin')
            ->post("http://{$domain}/workspace/admin/access/roles", [
                'product_key' => 'automotive_service',
                'name' => 'Should Not Exist',
                'is_active' => '1',
            ])
            ->assertForbidden();

        tenancy()->initialize($tenant);
        $this->assertDatabaseMissing('product_roles', ['name' => 'Should Not Exist']);
    }

    public function test_owner_implicit_access_passes_middleware(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        tenancy()->end();

        $this->actingAs($owner, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/access/roles")
            ->assertOk();
    }

    public function test_branch_context_selector_and_switch_routes_remain_usable(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $user = $this->tenantUser('advisor@example.test');
        $branch = $this->branch('Allowed Branch');
        app(TenantUserProductAccessService::class)->grantAccess($user, 'automotive_service', $owner);
        app(ProductBranchAccessService::class)->enableBranch($branch, 'automotive_service');
        app(ProductBranchAccessService::class)->grantUserBranchAccess($user, $branch, 'automotive_service');
        tenancy()->end();

        $this->actingAs($user, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/access/branch-context/select")
            ->assertOk()
            ->assertSee('Allowed Branch', false);

        $this->actingAs($user, 'automotive_admin')
            ->post("http://{$domain}/workspace/admin/access/branch-context/switch", [
                'product_key' => 'automotive_service',
                'branch_id' => $branch->id,
            ])
            ->assertRedirect("http://{$domain}/workspace/admin/dashboard?workspace_product=automotive_service");
    }

    protected function prepareTenantWorkspace(): array
    {
        $tenant = Tenant::query()->create([
            'id' => 'tenant-backend-enforcement-' . Str::uuid(),
            'data' => ['company_name' => 'Backend Enforcement Test'],
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
            'max_users' => 5,
            'max_branches' => 3,
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'product_key' => $productKey,
            'plan_id' => $plan->id,
            'status' => 'active',
            'included_seats' => 5,
            'extra_seats' => 0,
            'branch_limit' => 3,
        ]);
    }

    protected function tenantUser(string $email): User
    {
        return User::query()->create([
            'name' => Str::headline(Str::before($email, '@')),
            'email' => $email,
            'password' => Hash::make('password'),
        ]);
    }

    protected function userWithPermissions(string $email, array $permissionKeys): User
    {
        $user = $this->tenantUser($email);
        app(TenantUserProductAccessService::class)->grantAccess($user, 'automotive_service');
        $role = $this->roleWithPermissions(Str::headline(Str::before($email, '@')) . ' Role', $permissionKeys);
        app(ProductPermissionService::class)->assignRole($user, $role);

        return $user;
    }

    protected function roleWithPermissions(string $name, array $permissionKeys): ProductRole
    {
        $role = ProductRole::query()->create([
            'tenant_id' => (string) tenant()->id,
            'product_key' => 'automotive_service',
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(6),
            'is_active' => true,
            'is_system' => false,
        ]);

        app(ProductPermissionService::class)->syncRolePermissions($role, $permissionKeys);

        return $role->refresh();
    }

    protected function branch(string $name): Branch
    {
        return Branch::query()->create([
            'name' => $name,
            'code' => Str::slug($name),
            'is_active' => true,
        ]);
    }

    protected function registerBranchScopedProbeRoute(): void
    {
        Route::middleware([
            'web',
            'auth:automotive_admin',
            'tenant.product.permission:automotive_service,automotive_service.work_orders.view,current_branch',
        ])->get('/package-16-branch-probe', fn () => 'ok');
    }
}
