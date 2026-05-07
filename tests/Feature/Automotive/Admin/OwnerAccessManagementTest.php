<?php

namespace Tests\Feature\Automotive\Admin;

use App\Models\Branch;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\TenantUserProductAccess;
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

class OwnerAccessManagementTest extends TestCase
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

    public function test_owner_user_shows_owner_access_badges_in_users_ui(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->owner();
        tenancy()->end();

        $response = $this
            ->actingAs($owner, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/access/users");

        $response->assertOk();
        $response->assertSee('Owner Access', false);
        $response->assertSee('Does not consume product seat', false);
        $response->assertDontSee('No product access', false);
    }

    public function test_owner_does_not_consume_product_seat_by_default(): void
    {
        [$tenant] = $this->prepareTenantWorkspace(1);

        tenancy()->initialize($tenant);
        $owner = $this->owner();
        app(TenantUserProductAccessService::class)->grantAccess($owner, 'automotive_service', $owner);

        $this->assertSame(0, app(TenantUserProductAccessService::class)->countUsedSeats('automotive_service'));
    }

    public function test_sync_owner_access_is_idempotent_and_creates_product_and_branch_records(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->owner();
        $branch = Branch::query()->create(['name' => 'Abu Dhabi', 'code' => 'AUH', 'is_active' => true]);
        app(ProductBranchAccessService::class)->enableBranch($branch, 'automotive_service');
        tenancy()->end();

        $this
            ->actingAs($owner, 'automotive_admin')
            ->post("http://{$domain}/workspace/admin/access/users/{$owner->id}/owner-sync")
            ->assertRedirect();

        $this
            ->actingAs($owner, 'automotive_admin')
            ->post("http://{$domain}/workspace/admin/access/users/{$owner->id}/owner-sync")
            ->assertRedirect();

        tenancy()->initialize($tenant);
        $this->assertSame(1, TenantUserProductAccess::query()->where('user_id', $owner->id)->active()->count());
        $this->assertSame(1, TenantUserProductBranch::query()->where('user_id', $owner->id)->enabled()->count());
    }

    public function test_sync_owner_access_does_not_enable_new_product_branches(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->owner();
        Branch::query()->create(['name' => 'Not Enabled', 'code' => 'NE', 'is_active' => true]);
        tenancy()->end();

        $this
            ->actingAs($owner, 'automotive_admin')
            ->post("http://{$domain}/workspace/admin/access/users/{$owner->id}/owner-sync")
            ->assertRedirect();

        tenancy()->initialize($tenant);
        $this->assertSame(0, TenantUserProductBranch::query()->enabled()->count());
    }

    public function test_non_owner_cannot_run_sync_owner_access(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->owner();
        $user = User::query()->create([
            'name' => 'Regular User',
            'email' => 'regular@example.test',
            'password' => Hash::make('password'),
        ]);
        tenancy()->end();

        $this
            ->actingAs($user, 'automotive_admin')
            ->post("http://{$domain}/workspace/admin/access/users/{$owner->id}/owner-sync")
            ->assertForbidden();
    }

    protected function prepareTenantWorkspace(int $seats = 5): array
    {
        $tenant = Tenant::query()->create([
            'id' => 'tenant-owner-access-' . Str::uuid(),
            'data' => ['company_name' => 'Owner Access'],
        ]);

        $this->tenantDatabaseFiles[] = database_path($tenant->database()->getName());
        $domain = $tenant->id . '.example.test';
        Domain::query()->create(['domain' => $domain, 'tenant_id' => $tenant->id]);

        $product = Product::query()->firstOrCreate(['code' => 'automotive_service'], [
            'name' => 'Automotive Service',
            'slug' => 'automotive-service-' . Str::uuid(),
            'is_active' => true,
        ]);

        $plan = Plan::query()->create([
            'product_id' => $product->id,
            'name' => 'Owner Plan',
            'slug' => 'owner-plan-' . Str::uuid(),
            'price' => 99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'max_users' => $seats,
            'max_branches' => 3,
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'product_key' => 'automotive_service',
            'plan_id' => $plan->id,
            'status' => 'active',
            'included_seats' => $seats,
            'branch_limit' => 3,
        ]);

        Artisan::call('tenants:migrate', ['--tenants' => [$tenant->id], '--force' => true]);

        return [$tenant, $domain];
    }

    protected function owner(): User
    {
        return User::query()->create([
            'id' => 1,
            'name' => 'Workspace Owner',
            'email' => 'owner@example.test',
            'password' => Hash::make('password'),
        ]);
    }
}
