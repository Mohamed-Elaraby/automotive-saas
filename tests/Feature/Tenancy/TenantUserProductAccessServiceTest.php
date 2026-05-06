<?php

namespace Tests\Feature\Tenancy;

use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\TenantUserProductAccess;
use App\Models\User;
use App\Services\Tenancy\ProductEntitlementService;
use App\Services\Tenancy\TenantUserProductAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Stancl\Tenancy\Database\Models\Domain;
use Tests\TestCase;

class TenantUserProductAccessServiceTest extends TestCase
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

    public function test_user_can_be_granted_product_access_when_seats_are_available(): void
    {
        $tenant = $this->prepareTenantWithSubscription('automotive_service', 'active', 2);

        tenancy()->initialize($tenant);

        $user = $this->tenantUser('advisor@example.test');
        $access = app(TenantUserProductAccessService::class)->grantAccess($user, 'automotive_service');

        $this->assertSame('active', $access->status);
        $this->assertTrue(app(TenantUserProductAccessService::class)->hasAccess($user, 'automotive_service'));
        $this->assertSame(1, app(TenantUserProductAccessService::class)->countUsedSeats('automotive_service'));
        $this->assertSame(1, app(TenantUserProductAccessService::class)->availableSeats('automotive_service'));
    }

    public function test_grant_access_is_blocked_when_product_seat_limit_is_exceeded(): void
    {
        $tenant = $this->prepareTenantWithSubscription('automotive_service', 'active', 1);

        tenancy()->initialize($tenant);

        $service = app(TenantUserProductAccessService::class);
        $service->grantAccess($this->tenantUser('first@example.test'), 'automotive_service');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No available seats');

        $service->grantAccess($this->tenantUser('second@example.test'), 'automotive_service');
    }

    public function test_revoked_product_access_does_not_count_as_used_seat(): void
    {
        $tenant = $this->prepareTenantWithSubscription('automotive_service', 'active', 1);

        tenancy()->initialize($tenant);

        $service = app(TenantUserProductAccessService::class);
        $firstUser = $this->tenantUser('revoked@example.test');
        $service->grantAccess($firstUser, 'automotive_service');
        $service->revokeAccess($firstUser, 'automotive_service');

        $this->assertSame(0, $service->countUsedSeats('automotive_service'));
        $this->assertSame(1, $service->availableSeats('automotive_service'));

        $secondAccess = $service->grantAccess($this->tenantUser('replacement@example.test'), 'automotive_service');

        $this->assertSame('active', $secondAccess->status);
        $this->assertSame(1, $service->countUsedSeats('automotive_service'));
    }

    public function test_extra_user_seat_addon_increases_available_product_seats(): void
    {
        $tenant = $this->prepareTenantWithSubscription('automotive_service', 'active', 1);

        DB::table('subscription_addons')->insert([
            'tenant_id' => $tenant->id,
            'product_key' => 'automotive_service',
            'addon_key' => 'extra_user_seat',
            'quantity' => 2,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        tenancy()->initialize($tenant);

        $service = app(TenantUserProductAccessService::class);
        $service->grantAccess($this->tenantUser('one@example.test'), 'automotive_service');
        $service->grantAccess($this->tenantUser('two@example.test'), 'automotive_service');
        $service->grantAccess($this->tenantUser('three@example.test'), 'automotive_service');

        $this->assertSame(3, $service->countUsedSeats('automotive_service'));
        $this->assertSame(0, $service->availableSeats('automotive_service'));
    }

    public function test_same_user_consumes_one_seat_per_product(): void
    {
        $tenant = $this->prepareTenantWithSubscription('automotive_service', 'active', 1);
        $this->attachProductSubscription($tenant, 'accounting', 'active', 1);

        tenancy()->initialize($tenant);

        $service = app(TenantUserProductAccessService::class);
        $user = $this->tenantUser('multi-product@example.test');

        $service->grantAccess($user, 'automotive_service');
        $service->grantAccess($user, 'accounting');

        $this->assertSame(1, $service->countUsedSeats('automotive_service'));
        $this->assertSame(1, $service->countUsedSeats('accounting'));
        $this->assertTrue($service->hasAccess($user, 'automotive_service'));
        $this->assertTrue($service->hasAccess($user, 'accounting'));
    }

    public function test_inactive_subscription_blocks_product_access_grant(): void
    {
        $tenant = $this->prepareTenantWithSubscription('automotive_service', 'cancelled', 2);

        tenancy()->initialize($tenant);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not actively subscribed');

        app(TenantUserProductAccessService::class)->grantAccess($this->tenantUser('blocked@example.test'), 'automotive_service');
    }

    public function test_product_entitlement_service_integrates_with_user_access(): void
    {
        $tenant = $this->prepareTenantWithSubscription('automotive_service', 'trialing', 2, 1);

        tenancy()->initialize($tenant);

        $user = $this->tenantUser('entitled@example.test');
        app(TenantUserProductAccessService::class)->grantAccess($user, 'automotive_service');

        $this->assertTrue(app(ProductEntitlementService::class)->isSubscribed((string) $tenant->id, 'automotive_service'));
        $this->assertSame(3, app(ProductEntitlementService::class)->seatLimit((string) $tenant->id, 'automotive_service'));
        $this->assertTrue(app(TenantUserProductAccessService::class)->hasAccess($user, 'automotive_service'));
    }

    protected function prepareTenantWithSubscription(string $productKey, string $status, int $includedSeats, int $extraSeats = 0): Tenant
    {
        $tenant = Tenant::query()->create([
            'id' => 'tenant-access-' . Str::uuid(),
            'data' => ['company_name' => 'Tenant Access Test'],
        ]);

        $this->tenantDatabaseFiles[] = database_path($tenant->database()->getName());

        Domain::query()->create([
            'domain' => $tenant->id . '.example.test',
            'tenant_id' => $tenant->id,
        ]);

        $this->attachProductSubscription($tenant, $productKey, $status, $includedSeats, $extraSeats);

        Artisan::call('tenants:migrate', [
            '--tenants' => [$tenant->id],
            '--force' => true,
        ]);

        return $tenant;
    }

    protected function attachProductSubscription(Tenant $tenant, string $productKey, string $status, int $includedSeats, int $extraSeats = 0): void
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
            'name' => Str::headline($productKey) . ' Plan',
            'slug' => Str::slug($productKey) . '-plan-' . uniqid(),
            'price' => 99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'max_users' => $includedSeats,
            'max_branches' => 1,
        ]);

        DB::table('plan_limits')->insert([
            'product_key' => $productKey,
            'plan_id' => $plan->id,
            'limit_key' => 'included_seats',
            'limit_value' => (string) $includedSeats,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'product_key' => $productKey,
            'plan_id' => $plan->id,
            'status' => $status,
            'included_seats' => $includedSeats,
            'extra_seats' => $extraSeats,
            'branch_limit' => 1,
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
}
