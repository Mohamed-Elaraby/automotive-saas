<?php

namespace Tests\Feature\Automotive\Admin;

use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;
use Tests\TestCase;

class AccessControlDashboardTest extends TestCase
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

    public function test_access_dashboard_route_exists_under_automotive_admin_access_names(): void
    {
        $routeNames = $this->registeredRouteNames();

        $this->assertContains('automotive.admin.access.index', $routeNames);
        $this->assertContains('automotive.admin.access.users.index', $routeNames);
        $this->assertContains('automotive.admin.access.roles.index', $routeNames);
        $this->assertContains('automotive.admin.access.branches.index', $routeNames);
        $this->assertContains('automotive.admin.access.products.index', $routeNames);
        $this->assertContains('automotive.admin.access.diagnostics.index', $routeNames);
    }

    public function test_owner_can_view_access_dashboard(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $this->tenantUser('advisor@example.test');
        tenancy()->end();

        $response = $this
            ->actingAs($owner, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/access");

        $response->assertOk();
        $response->assertSee('Access Control', false);
        $response->assertSee('Seat Usage', false);
        $response->assertSee('Branch Usage', false);
        $response->assertSee('automotive_service', false);
    }

    public function test_unauthorized_user_cannot_view_access_dashboard(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $this->tenantUser('owner@example.test');
        $regularUser = $this->tenantUser('regular@example.test');
        tenancy()->end();

        $response = $this
            ->actingAs($regularUser, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/access");

        $response->assertForbidden();
    }

    public function test_automotive_admin_routes_still_list_after_access_routes_are_registered(): void
    {
        $routeNames = $this->registeredRouteNames();

        $this->assertContains('automotive.admin.access.index', $routeNames);
        $this->assertContains('automotive.admin.users.index', $routeNames);
        $this->assertContains('automotive.admin.dashboard', $routeNames);
    }

    protected function prepareTenantWorkspace(): array
    {
        $tenant = Tenant::query()->create([
            'id' => 'tenant-access-ui-' . Str::uuid(),
            'data' => ['company_name' => 'Access UI Test'],
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
            'name' => 'Automotive Service',
            'slug' => 'automotive-service-' . Str::uuid(),
            'is_active' => true,
        ]);

        $plan = Plan::query()->create([
            'product_id' => $product->id,
            'name' => 'Automotive Access Plan',
            'slug' => 'automotive-access-plan-' . Str::uuid(),
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

    protected function registeredRouteNames(): array
    {
        return collect(app('router')->getRoutes()->getRoutes())
            ->map(fn ($route): ?string => $route->getName())
            ->filter()
            ->values()
            ->all();
    }
}
