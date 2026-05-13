<?php

namespace Tests\Feature\Automotive\Admin;

use App\Models\Branch;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductRole;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\User;
use App\Services\Tenancy\EffectiveUserAccessService;
use App\Services\Tenancy\ProductPermissionService;
use App\Services\Tenancy\TenantUserProductAccessService;
use Database\Seeders\TenantProductPermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;
use Tests\TestCase;

class UserAccessProfileTest extends TestCase
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

    public function test_owner_can_view_user_access_profile(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $user = $this->tenantUser('advisor@example.test');
        tenancy()->end();

        $response = $this->actingAs($owner, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/access/users/{$user->id}");

        $response->assertOk();
        $response->assertSee('User Access Profile', false);
        $response->assertSee('Overview', false);
        $response->assertSee('Effective Permissions', false);
    }

    public function test_profile_shows_product_branch_role_and_permission_summaries(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $user = $this->tenantUser('advisor@example.test');
        $branch = $this->branch('Main Branch');
        $this->seedCatalog();
        app(TenantUserProductAccessService::class)->grantAccess($user, 'automotive_service', $owner);
        app(\App\Services\Tenancy\ProductBranchAccessService::class)->enableBranch($branch, 'automotive_service');
        app(\App\Services\Tenancy\ProductBranchAccessService::class)->grantUserBranchAccess($user, $branch, 'automotive_service');
        $role = $this->roleWithPermissions('Service Advisor', ['automotive_service.work_orders.view']);
        app(ProductPermissionService::class)->assignRole($user, $role);
        tenancy()->end();

        $response = $this->actingAs($owner, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/access/users/{$user->id}");

        $response->assertOk();
        $response->assertSee('Automotive Service', false);
        $response->assertSee('Main Branch', false);
        $response->assertSee('Service Advisor', false);
        $response->assertSee('automotive_service.work_orders.view', false);
    }

    public function test_workspace_owner_is_shown_as_owner_access_not_missing_product_access(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        tenancy()->end();

        $response = $this->actingAs($owner, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/access/users/{$owner->id}");

        $response->assertOk();
        $response->assertSee('Owner Access', false);
        $response->assertSee('Implicit Full Access', false);
        $response->assertSee('Does not consume product seat', false);
        $response->assertDontSee('User has no product access', false);
    }

    public function test_owner_can_assign_role_to_user_for_product(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $user = $this->tenantUser('advisor@example.test');
        app(TenantUserProductAccessService::class)->grantAccess($user, 'automotive_service', $owner);
        $role = $this->roleWithPermissions('Manager Role', ['automotive_service.work_orders.view']);
        tenancy()->end();

        $this->actingAs($owner, 'automotive_admin')
            ->put("http://{$domain}/workspace/admin/access/users/{$user->id}/roles", [
                'roles' => ['automotive_service' => $role->id],
            ])
            ->assertRedirect("http://{$domain}/workspace/admin/access/users/{$user->id}");

        tenancy()->initialize($tenant);
        $this->assertDatabaseHas('tenant_user_product_roles', [
            'user_id' => $user->id,
            'product_key' => 'automotive_service',
            'product_role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    public function test_cannot_assign_role_to_user_without_product_access(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $user = $this->tenantUser('advisor@example.test');
        $role = $this->roleWithPermissions('Manager Role', ['automotive_service.work_orders.view']);
        tenancy()->end();

        $this->actingAs($owner, 'automotive_admin')
            ->from("http://{$domain}/workspace/admin/access/users/{$user->id}/roles")
            ->put("http://{$domain}/workspace/admin/access/users/{$user->id}/roles", [
                'roles' => ['automotive_service' => $role->id],
            ])
            ->assertSessionHasErrors('roles');
    }

    public function test_cannot_assign_role_from_another_product(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();
        $this->attachProductSubscription($tenant, 'parts_inventory');

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $user = $this->tenantUser('advisor@example.test');
        app(TenantUserProductAccessService::class)->grantAccess($user, 'automotive_service', $owner);
        $role = ProductRole::query()->create([
            'tenant_id' => (string) tenant()->id,
            'product_key' => 'parts_inventory',
            'name' => 'Parts Manager',
            'slug' => 'parts-manager',
            'is_active' => true,
            'is_system' => false,
        ]);
        tenancy()->end();

        $this->actingAs($owner, 'automotive_admin')
            ->from("http://{$domain}/workspace/admin/access/users/{$user->id}/roles")
            ->put("http://{$domain}/workspace/admin/access/users/{$user->id}/roles", [
                'roles' => ['automotive_service' => $role->id],
            ])
            ->assertSessionHasErrors('roles');
    }

    public function test_role_assignment_updates_effective_permissions(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $user = $this->tenantUser('advisor@example.test');
        app(TenantUserProductAccessService::class)->grantAccess($user, 'automotive_service', $owner);
        $role = $this->roleWithPermissions('Work Order Viewer', ['automotive_service.work_orders.view']);
        tenancy()->end();

        $this->actingAs($owner, 'automotive_admin')
            ->put("http://{$domain}/workspace/admin/access/users/{$user->id}/roles", [
                'roles' => ['automotive_service' => $role->id],
            ]);

        tenancy()->initialize($tenant);
        $permissions = app(EffectiveUserAccessService::class)->effectivePermissionsForUser($user, 'automotive_service');
        $this->assertTrue($permissions->flatMap(fn (array $group) => $group['permissions'])->contains(fn (array $permission): bool => $permission['permission_key'] === 'automotive_service.work_orders.view' && $permission['granted']));
    }

    public function test_effective_permissions_are_grouped_by_product_module_and_action(): void
    {
        [$tenant] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $this->tenantUser('owner@example.test');
        $user = $this->tenantUser('advisor@example.test');
        $groups = app(EffectiveUserAccessService::class)->effectivePermissionsForUser($user, 'automotive_service');

        $this->assertTrue($groups->contains(fn (array $group): bool => $group['product_key'] === 'automotive_service' && $group['module_key'] === 'work_orders'));
        $this->assertTrue($groups->firstWhere('module_key', 'work_orders')['permissions']->contains(fn (array $permission): bool => $permission['action'] === 'view'));
    }

    public function test_warnings_show_product_access_without_branch_access(): void
    {
        [$tenant] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $user = $this->tenantUser('advisor@example.test');
        app(TenantUserProductAccessService::class)->grantAccess($user, 'automotive_service', $owner);
        $warnings = app(EffectiveUserAccessService::class)->accessWarningsForUser($user);

        $this->assertTrue($warnings->contains(fn (array $warning): bool => str_contains($warning['message'], 'no branch access')));
    }

    public function test_warnings_show_product_access_without_role(): void
    {
        [$tenant] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $user = $this->tenantUser('advisor@example.test');
        app(TenantUserProductAccessService::class)->grantAccess($user, 'automotive_service', $owner);
        $warnings = app(EffectiveUserAccessService::class)->accessWarningsForUser($user);

        $this->assertTrue($warnings->contains(fn (array $warning): bool => str_contains($warning['message'], 'no role')));
    }

    public function test_owner_self_lockout_is_prevented(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $this->seedCatalog();
        tenancy()->end();

        $this->actingAs($owner, 'automotive_admin')
            ->from("http://{$domain}/workspace/admin/access/users/{$owner->id}/roles")
            ->put("http://{$domain}/workspace/admin/access/users/{$owner->id}/roles", [
                'roles' => ['automotive_service' => ''],
            ])
            ->assertSessionHasErrors('roles');
    }

    public function test_access_users_list_links_to_access_profile(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $user = $this->tenantUser('advisor@example.test');
        tenancy()->end();

        $response = $this->actingAs($owner, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/access/users");

        $response->assertOk();
        $response->assertSee('View Access Profile', false);
        $response->assertSee("/workspace/admin/access/users/{$user->id}", false);
    }

    protected function prepareTenantWorkspace(): array
    {
        $tenant = Tenant::query()->create([
            'id' => 'tenant-user-access-profile-' . Str::uuid(),
            'data' => ['company_name' => 'User Access Profile Test'],
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

    protected function branch(string $name): Branch
    {
        return Branch::query()->create([
            'name' => $name,
            'code' => Str::slug($name),
            'is_active' => true,
        ]);
    }

    protected function roleWithPermissions(string $name, array $permissionKeys): ProductRole
    {
        $role = ProductRole::query()->create([
            'tenant_id' => (string) tenant()->id,
            'product_key' => 'automotive_service',
            'name' => $name,
            'slug' => Str::slug($name),
            'is_active' => true,
            'is_system' => false,
        ]);

        app(ProductPermissionService::class)->syncRolePermissions($role, $permissionKeys);

        return $role->refresh();
    }

    protected function seedCatalog(): void
    {
        $this->seed(TenantProductPermissionCatalogSeeder::class);
    }
}
