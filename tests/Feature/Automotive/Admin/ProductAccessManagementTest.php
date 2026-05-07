<?php

namespace Tests\Feature\Automotive\Admin;

use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\TenantUserProductAccess;
use App\Models\User;
use App\Services\Tenancy\TenantUserProductAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;
use Tests\TestCase;

class ProductAccessManagementTest extends TestCase
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

    public function test_owner_can_view_product_access_management_page(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace(3);

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $user = $this->tenantUser('advisor@example.test');
        tenancy()->end();

        $response = $this
            ->actingAs($owner, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/access/users/{$user->id}/products");

        $response->assertOk();
        $response->assertSee('Manage Product Access', false);
        $response->assertSee('Automotive Service', false);
        $response->assertSee('Seats', false);
    }

    public function test_owner_can_grant_product_access_to_user(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace(3);

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $user = $this->tenantUser('advisor@example.test');
        tenancy()->end();

        $response = $this
            ->actingAs($owner, 'automotive_admin')
            ->put("http://{$domain}/workspace/admin/access/users/{$user->id}/products", [
                'products' => ['automotive_service'],
            ]);

        $response->assertRedirect("http://{$domain}/workspace/admin/access/users/{$user->id}/products");

        tenancy()->initialize($tenant);
        $this->assertDatabaseHas('tenant_user_product_access', [
            'user_id' => $user->id,
            'product_key' => 'automotive_service',
            'status' => 'active',
        ]);
    }

    public function test_owner_can_revoke_product_access_from_user(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace(3);

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $user = $this->tenantUser('advisor@example.test');
        app(TenantUserProductAccessService::class)->grantAccess($user, 'automotive_service', $owner);
        tenancy()->end();

        $response = $this
            ->actingAs($owner, 'automotive_admin')
            ->put("http://{$domain}/workspace/admin/access/users/{$user->id}/products", [
                'products' => [],
            ]);

        $response->assertRedirect("http://{$domain}/workspace/admin/access/users/{$user->id}/products");

        tenancy()->initialize($tenant);
        $this->assertDatabaseHas('tenant_user_product_access', [
            'user_id' => $user->id,
            'product_key' => 'automotive_service',
            'status' => 'revoked',
        ]);
    }

    public function test_cannot_grant_access_when_seat_limit_is_exceeded(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace(1);

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $firstUser = $this->tenantUser('first@example.test');
        $blockedUser = $this->tenantUser('blocked@example.test');
        app(TenantUserProductAccessService::class)->grantAccess($firstUser, 'automotive_service', $owner);
        tenancy()->end();

        $response = $this
            ->actingAs($owner, 'automotive_admin')
            ->from("http://{$domain}/workspace/admin/access/users/{$blockedUser->id}/products")
            ->put("http://{$domain}/workspace/admin/access/users/{$blockedUser->id}/products", [
                'products' => ['automotive_service'],
            ]);

        $response->assertRedirect("http://{$domain}/workspace/admin/access/users/{$blockedUser->id}/products");
        $response->assertSessionHasErrors('products');

        tenancy()->initialize($tenant);
        $this->assertFalse(TenantUserProductAccess::query()
            ->where('user_id', $blockedUser->id)
            ->where('product_key', 'automotive_service')
            ->active()
            ->exists());
    }

    public function test_revoked_access_frees_a_seat(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace(1);

        tenancy()->initialize($tenant);
        $owner = $this->tenantUser('owner@example.test');
        $firstUser = $this->tenantUser('first@example.test');
        $secondUser = $this->tenantUser('second@example.test');
        $access = app(TenantUserProductAccessService::class);
        $access->grantAccess($firstUser, 'automotive_service', $owner);
        $access->revokeAccess($firstUser, 'automotive_service');
        tenancy()->end();

        $response = $this
            ->actingAs($owner, 'automotive_admin')
            ->put("http://{$domain}/workspace/admin/access/users/{$secondUser->id}/products", [
                'products' => ['automotive_service'],
            ]);

        $response->assertRedirect("http://{$domain}/workspace/admin/access/users/{$secondUser->id}/products");

        tenancy()->initialize($tenant);
        $this->assertTrue(TenantUserProductAccess::query()
            ->where('user_id', $secondUser->id)
            ->where('product_key', 'automotive_service')
            ->active()
            ->exists());
    }

    public function test_user_without_access_management_permission_cannot_access_page(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace(3);

        tenancy()->initialize($tenant);
        $this->tenantUser('owner@example.test');
        $regularUser = $this->tenantUser('regular@example.test');
        $targetUser = $this->tenantUser('target@example.test');
        tenancy()->end();

        $response = $this
            ->actingAs($regularUser, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/access/users/{$targetUser->id}/products");

        $response->assertForbidden();
    }

    protected function prepareTenantWorkspace(int $includedSeats): array
    {
        $tenant = Tenant::query()->create([
            'id' => 'tenant-product-access-ui-' . Str::uuid(),
            'data' => ['company_name' => 'Product Access UI Test'],
        ]);

        $this->tenantDatabaseFiles[] = database_path($tenant->database()->getName());

        $domain = $tenant->id . '.example.test';

        Domain::query()->create([
            'domain' => $domain,
            'tenant_id' => $tenant->id,
        ]);

        $this->attachProductSubscription($tenant, 'automotive_service', $includedSeats);

        Artisan::call('tenants:migrate', [
            '--tenants' => [$tenant->id],
            '--force' => true,
        ]);

        return [$tenant, $domain];
    }

    protected function attachProductSubscription(Tenant $tenant, string $productKey, int $includedSeats): void
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
            'name' => 'Automotive Product Access Plan',
            'slug' => 'automotive-product-access-plan-' . Str::uuid(),
            'price' => 99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'max_users' => $includedSeats,
            'max_branches' => 3,
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'product_key' => $productKey,
            'plan_id' => $plan->id,
            'status' => 'active',
            'included_seats' => $includedSeats,
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
}
