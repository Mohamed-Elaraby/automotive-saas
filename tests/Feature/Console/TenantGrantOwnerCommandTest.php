<?php

namespace Tests\Feature\Console;

use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantGrantOwnerCommandTest extends TestCase
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

    public function test_command_grants_owner_access_idempotently(): void
    {
        $tenant = $this->prepareTenantWorkspace();

        Artisan::call('tenant:grant-owner', [
            'tenant' => $tenant->id,
            'email' => 'owner@example.test',
            '--sync-access' => true,
        ]);

        Artisan::call('tenant:grant-owner', [
            'tenant' => $tenant->id,
            'email' => 'owner@example.test',
            '--sync-access' => true,
        ]);

        tenancy()->initialize($tenant);

        $this->assertSame(1, User::query()->where('email', 'owner@example.test')->count());
        $this->assertSame(1, User::query()->whereKey(1)->count());
    }

    protected function prepareTenantWorkspace(): Tenant
    {
        $tenant = Tenant::query()->create([
            'id' => 'tenant-grant-owner-' . Str::uuid(),
            'data' => ['company_name' => 'Grant Owner'],
        ]);

        $this->tenantDatabaseFiles[] = database_path($tenant->database()->getName());

        $product = Product::query()->firstOrCreate(['code' => 'automotive_service'], [
            'name' => 'Automotive Service',
            'slug' => 'automotive-service-' . Str::uuid(),
            'is_active' => true,
        ]);

        $plan = Plan::query()->create([
            'product_id' => $product->id,
            'name' => 'Owner Recovery Plan',
            'slug' => 'owner-recovery-plan-' . Str::uuid(),
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

        return $tenant;
    }
}
