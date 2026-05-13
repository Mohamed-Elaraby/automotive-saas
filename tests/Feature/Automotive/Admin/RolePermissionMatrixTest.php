<?php

namespace Tests\Feature\Automotive\Admin;

use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductRole;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\User;
use App\Services\Tenancy\ProductPermissionCatalogService;
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

class RolePermissionMatrixTest extends TestCase
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

    public function test_owner_can_view_roles_index(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $this->seedCatalog();
        tenancy()->end();

        $response = $this->actingAs($owner, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/access/roles");

        $response->assertOk();
        $response->assertSee('Roles &amp; Permission Matrix', false);
        $response->assertSee('Tenant Owner', false);
    }

    public function test_owner_can_create_product_role(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        tenancy()->end();

        $response = $this->actingAs($owner, 'automotive_admin')
            ->post("http://{$domain}/workspace/admin/access/roles", [
                'product_key' => 'automotive_service',
                'name' => 'Automotive Test Manager',
                'description' => 'Test manager role',
                'is_active' => '1',
            ]);

        $response->assertRedirect();

        tenancy()->initialize($tenant);
        $this->assertDatabaseHas('product_roles', [
            'product_key' => 'automotive_service',
            'slug' => 'automotive-test-manager',
        ]);
    }

    public function test_role_name_can_repeat_across_products_but_not_within_same_product(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();
        $this->attachProductSubscription($tenant, 'parts_inventory');

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        tenancy()->end();

        $this->actingAs($owner, 'automotive_admin')
            ->post("http://{$domain}/workspace/admin/access/roles", [
                'product_key' => 'automotive_service',
                'name' => 'Shared Manager',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->actingAs($owner, 'automotive_admin')
            ->post("http://{$domain}/workspace/admin/access/roles", [
                'product_key' => 'parts_inventory',
                'name' => 'Shared Manager',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->actingAs($owner, 'automotive_admin')
            ->from("http://{$domain}/workspace/admin/access/roles/create")
            ->post("http://{$domain}/workspace/admin/access/roles", [
                'product_key' => 'automotive_service',
                'name' => 'Shared Manager',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_owner_can_edit_product_role(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $role = $this->role('Advisor');
        tenancy()->end();

        $this->actingAs($owner, 'automotive_admin')
            ->put("http://{$domain}/workspace/admin/access/roles/{$role->id}", [
                'product_key' => 'automotive_service',
                'name' => 'Senior Advisor',
                'description' => 'Updated',
                'is_active' => '1',
            ])
            ->assertRedirect("http://{$domain}/workspace/admin/access/roles");

        tenancy()->initialize($tenant);
        $this->assertDatabaseHas('product_roles', ['id' => $role->id, 'slug' => 'senior-advisor']);
    }

    public function test_owner_can_open_permission_matrix(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $role = $this->role('Matrix Role');
        tenancy()->end();

        $response = $this->actingAs($owner, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/access/roles/{$role->id}/permissions");

        $response->assertOk();
        $response->assertSee('Permission Matrix', false);
        $response->assertSee('automotive_service.work_orders.view', false);
    }

    public function test_owner_can_update_role_permissions(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $role = $this->role('Permission Role');
        $this->seedCatalog();
        tenancy()->end();

        $permissions = [
            'automotive_service.work_orders.view',
            'automotive_service.work_orders.create',
            'automotive_service.estimates.approve',
            'automotive_service.reports.view',
        ];

        $this->actingAs($owner, 'automotive_admin')
            ->put("http://{$domain}/workspace/admin/access/roles/{$role->id}/permissions", [
                'permissions' => $permissions,
            ])
            ->assertRedirect("http://{$domain}/workspace/admin/access/roles/{$role->id}/permissions");

        tenancy()->initialize($tenant);
        $this->assertEqualsCanonicalizing($permissions, $role->refresh()->permissions()->pluck('permission_key')->all());
    }

    public function test_permissions_are_grouped_by_module(): void
    {
        [$tenant] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $groups = app(ProductPermissionCatalogService::class)->groupedPermissionsForProduct('automotive_service');

        $this->assertTrue($groups->contains(fn (array $group): bool => $group['module_key'] === 'work_orders'));
        $this->assertTrue($groups->contains(fn (array $group): bool => $group['module_key'] === 'access.roles'));
    }

    public function test_user_without_access_management_permission_cannot_manage_roles(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $this->tenantUser('owner@example.test');
        $regularUser = $this->tenantUser('regular@example.test');
        tenancy()->end();

        $this->actingAs($regularUser, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/access/roles")
            ->assertForbidden();
    }

    public function test_role_with_assigned_users_cannot_be_deleted(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $user = $this->tenantUser('assigned@example.test');
        $role = $this->role('Assigned Role');
        app(TenantUserProductAccessService::class)->grantAccess($user, 'automotive_service', $owner);
        app(ProductPermissionService::class)->assignRole($user, $role);
        tenancy()->end();

        $this->actingAs($owner, 'automotive_admin')
            ->from("http://{$domain}/workspace/admin/access/roles")
            ->delete("http://{$domain}/workspace/admin/access/roles/{$role->id}")
            ->assertSessionHasErrors('role');

        tenancy()->initialize($tenant);
        $this->assertDatabaseHas('product_roles', ['id' => $role->id]);
    }

    public function test_system_owner_role_cannot_be_deleted(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $this->seedCatalog();
        $role = ProductRole::query()->where('slug', 'tenant-owner')->firstOrFail();
        tenancy()->end();

        $this->actingAs($owner, 'automotive_admin')
            ->from("http://{$domain}/workspace/admin/access/roles")
            ->delete("http://{$domain}/workspace/admin/access/roles/{$role->id}")
            ->assertSessionHasErrors('role');

        tenancy()->initialize($tenant);
        $this->assertDatabaseHas('product_roles', ['id' => $role->id]);
    }

    public function test_role_templates_seeder_is_idempotent(): void
    {
        [$tenant] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $this->seedCatalog();
        $firstRoles = ProductRole::query()->count();
        $firstPermissions = \App\Models\ProductPermission::query()->count();

        $this->seedCatalog();

        $this->assertSame($firstRoles, ProductRole::query()->count());
        $this->assertSame($firstPermissions, \App\Models\ProductPermission::query()->count());
    }

    public function test_route_names_are_under_automotive_admin_access_roles(): void
    {
        $routeNames = collect(app('router')->getRoutes()->getRoutes())
            ->map(fn ($route): ?string => $route->getName())
            ->filter()
            ->values();

        foreach ([
            'automotive.admin.access.roles.index',
            'automotive.admin.access.roles.create',
            'automotive.admin.access.roles.store',
            'automotive.admin.access.roles.edit',
            'automotive.admin.access.roles.update',
            'automotive.admin.access.roles.destroy',
            'automotive.admin.access.roles.duplicate',
            'automotive.admin.access.roles.permissions.edit',
            'automotive.admin.access.roles.permissions.update',
        ] as $routeName) {
            $this->assertContains($routeName, $routeNames);
        }
    }

    protected function prepareTenantWorkspace(): array
    {
        $tenant = Tenant::query()->create([
            'id' => 'tenant-role-matrix-' . Str::uuid(),
            'data' => ['company_name' => 'Role Matrix Test'],
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

    protected function role(string $name): ProductRole
    {
        return ProductRole::query()->create([
            'tenant_id' => (string) tenant()->id,
            'product_key' => 'automotive_service',
            'name' => $name,
            'slug' => Str::slug($name),
            'is_active' => true,
            'is_system' => false,
        ]);
    }

    protected function seedCatalog(): void
    {
        $this->seed(TenantProductPermissionCatalogSeeder::class);
    }
}
