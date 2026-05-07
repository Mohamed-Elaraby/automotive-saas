<?php

namespace Tests\Feature\Auth;

use App\Models\Admin;
use App\Models\Branch;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\User;
use App\Services\Tenancy\ProductBranchAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;
use Tests\TestCase;

class AdminSessionIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected array $tenantDatabaseFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('tenancy.database.template_tenant_connection', 'sqlite');
        URL::forceRootUrl('https://seven-scapital.com');
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

    public function test_central_admin_session_survives_tenant_admin_login(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();
        $this->centralAdmin();

        tenancy()->initialize($tenant);
        $this->tenantOwner();
        $branch = Branch::query()->create(['name' => 'Abu Dhabi', 'code' => 'AUH', 'is_active' => true]);
        app(ProductBranchAccessService::class)->enableBranch($branch, 'automotive_service');
        tenancy()->end();

        $this->post('https://seven-scapital.com/admin/login', [
            'email' => 'central@example.test',
            'password' => 'password',
        ])->assertRedirect('https://seven-scapital.com/admin/dashboard');

        $this->post("http://{$domain}/workspace/admin/login", [
            'email' => 'owner@example.test',
            'password' => 'password',
        ])->assertRedirectContains('/workspace/admin/dashboard');

        $this->get('https://seven-scapital.com/admin/dashboard')->assertOk();
        $this->get("http://{$domain}/workspace/admin/dashboard")->assertOk();
    }

    public function test_tenant_admin_session_survives_central_admin_login(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();
        $this->centralAdmin();

        tenancy()->initialize($tenant);
        $this->tenantOwner();
        $branch = Branch::query()->create(['name' => 'Dubai', 'code' => 'DXB', 'is_active' => true]);
        app(ProductBranchAccessService::class)->enableBranch($branch, 'automotive_service');
        tenancy()->end();

        $this->post("http://{$domain}/workspace/admin/login", [
            'email' => 'owner@example.test',
            'password' => 'password',
        ])->assertRedirectContains('/workspace/admin/dashboard');

        $this->post('https://seven-scapital.com/admin/login', [
            'email' => 'central@example.test',
            'password' => 'password',
        ])->assertRedirect('https://seven-scapital.com/admin/dashboard');

        $this->get("http://{$domain}/workspace/admin/dashboard")->assertOk();
        $this->get('https://seven-scapital.com/admin/dashboard')->assertOk();
    }

    public function test_logout_is_scoped_to_the_selected_admin_guard(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();
        $this->centralAdmin();

        tenancy()->initialize($tenant);
        $this->tenantOwner();
        $branch = Branch::query()->create(['name' => 'Sharjah', 'code' => 'SHJ', 'is_active' => true]);
        app(ProductBranchAccessService::class)->enableBranch($branch, 'automotive_service');
        tenancy()->end();

        $this->post('https://seven-scapital.com/admin/login', ['email' => 'central@example.test', 'password' => 'password']);
        $this->post("http://{$domain}/workspace/admin/login", ['email' => 'owner@example.test', 'password' => 'password']);

        $this->post("http://{$domain}/workspace/admin/logout")->assertRedirectContains('/workspace');
        $this->get('https://seven-scapital.com/admin/dashboard')->assertOk();

        $this->post("http://{$domain}/workspace/admin/login", ['email' => 'owner@example.test', 'password' => 'password']);
        $this->post('https://seven-scapital.com/admin/logout')->assertRedirect('https://seven-scapital.com/admin/login');
        $this->get("http://{$domain}/workspace/admin/dashboard")->assertOk();
    }

    protected function prepareTenantWorkspace(): array
    {
        $tenant = Tenant::query()->create([
            'id' => 'tenant-session-isolation-' . Str::uuid(),
            'data' => ['company_name' => 'Session Isolation'],
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
            'name' => 'Session Plan',
            'slug' => 'session-plan-' . Str::uuid(),
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
            'product_key' => 'automotive_service',
            'plan_id' => $plan->id,
            'status' => 'active',
            'included_seats' => 5,
            'branch_limit' => 3,
        ]);

        Artisan::call('tenants:migrate', ['--tenants' => [$tenant->id], '--force' => true]);

        return [$tenant, $domain];
    }

    protected function centralAdmin(): Admin
    {
        return Admin::query()->create([
            'name' => 'Central Admin',
            'email' => 'central@example.test',
            'password' => Hash::make('password'),
        ]);
    }

    protected function tenantOwner(): User
    {
        return User::query()->create([
            'id' => 1,
            'name' => 'Workspace Owner',
            'email' => 'owner@example.test',
            'password' => Hash::make('password'),
        ]);
    }
}
