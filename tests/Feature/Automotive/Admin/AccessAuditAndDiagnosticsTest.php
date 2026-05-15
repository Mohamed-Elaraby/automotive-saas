<?php

namespace Tests\Feature\Automotive\Admin;

use App\Models\Branch;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductRole;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\User;
use App\Services\Tenancy\AccessAuditService;
use App\Services\Tenancy\AccessDiagnosticsService;
use App\Services\Tenancy\ProductBranchAccessService;
use App\Services\Tenancy\ProductPermissionService;
use App\Services\Tenancy\TenantUserProductAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;
use Tests\TestCase;

class AccessAuditAndDiagnosticsTest extends TestCase
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

    public function test_product_access_grant_creates_audit_log(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $target = $this->tenantUser('target@example.test');
        tenancy()->end();

        $this->actingAs($owner, 'automotive_admin')
            ->put("http://{$domain}/workspace/admin/access/users/{$target->id}/products", [
                'products' => ['automotive_service'],
            ])
            ->assertRedirect();

        tenancy()->initialize($tenant);
        $this->assertDatabaseHas('access_audit_logs', [
            'event_key' => 'product_access.granted',
            'target_user_id' => $target->id,
            'product_key' => 'automotive_service',
        ]);
    }

    public function test_product_access_revoke_creates_audit_log(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $target = $this->tenantUser('target@example.test');
        app(TenantUserProductAccessService::class)->grantAccess($target, 'automotive_service', $owner);
        tenancy()->end();

        $this->actingAs($owner, 'automotive_admin')
            ->put("http://{$domain}/workspace/admin/access/users/{$target->id}/products", [
                'products' => [],
            ])
            ->assertRedirect();

        tenancy()->initialize($tenant);
        $this->assertDatabaseHas('access_audit_logs', [
            'event_key' => 'product_access.revoked',
            'target_user_id' => $target->id,
            'product_key' => 'automotive_service',
        ]);
    }

    public function test_branch_access_update_creates_audit_log(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $target = $this->tenantUser('target@example.test');
        $branch = $this->branch('Dubai Branch');
        app(TenantUserProductAccessService::class)->grantAccess($target, 'automotive_service', $owner);
        app(ProductBranchAccessService::class)->enableBranch($branch, 'automotive_service');
        tenancy()->end();

        $this->actingAs($owner, 'automotive_admin')
            ->put("http://{$domain}/workspace/admin/access/users/{$target->id}/branches", [
                'branches' => ['automotive_service' => [$branch->id]],
            ])
            ->assertRedirect();

        tenancy()->initialize($tenant);
        $this->assertDatabaseHas('access_audit_logs', [
            'event_key' => 'branch_access.granted',
            'target_user_id' => $target->id,
            'branch_id' => $branch->id,
        ]);
    }

    public function test_role_assignment_creates_audit_log(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $target = $this->tenantUser('target@example.test');
        app(TenantUserProductAccessService::class)->grantAccess($target, 'automotive_service', $owner);
        $role = $this->roleWithPermissions('Advisor Role', ['automotive_service.work_orders.view']);
        tenancy()->end();

        $this->actingAs($owner, 'automotive_admin')
            ->put("http://{$domain}/workspace/admin/access/users/{$target->id}/roles", [
                'roles' => ['automotive_service' => $role->id],
            ])
            ->assertRedirect();

        tenancy()->initialize($tenant);
        $this->assertDatabaseHas('access_audit_logs', [
            'event_key' => 'role.assigned',
            'target_user_id' => $target->id,
            'subject_id' => $role->id,
        ]);
    }

    public function test_role_permission_update_creates_audit_log(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $role = $this->roleWithPermissions('Permission Role', ['automotive_service.work_orders.view']);
        tenancy()->end();

        $this->actingAs($owner, 'automotive_admin')
            ->put("http://{$domain}/workspace/admin/access/roles/{$role->id}/permissions", [
                'permissions' => [
                    'automotive_service.work_orders.view',
                    'automotive_service.work_orders.create',
                ],
            ])
            ->assertRedirect();

        tenancy()->initialize($tenant);
        $this->assertDatabaseHas('access_audit_logs', [
            'event_key' => 'role_permissions.updated',
            'subject_id' => $role->id,
        ]);
    }

    public function test_owner_sync_creates_audit_log(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        tenancy()->end();

        $this->actingAs($owner, 'automotive_admin')
            ->post("http://{$domain}/workspace/admin/access/users/{$owner->id}/owner-sync")
            ->assertRedirect();

        tenancy()->initialize($tenant);
        $this->assertDatabaseHas('access_audit_logs', [
            'event_key' => 'owner_access.synced',
            'target_user_id' => $owner->id,
        ]);
    }

    public function test_forbidden_backend_action_creates_audit_log(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $this->tenantUser('owner@example.test');
        $actor = $this->userWithPermissions('user-manager@example.test', ['automotive_service.access.users.manage']);
        tenancy()->end();

        $this->actingAs($actor, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/access/roles")
            ->assertForbidden();

        tenancy()->initialize($tenant);
        $this->assertDatabaseHas('access_audit_logs', [
            'event_key' => 'forbidden_action.blocked',
            'actor_user_id' => $actor->id,
            'product_key' => 'automotive_service',
        ]);
    }

    public function test_owner_can_view_audit_logs_screen(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        app(AccessAuditService::class)->log([
            'product_key' => 'automotive_service',
            'action' => 'role.created',
            'event_key' => 'role.created',
        ]);
        tenancy()->end();

        $this->actingAs($owner, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/access/audit")
            ->assertOk()
            ->assertSee('Access Audit Logs')
            ->assertSee('role.created');
    }

    public function test_user_without_diagnostics_permission_cannot_view_diagnostics(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $this->tenantUser('owner@example.test');
        $user = $this->tenantUser('regular@example.test');
        tenancy()->end();

        $this->actingAs($user, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/access/diagnostics")
            ->assertForbidden();
    }

    public function test_diagnostics_detects_missing_product_access(): void
    {
        [$tenant] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $this->tenantUser('owner@example.test');
        $user = $this->tenantUser('target@example.test');

        $result = app(AccessDiagnosticsService::class)->diagnoseProductAccess($user, 'automotive_service');

        $this->assertFalse($result['final']['allowed']);
        $this->assertSame('missing_product_access', $result['final']['reason_code']);
    }

    public function test_diagnostics_detects_missing_branch_access(): void
    {
        [$tenant] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $user = $this->tenantUser('target@example.test');
        $branch = $this->branch('Ajman Branch');
        app(TenantUserProductAccessService::class)->grantAccess($user, 'automotive_service', $owner);
        app(ProductBranchAccessService::class)->enableBranch($branch, 'automotive_service');

        $result = app(AccessDiagnosticsService::class)->diagnoseBranchAccess($user, 'automotive_service', $branch->id);

        $this->assertFalse($result['final']['allowed']);
        $this->assertSame('missing_branch_access', $result['final']['reason_code']);
    }

    public function test_diagnostics_detects_missing_role(): void
    {
        [$tenant] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $user = $this->tenantUser('target@example.test');
        app(TenantUserProductAccessService::class)->grantAccess($user, 'automotive_service', $owner);
        app(ProductPermissionService::class)->createPermission('automotive_service', 'automotive_service.reports.view');

        $result = app(AccessDiagnosticsService::class)->diagnosePermission($user, 'automotive_service', 'automotive_service.reports.view');

        $this->assertFalse($result['final']['allowed']);
        $this->assertSame('missing_role', $result['final']['reason_code']);
    }

    public function test_diagnostics_detects_missing_permission(): void
    {
        [$tenant] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $user = $this->tenantUser('target@example.test');
        app(TenantUserProductAccessService::class)->grantAccess($user, 'automotive_service', $owner);
        app(ProductPermissionService::class)->createPermission('automotive_service', 'automotive_service.reports.export');
        $role = $this->roleWithPermissions('Viewer Role', ['automotive_service.reports.view']);
        app(ProductPermissionService::class)->assignRole($user, $role);

        $result = app(AccessDiagnosticsService::class)->diagnosePermission($user, 'automotive_service', 'automotive_service.reports.export');

        $this->assertFalse($result['final']['allowed']);
        $this->assertSame('missing_permission', $result['final']['reason_code']);
    }

    public function test_diagnostics_returns_owner_implicit_access_as_allowed(): void
    {
        [$tenant] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');

        $result = app(AccessDiagnosticsService::class)->diagnosePermission($owner, 'automotive_service', 'automotive_service.access.roles.manage');

        $this->assertTrue($result['final']['allowed']);
        $this->assertSame('owner_implicit_access', $result['final']['reason_code']);
    }

    public function test_diagnostics_ui_renders_result_cards(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $target = $this->tenantUser('target@example.test');
        tenancy()->end();

        $this->actingAs($owner, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/access/diagnostics?user_id={$target->id}&product_key=automotive_service")
            ->assertOk()
            ->assertSee('Final Decision')
            ->assertSee('Product Access');
    }

    public function test_audit_filters_by_product_action_and_actor(): void
    {
        [$tenant] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $other = $this->tenantUser('other@example.test');
        $audit = app(AccessAuditService::class);
        $audit->log([
            'actor_user_id' => $owner->id,
            'product_key' => 'automotive_service',
            'action' => 'product_access.granted',
            'event_key' => 'product_access.granted',
        ]);
        $audit->log([
            'actor_user_id' => $other->id,
            'product_key' => 'other_product',
            'action' => 'role.created',
            'event_key' => 'role.created',
        ]);

        $logs = $audit->paginate([
            'actor_user_id' => $owner->id,
            'product_key' => 'automotive_service',
            'event_key' => 'product_access.granted',
        ]);

        $this->assertSame(1, $logs->total());
        $this->assertSame('product_access.granted', $logs->getCollection()->first()->event_key);
    }

    protected function prepareTenantWorkspace(): array
    {
        $tenant = Tenant::query()->create([
            'id' => 'tenant-access-audit-' . Str::uuid(),
            'data' => ['company_name' => 'Access Audit Test'],
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
            'max_branches' => 5,
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'product_key' => $productKey,
            'plan_id' => $plan->id,
            'status' => 'active',
            'included_seats' => 10,
            'extra_seats' => 0,
            'branch_limit' => 5,
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
}
