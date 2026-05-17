<?php

namespace Tests\Feature\Automotive\Admin\Maintenance;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Maintenance\VehicleCheckIn;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Services\Tenancy\ProductBranchAccessService;
use App\Services\Tenancy\TenantUserProductAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;
use Tests\TestCase;

class VinTenantWideLookupTest extends TestCase
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

    public function test_confirmed_vin_lookup_searches_tenant_wide_and_returns_existing_vehicle(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->ownerUser();
        $dubai = $this->enableBranch('Dubai Branch');
        $ajman = $this->enableBranch('Ajman Branch');
        $vehicle = $this->vehicleWithHistory('JTDKB20U777777777', $ajman);
        tenancy()->end();

        $this->actingAs($owner, 'automotive_admin')
            ->getJson("http://{$domain}/workspace/admin/maintenance/vehicles/search-vin?vin=jtdkb20u777777777")
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('normalized_vin', 'JTDKB20U777777777')
            ->assertJsonPath('vehicle.id', $vehicle->id)
            ->assertJsonPath('branch.name', 'Ajman Branch')
            ->assertJsonPath('history.total_visits', 1)
            ->assertJsonPath('actions.use_for_check_in', true);

        $this->assertNotNull($dubai);
    }

    public function test_vehicle_from_another_tenant_is_never_returned(): void
    {
        [$tenantOne, $domainOne] = $this->prepareTenantWorkspace('tenant-vin-one-');
        [$tenantTwo] = $this->prepareTenantWorkspace('tenant-vin-two-');

        tenancy()->initialize($tenantTwo);
        $this->ownerUser();
        $branch = $this->enableBranch('Other Tenant Branch');
        $this->vehicleWithHistory('1HGCM82633A004352', $branch);
        tenancy()->end();

        tenancy()->initialize($tenantOne);
        $owner = $this->ownerUser();
        $this->enableBranch('Tenant One Branch');
        tenancy()->end();

        $this->actingAs($owner, 'automotive_admin')
            ->getJson("http://{$domainOne}/workspace/admin/maintenance/vehicles/search-vin?vin=1HGCM82633A004352")
            ->assertOk()
            ->assertJsonPath('found', false)
            ->assertJsonPath('normalized_vin', '1HGCM82633A004352');
    }

    public function test_not_found_response_returns_normalized_vin(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->ownerUser();
        $this->enableBranch('Dubai Branch');
        tenancy()->end();

        $this->actingAs($owner, 'automotive_admin')
            ->getJson("http://{$domain}/workspace/admin/maintenance/vehicles/search-vin?vin= missing vin ")
            ->assertOk()
            ->assertJsonPath('found', false)
            ->assertJsonPath('normalized_vin', 'MISSINGVIN');
    }

    public function test_branch_restricted_user_does_not_receive_unauthorized_branch_history_details(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->ownerUser();
        $advisor = $this->advisorUser();
        $dubai = $this->enableBranch('Dubai Branch');
        $ajman = $this->enableBranch('Ajman Branch');
        app(TenantUserProductAccessService::class)->grantAccess($advisor, 'automotive_service', $owner);
        app(ProductBranchAccessService::class)->grantUserBranchAccess($advisor, $dubai, 'automotive_service');
        $this->vehicleWithHistory('WDBUF56X78B123456', $ajman);
        tenancy()->end();

        $this->actingAs($advisor, 'automotive_admin')
            ->getJson("http://{$domain}/workspace/admin/maintenance/vehicles/search-vin?vin=WDBUF56X78B123456")
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('restricted_history', true)
            ->assertJsonPath('branch', null)
            ->assertJsonPath('history.last_check_in_at', null)
            ->assertJsonPath('customer.phone', null);
    }

    protected function prepareTenantWorkspace(string $prefix = 'tenant-vin-lookup-'): array
    {
        $tenant = Tenant::query()->create([
            'id' => $prefix . Str::uuid(),
            'data' => ['company_name' => 'VIN Lookup Test'],
        ]);

        $this->tenantDatabaseFiles[] = database_path($tenant->database()->getName());

        $domain = $tenant->id . '.example.test';
        Domain::query()->create(['domain' => $domain, 'tenant_id' => $tenant->id]);
        $this->attachProductSubscription($tenant);

        Artisan::call('tenants:migrate', [
            '--tenants' => [$tenant->id],
            '--force' => true,
        ]);

        return [$tenant, $domain];
    }

    protected function attachProductSubscription(Tenant $tenant): void
    {
        $product = Product::query()->firstOrCreate(['code' => 'automotive_service'], [
            'name' => 'Automotive Service',
            'slug' => 'automotive-service-' . Str::uuid(),
            'is_active' => true,
        ]);

        $plan = Plan::query()->create([
            'product_id' => $product->id,
            'name' => 'Automotive Service Plan',
            'slug' => 'automotive-service-plan-' . Str::uuid(),
            'price' => 99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'max_users' => 10,
            'max_branches' => 3,
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'product_key' => 'automotive_service',
            'plan_id' => $plan->id,
            'status' => 'active',
            'included_seats' => 10,
            'extra_seats' => 0,
            'branch_limit' => 3,
        ]);
    }

    protected function ownerUser(): User
    {
        return User::query()->firstOrCreate(['email' => 'owner@example.test'], [
            'name' => 'Owner',
            'password' => Hash::make('password'),
        ]);
    }

    protected function advisorUser(): User
    {
        return User::query()->firstOrCreate(['email' => 'advisor@example.test'], [
            'name' => 'Advisor',
            'password' => Hash::make('password'),
        ]);
    }

    protected function enableBranch(string $name): Branch
    {
        $branch = Branch::query()->create([
            'name' => $name,
            'code' => Str::slug($name) . '-' . Str::random(5),
            'is_active' => true,
        ]);

        app(ProductBranchAccessService::class)->enableBranch($branch, 'automotive_service');

        return $branch;
    }

    protected function vehicleWithHistory(string $vin, Branch $branch): Vehicle
    {
        $customer = Customer::query()->create([
            'customer_number' => 'CUS-' . Str::random(6),
            'name' => 'VIN Customer',
            'phone' => '0501234567',
            'email' => 'customer@example.test',
            'customer_type' => 'individual',
        ]);

        $vehicle = Vehicle::query()->create([
            'vehicle_number' => 'VEH-' . Str::random(6),
            'customer_id' => $customer->id,
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2020,
            'color' => 'White',
            'plate_number' => 'D12345',
            'vin' => $vin,
        ]);

        $workOrder = WorkOrder::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'work_order_number' => 'WO-' . Str::random(6),
            'title' => 'Previous service',
            'status' => 'open',
            'priority' => 'normal',
            'vehicle_status' => 'in_workshop',
            'payment_status' => 'unpaid',
            'opened_at' => now()->subDay(),
        ]);

        VehicleCheckIn::query()->create([
            'check_in_number' => 'CHK-' . Str::random(6),
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'work_order_id' => $workOrder->id,
            'status' => 'checked_in',
            'vin_number' => $vin,
            'checked_in_at' => now()->subDay(),
        ]);

        return $vehicle;
    }
}
