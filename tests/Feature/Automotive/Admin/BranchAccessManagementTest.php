<?php

namespace Tests\Feature\Automotive\Admin;

use App\Models\Branch;
use App\Models\Plan;
use App\Models\Product;
use App\Models\SubscriptionAddon;
use App\Models\Tenant;
use App\Models\TenantProductBranch;
use App\Models\TenantProductSubscription;
use App\Models\TenantUserProductBranch;
use App\Models\User;
use App\Services\Tenancy\ProductBranchAccessService;
use App\Services\Tenancy\TenantUserProductAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;
use Tests\TestCase;

class BranchAccessManagementTest extends TestCase
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

    public function test_owner_can_view_product_branch_activation_page(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace(2);

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $this->branch('Abu Dhabi');
        tenancy()->end();

        $response = $this
            ->actingAs($owner, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/access/products/automotive_service/branches");

        $response->assertOk();
        $response->assertSee('Product Branches', false);
        $response->assertSee('Abu Dhabi', false);
    }

    public function test_owner_can_enable_branch_for_product_when_limit_allows(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace(2);

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $branch = $this->branch('Dubai');
        tenancy()->end();

        $response = $this
            ->actingAs($owner, 'automotive_admin')
            ->put("http://{$domain}/workspace/admin/access/products/automotive_service/branches", [
                'branches' => [$branch->id],
            ]);

        $response->assertRedirect("http://{$domain}/workspace/admin/access/products/automotive_service/branches");

        tenancy()->initialize($tenant);
        $this->assertDatabaseHas('tenant_product_branches', [
            'product_key' => 'automotive_service',
            'branch_id' => $branch->id,
            'is_enabled' => true,
        ]);
    }

    public function test_cannot_enable_branch_when_branch_limit_exceeded(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace(1);

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $firstBranch = $this->branch('Abu Dhabi');
        $secondBranch = $this->branch('Dubai');
        tenancy()->end();

        $response = $this
            ->actingAs($owner, 'automotive_admin')
            ->from("http://{$domain}/workspace/admin/access/products/automotive_service/branches")
            ->put("http://{$domain}/workspace/admin/access/products/automotive_service/branches", [
                'branches' => [$firstBranch->id, $secondBranch->id],
            ]);

        $response->assertRedirect("http://{$domain}/workspace/admin/access/products/automotive_service/branches");
        $response->assertSessionHasErrors('branches');

        tenancy()->initialize($tenant);
        $this->assertSame(0, TenantProductBranch::query()->enabled()->count());
    }

    public function test_extra_branch_addon_increases_branch_limit(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace(1);

        SubscriptionAddon::query()->create([
            'tenant_id' => $tenant->id,
            'product_key' => 'automotive_service',
            'addon_key' => 'extra_branch',
            'quantity' => 1,
            'status' => 'active',
        ]);

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $firstBranch = $this->branch('Abu Dhabi');
        $secondBranch = $this->branch('Dubai');
        tenancy()->end();

        $response = $this
            ->actingAs($owner, 'automotive_admin')
            ->put("http://{$domain}/workspace/admin/access/products/automotive_service/branches", [
                'branches' => [$firstBranch->id, $secondBranch->id],
            ]);

        $response->assertRedirect("http://{$domain}/workspace/admin/access/products/automotive_service/branches");

        tenancy()->initialize($tenant);
        $this->assertSame(2, TenantProductBranch::query()->enabled()->count());
    }

    public function test_owner_can_assign_product_branch_to_user(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace(2);

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $user = $this->tenantUser('advisor@example.test');
        $branch = $this->branch('Sharjah');
        app(TenantUserProductAccessService::class)->grantAccess($user, 'automotive_service', $owner);
        app(ProductBranchAccessService::class)->enableBranch($branch, 'automotive_service');
        tenancy()->end();

        $response = $this
            ->actingAs($owner, 'automotive_admin')
            ->put("http://{$domain}/workspace/admin/access/users/{$user->id}/branches", [
                'branches' => [
                    'automotive_service' => [$branch->id],
                ],
            ]);

        $response->assertRedirect("http://{$domain}/workspace/admin/access/users/{$user->id}/branches");

        tenancy()->initialize($tenant);
        $this->assertDatabaseHas('tenant_user_product_branches', [
            'user_id' => $user->id,
            'product_key' => 'automotive_service',
            'branch_id' => $branch->id,
            'is_enabled' => true,
        ]);
    }

    public function test_cannot_assign_branch_to_user_without_product_access(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace(2);

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $user = $this->tenantUser('advisor@example.test');
        $branch = $this->branch('Dubai');
        app(ProductBranchAccessService::class)->enableBranch($branch, 'automotive_service');
        tenancy()->end();

        $response = $this
            ->actingAs($owner, 'automotive_admin')
            ->from("http://{$domain}/workspace/admin/access/users/{$user->id}/branches")
            ->put("http://{$domain}/workspace/admin/access/users/{$user->id}/branches", [
                'branches' => [
                    'automotive_service' => [$branch->id],
                ],
            ]);

        $response->assertRedirect("http://{$domain}/workspace/admin/access/users/{$user->id}/branches");
        $response->assertSessionHasErrors('branches');
    }

    public function test_cannot_assign_branch_that_is_not_enabled_for_product(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace(2);

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $user = $this->tenantUser('advisor@example.test');
        $branch = $this->branch('Inactive For Product');
        app(TenantUserProductAccessService::class)->grantAccess($user, 'automotive_service', $owner);
        tenancy()->end();

        $response = $this
            ->actingAs($owner, 'automotive_admin')
            ->from("http://{$domain}/workspace/admin/access/users/{$user->id}/branches")
            ->put("http://{$domain}/workspace/admin/access/users/{$user->id}/branches", [
                'branches' => [
                    'automotive_service' => [$branch->id],
                ],
            ]);

        $response->assertRedirect("http://{$domain}/workspace/admin/access/users/{$user->id}/branches");
        $response->assertSessionHasErrors('branches');
    }

    public function test_branch_switcher_only_shows_allowed_branches(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace(2);

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $user = $this->tenantUser('advisor@example.test');
        $allowed = $this->branch('Allowed Branch');
        $blocked = $this->branch('Blocked Branch');
        $branchAccess = app(ProductBranchAccessService::class);
        $productAccess = app(TenantUserProductAccessService::class);
        $productAccess->grantAccess($user, 'automotive_service', $owner);
        $branchAccess->enableBranch($allowed, 'automotive_service');
        $branchAccess->enableBranch($blocked, 'automotive_service');
        $branchAccess->grantUserBranchAccess($user, $allowed, 'automotive_service');
        tenancy()->end();

        $response = $this
            ->actingAs($user, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/access/branch-context/select");

        $response->assertOk();
        $response->assertSee('Allowed Branch', false);
        $response->assertDontSee('Blocked Branch', false);
    }

    public function test_user_with_one_branch_auto_selects_branch(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace(2);

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $user = $this->tenantUser('advisor@example.test');
        $branch = $this->branch('Only Branch');
        app(TenantUserProductAccessService::class)->grantAccess($user, 'automotive_service', $owner);
        app(ProductBranchAccessService::class)->enableBranch($branch, 'automotive_service');
        app(ProductBranchAccessService::class)->grantUserBranchAccess($user, $branch, 'automotive_service');
        tenancy()->end();

        $response = $this
            ->actingAs($user, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/dashboard");

        $response->assertOk();
        $response->assertSessionHas('current_branch_id', $branch->id);
    }

    public function test_forbidden_branch_cannot_be_selected(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace(2);

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $user = $this->tenantUser('advisor@example.test');
        $allowed = $this->branch('Allowed Branch');
        $blocked = $this->branch('Blocked Branch');
        $branchAccess = app(ProductBranchAccessService::class);
        app(TenantUserProductAccessService::class)->grantAccess($user, 'automotive_service', $owner);
        $branchAccess->enableBranch($allowed, 'automotive_service');
        $branchAccess->enableBranch($blocked, 'automotive_service');
        $branchAccess->grantUserBranchAccess($user, $allowed, 'automotive_service');
        tenancy()->end();

        $response = $this
            ->actingAs($user, 'automotive_admin')
            ->from("http://{$domain}/workspace/admin/access/branch-context/select")
            ->post("http://{$domain}/workspace/admin/access/branch-context/switch", [
                'product_key' => 'automotive_service',
                'branch_id' => $blocked->id,
            ]);

        $response->assertRedirect("http://{$domain}/workspace/admin/access/branch-context/select");
        $response->assertSessionHasErrors('branch_id');
    }

    public function test_user_without_branch_access_sees_no_branch_access_state(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace(2);

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $user = $this->tenantUser('advisor@example.test');
        app(TenantUserProductAccessService::class)->grantAccess($user, 'automotive_service', $owner);
        tenancy()->end();

        $response = $this
            ->actingAs($user, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/access/branch-context/select");

        $response->assertOk();
        $response->assertSee('No branch access assigned', false);
        $response->assertSee('Contact your administrator', false);
    }

    public function test_access_dashboard_shows_branch_usage_cards(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace(2);

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $branch = $this->branch('Abu Dhabi');
        app(ProductBranchAccessService::class)->enableBranch($branch, 'automotive_service');
        tenancy()->end();

        $response = $this
            ->actingAs($owner, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/access");

        $response->assertOk();
        $response->assertSee('Manage Product Branches', false);
        $response->assertSee('Users without branch access', false);
    }

    protected function prepareTenantWorkspace(int $branchLimit): array
    {
        $tenant = Tenant::query()->create([
            'id' => 'tenant-branch-access-ui-' . Str::uuid(),
            'data' => ['company_name' => 'Branch Access UI Test'],
        ]);

        $this->tenantDatabaseFiles[] = database_path($tenant->database()->getName());

        $domain = $tenant->id . '.example.test';

        Domain::query()->create([
            'domain' => $domain,
            'tenant_id' => $tenant->id,
        ]);

        $this->attachProductSubscription($tenant, 'automotive_service', $branchLimit);

        Artisan::call('tenants:migrate', [
            '--tenants' => [$tenant->id],
            '--force' => true,
        ]);

        return [$tenant, $domain];
    }

    protected function attachProductSubscription(Tenant $tenant, string $productKey, int $branchLimit): void
    {
        $product = Product::query()->firstOrCreate([
            'code' => $productKey,
        ], [
            'name' => 'Automotive Service',
            'slug' => 'automotive-service-' . Str::uuid(),
            'is_active' => true,
        ]);

        $plan = Plan::query()->create([
            'product_id' => $product->id,
            'name' => 'Automotive Branch Access Plan',
            'slug' => 'automotive-branch-access-plan-' . Str::uuid(),
            'price' => 99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'max_users' => 10,
            'max_branches' => $branchLimit,
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'product_key' => $productKey,
            'plan_id' => $plan->id,
            'status' => 'active',
            'included_seats' => 10,
            'extra_seats' => 0,
            'branch_limit' => $branchLimit,
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
            'code' => Str::upper(Str::substr(Str::slug($name, ''), 0, 6)),
            'is_active' => true,
        ]);
    }
}
