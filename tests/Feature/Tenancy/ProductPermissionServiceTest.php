<?php

namespace Tests\Feature\Tenancy;

use App\Models\Branch;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\User;
use App\Services\Tenancy\ProductBranchAccessService;
use App\Services\Tenancy\ProductPermissionService;
use App\Services\Tenancy\TenantUserProductAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;
use Tests\TestCase;

class ProductPermissionServiceTest extends TestCase
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

    public function test_user_can_have_role_in_one_product_only(): void
    {
        $tenant = $this->prepareTenant(['automotive_service', 'accounting']);

        tenancy()->initialize($tenant);

        $user = $this->tenantUser('advisor@example.test');
        app(TenantUserProductAccessService::class)->grantAccess($user, 'automotive_service');

        $permissions = app(ProductPermissionService::class);
        $role = $permissions->createRole('automotive_service', 'Advisor');
        $permissions->syncRolePermissions($role, ['automotive.work_orders.view']);
        $permissions->assignRole($user, $role);

        $this->assertTrue($permissions->can($user, 'automotive_service', 'automotive.work_orders.view'));
        $this->assertFalse($permissions->can($user, 'accounting', 'accounting.payments.approve'));
    }

    public function test_same_role_name_can_exist_in_different_products(): void
    {
        $tenant = $this->prepareTenant(['automotive_service', 'accounting']);

        tenancy()->initialize($tenant);

        $permissions = app(ProductPermissionService::class);
        $automotiveRole = $permissions->createRole('automotive_service', 'Manager');
        $accountingRole = $permissions->createRole('accounting', 'Manager');

        $this->assertSame('manager', $automotiveRole->slug);
        $this->assertSame('manager', $accountingRole->slug);
        $this->assertNotSame($automotiveRole->id, $accountingRole->id);
        $this->assertSame('automotive_service', $automotiveRole->product_key);
        $this->assertSame('accounting', $accountingRole->product_key);
    }

    public function test_permission_check_respects_product_key(): void
    {
        $tenant = $this->prepareTenant(['automotive_service', 'accounting']);

        tenancy()->initialize($tenant);

        $user = $this->tenantUser('scoped@example.test');
        $access = app(TenantUserProductAccessService::class);
        $access->grantAccess($user, 'automotive_service');
        $access->grantAccess($user, 'accounting');

        $permissions = app(ProductPermissionService::class);
        $automotiveRole = $permissions->createRole('automotive_service', 'Work Order Manager');
        $accountingRole = $permissions->createRole('accounting', 'Payment Approver');
        $permissions->syncRolePermissions($automotiveRole, ['automotive.work_orders.create']);
        $permissions->syncRolePermissions($accountingRole, ['accounting.payments.approve']);
        $permissions->assignRole($user, $automotiveRole);

        $this->assertTrue($permissions->can($user, 'automotive_service', 'automotive.work_orders.create'));
        $this->assertFalse($permissions->can($user, 'accounting', 'accounting.payments.approve'));
    }

    public function test_user_without_product_access_cannot_pass_permission_check(): void
    {
        $tenant = $this->prepareTenant(['automotive_service']);

        tenancy()->initialize($tenant);

        $user = $this->tenantUser('no-access@example.test');
        $permissions = app(ProductPermissionService::class);
        $role = $permissions->createRole('automotive_service', 'Advisor');
        $permissions->syncRolePermissions($role, ['automotive.work_orders.view']);

        $this->assertFalse($permissions->can($user, 'automotive_service', 'automotive.work_orders.view'));
    }

    public function test_inactive_or_revoked_product_access_blocks_permission_check(): void
    {
        $tenant = $this->prepareTenant(['automotive_service']);

        tenancy()->initialize($tenant);

        $user = $this->tenantUser('revoked-access@example.test');
        $access = app(TenantUserProductAccessService::class);
        $access->grantAccess($user, 'automotive_service');

        $permissions = app(ProductPermissionService::class);
        $role = $permissions->createRole('automotive_service', 'Advisor');
        $permissions->syncRolePermissions($role, ['automotive.work_orders.view']);
        $permissions->assignRole($user, $role);

        $this->assertTrue($permissions->can($user, 'automotive_service', 'automotive.work_orders.view'));

        $access->revokeAccess($user, 'automotive_service');

        $this->assertFalse($permissions->can($user, 'automotive_service', 'automotive.work_orders.view'));
    }

    public function test_branch_access_can_be_checked_with_permission_when_branch_id_exists(): void
    {
        $tenant = $this->prepareTenant(['automotive_service']);

        tenancy()->initialize($tenant);

        $user = $this->tenantUser('branch-permission@example.test');
        app(TenantUserProductAccessService::class)->grantAccess($user, 'automotive_service');

        $allowedBranch = $this->branch('AUH-MAIN', 'Abu Dhabi Main Branch');
        $blockedBranch = $this->branch('DXB-BR', 'Dubai Branch');
        $branchAccess = app(ProductBranchAccessService::class);
        $branchAccess->enableBranch($allowedBranch, 'automotive_service');
        $branchAccess->enableBranch($blockedBranch, 'automotive_service');
        $branchAccess->grantUserBranchAccess($user, $allowedBranch, 'automotive_service');

        $permissions = app(ProductPermissionService::class);
        $role = $permissions->createRole('automotive_service', 'Branch Advisor');
        $permissions->syncRolePermissions($role, ['automotive.work_orders.view']);
        $permissions->assignRole($user, $role);

        $this->assertTrue($permissions->can($user, 'automotive_service', 'automotive.work_orders.view', $allowedBranch->id));
        $this->assertFalse($permissions->can($user, 'automotive_service', 'automotive.work_orders.view', $blockedBranch->id));
    }

    protected function prepareTenant(array $productKeys): Tenant
    {
        $tenant = Tenant::query()->create([
            'id' => 'tenant-permission-' . Str::uuid(),
            'data' => ['company_name' => 'Tenant Permission Test'],
        ]);

        $this->tenantDatabaseFiles[] = database_path($tenant->database()->getName());

        Domain::query()->create([
            'domain' => $tenant->id . '.example.test',
            'tenant_id' => $tenant->id,
        ]);

        foreach ($productKeys as $productKey) {
            $this->attachProductSubscription($tenant, $productKey);
        }

        Artisan::call('tenants:migrate', [
            '--tenants' => [$tenant->id],
            '--force' => true,
        ]);

        return $tenant;
    }

    protected function attachProductSubscription(Tenant $tenant, string $productKey): void
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
            'name' => Str::headline($productKey) . ' Permission Plan',
            'slug' => Str::slug($productKey) . '-permission-plan-' . uniqid(),
            'price' => 99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'max_users' => 5,
            'max_branches' => 3,
        ]);

        DB::table('plan_limits')->insert([
            [
                'product_key' => $productKey,
                'plan_id' => $plan->id,
                'limit_key' => 'included_seats',
                'limit_value' => '5',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'product_key' => $productKey,
                'plan_id' => $plan->id,
                'limit_key' => 'branch_limit',
                'limit_value' => '3',
                'created_at' => now(),
                'updated_at' => now(),
            ],
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
            'password' => bcrypt('secret-pass'),
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
}
