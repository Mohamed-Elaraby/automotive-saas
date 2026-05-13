<?php

namespace Tests\Feature\Automotive\Admin;

use App\Models\Branch;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductRole;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\User;
use App\Services\Tenancy\AccessVisibilityService;
use App\Services\Tenancy\BranchContextService;
use App\Services\Tenancy\ProductBranchAccessService;
use App\Services\Tenancy\ProductPermissionService;
use App\Services\Tenancy\TenantUserProductAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;
use Tests\TestCase;

class MenuButtonVisibilityTest extends TestCase
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

    public function test_owner_can_see_access_control_sidebar_links(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $this->giveBranchContext($owner);
        tenancy()->end();

        $this->actingAs($owner, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/dashboard")
            ->assertOk()
            ->assertSee('Access Control', false);
    }

    public function test_user_without_access_management_permission_cannot_see_access_control_sidebar_links(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $this->tenantUser('owner@example.test');
        $user = $this->tenantUser('viewer@example.test');
        app(TenantUserProductAccessService::class)->grantAccess($user, 'automotive_service');
        $branch = $this->giveBranchContext($user);
        tenancy()->end();

        $this->withSession([
            BranchContextService::SESSION_PRODUCT_KEY => 'automotive_service',
            BranchContextService::SESSION_BRANCH_ID => $branch->id,
        ])
            ->actingAs($user, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/dashboard")
            ->assertOk()
            ->assertDontSee('Access Control', false);
    }

    public function test_user_with_roles_manage_permission_can_see_roles_menu_link(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $this->tenantUser('owner@example.test');
        $user = $this->userWithPermissions('roles-manager@example.test', ['automotive_service.access.roles.manage']);
        $branch = $this->giveBranchContext($user);
        tenancy()->end();

        $this->withSession([
            BranchContextService::SESSION_PRODUCT_KEY => 'automotive_service',
            BranchContextService::SESSION_BRANCH_ID => $branch->id,
        ])
            ->actingAs($user, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/dashboard")
            ->assertOk()
            ->assertSee('Access Control', false);
    }

    public function test_user_without_roles_manage_permission_cannot_see_create_role_button(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $this->tenantUser('owner@example.test');
        $user = $this->userWithPermissions('access-manager@example.test', ['automotive_service.access.manage']);
        tenancy()->end();

        $this->actingAs($user, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/access/roles")
            ->assertOk()
            ->assertDontSee('New Role', false);
    }

    public function test_user_with_roles_manage_permission_can_see_create_role_button(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $this->tenantUser('owner@example.test');
        $user = $this->userWithPermissions('roles-manager@example.test', ['automotive_service.access.roles.manage']);
        tenancy()->end();

        $this->actingAs($user, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/access/roles")
            ->assertOk()
            ->assertSee('New Role', false);
    }

    public function test_user_without_branch_manage_permission_cannot_see_enable_product_branch_action(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $this->tenantUser('owner@example.test');
        $user = $this->userWithPermissions('access-manager@example.test', ['automotive_service.access.roles.manage']);
        $this->branch('Main Branch');
        tenancy()->end();

        $this->actingAs($user, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/access/products/automotive_service/branches")
            ->assertOk()
            ->assertDontSee('name="branches[]"', false)
            ->assertSee('Read only', false);
    }

    public function test_owner_can_see_sync_owner_access_action(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        tenancy()->end();

        $this->actingAs($owner, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/access/users")
            ->assertOk()
            ->assertSee('Sync Owner Access', false);
    }

    public function test_non_owner_cannot_see_sync_owner_access_action(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $this->tenantUser('owner@example.test');
        $user = $this->userWithPermissions('access-manager@example.test', ['automotive_service.access.manage']);
        tenancy()->end();

        $this->actingAs($user, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/access/users")
            ->assertOk()
            ->assertDontSee('Sync Owner Access', false);
    }

    public function test_viewer_sees_read_only_state_without_manage_actions(): void
    {
        [$tenant] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $this->tenantUser('owner@example.test');
        $viewer = $this->userWithPermissions('viewer@example.test', ['automotive_service.work_orders.view']);
        $visibility = app(AccessVisibilityService::class);

        $this->assertTrue($visibility->canSeeMenu($viewer, 'service.work-orders', 'automotive_service'));
        $this->assertTrue($visibility->canSeeAction($viewer, 'automotive_service.work_orders.view', 'automotive_service'));
        $this->assertFalse($visibility->canSeeAction($viewer, 'automotive_service.work_orders.create', 'automotive_service'));
        $this->assertFalse($visibility->canSeeAction($viewer, 'automotive_service.work_orders.delete', 'automotive_service'));
    }

    public function test_blade_directives_handle_guest_no_guard_safely(): void
    {
        $rendered = Blade::render("@productCan('automotive_service.access.roles.manage', 'automotive_service') visible @else hidden @endproductCan");

        $this->assertStringContainsString('hidden', $rendered);
        $this->assertStringNotContainsString('visible', $rendered);
    }

    public function test_branch_switcher_only_lists_allowed_branches(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $this->tenantUser('owner@example.test');
        $user = $this->userWithPermissions('advisor@example.test', ['automotive_service.dashboard.view']);
        $allowedA = $this->branch('Allowed A');
        $allowedB = $this->branch('Allowed B');
        $forbidden = $this->branch('Forbidden Branch');

        foreach ([$allowedA, $allowedB, $forbidden] as $branch) {
            app(ProductBranchAccessService::class)->enableBranch($branch, 'automotive_service');
        }

        app(ProductBranchAccessService::class)->grantUserBranchAccess($user, $allowedA, 'automotive_service');
        app(ProductBranchAccessService::class)->grantUserBranchAccess($user, $allowedB, 'automotive_service');
        tenancy()->end();

        $this->withSession([
            BranchContextService::SESSION_PRODUCT_KEY => 'automotive_service',
            BranchContextService::SESSION_BRANCH_ID => $allowedA->id,
        ])
            ->actingAs($user, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/dashboard")
            ->assertOk()
            ->assertSee('Allowed A', false)
            ->assertSee('Allowed B', false)
            ->assertDontSee('<i class="isax isax-location me-2"></i>Forbidden Branch', false);
    }

    public function test_menu_group_hides_when_no_visible_children(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $this->tenantUser('owner@example.test');
        $user = $this->tenantUser('regular@example.test');
        app(TenantUserProductAccessService::class)->grantAccess($user, 'automotive_service');
        $this->giveBranchContext($user);
        tenancy()->end();

        $this->actingAs($user, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/dashboard")
            ->assertOk()
            ->assertDontSee('<li class="menu-title"><span>Automotive Service</span></li>', false)
            ->assertDontSee('Work Orders', false);
    }

    protected function prepareTenantWorkspace(): array
    {
        $tenant = Tenant::query()->create([
            'id' => 'tenant-menu-visibility-' . Str::uuid(),
            'data' => ['company_name' => 'Menu Visibility Test'],
        ]);

        $this->tenantDatabaseFiles[] = database_path($tenant->database()->getName());

        $domain = $tenant->id . '.example.test';

        Domain::query()->create([
            'domain' => $domain,
            'tenant_id' => $tenant->id,
        ]);

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

    protected function giveBranchContext(User $user): Branch
    {
        $branch = $this->branch('Context Branch ' . Str::random(6));
        app(ProductBranchAccessService::class)->enableBranch($branch, 'automotive_service');

        if ((int) $user->id !== 1) {
            app(ProductBranchAccessService::class)->grantUserBranchAccess($user, $branch, 'automotive_service');
        }

        return $branch;
    }

    protected function branch(string $name): Branch
    {
        return Branch::query()->create([
            'name' => $name,
            'code' => Str::slug($name),
            'is_active' => true,
        ]);
    }
}
