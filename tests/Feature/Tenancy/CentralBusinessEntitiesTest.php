<?php

namespace Tests\Feature\Tenancy;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\ProductCustomerProfile;
use App\Models\ProductEmployeeProfile;
use App\Models\ProductSupplierProfile;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Automotive\SupplierCatalogService;
use App\Services\Automotive\WorkshopWorkOrderService;
use App\Services\Tenancy\CentralCustomerService;
use App\Services\Tenancy\CentralEmployeeService;
use App\Services\Tenancy\CentralSupplierService;
use Database\Seeders\TenantBusinessEntityDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;
use Tests\TestCase;

class CentralBusinessEntitiesTest extends TestCase
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

    public function test_central_customer_can_be_created_once_and_reused_by_product_logic(): void
    {
        $tenant = $this->prepareTenant();

        tenancy()->initialize($tenant);

        $service = app(CentralCustomerService::class);
        $customer = $service->findOrCreate([
            'name' => 'Ahmed Customer',
            'phone' => '+971500000001',
            'email' => 'customer@example.test',
        ], 'automotive_service', ['profile_type' => 'workshop']);

        $sameCustomer = $service->findOrCreate([
            'name' => 'Ahmed Customer Updated',
            'phone' => '+971500000001',
            'email' => 'customer@example.test',
        ], 'accounting', ['profile_type' => 'receivable']);

        $this->assertSame($customer->id, $sameCustomer->id);
        $this->assertSame(1, Customer::query()->count());
        $this->assertSame(2, ProductCustomerProfile::query()->where('customer_id', $customer->id)->count());
    }

    public function test_duplicate_customer_is_not_created_when_same_phone_or_email_exists(): void
    {
        $tenant = $this->prepareTenant();

        tenancy()->initialize($tenant);

        $service = app(CentralCustomerService::class);
        $first = $service->findOrCreate([
            'name' => 'Duplicate Customer',
            'phone' => '+971500000002',
            'email' => 'duplicate@example.test',
        ]);
        $second = $service->findOrCreate([
            'name' => 'Duplicate Customer Again',
            'phone' => '+971500000002',
        ]);
        $third = $service->findOrCreate([
            'name' => 'Duplicate Customer By Email',
            'email' => 'duplicate@example.test',
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->id, $third->id);
        $this->assertSame(1, Customer::query()->count());
    }

    public function test_supplier_can_be_central_and_product_scoped_data_can_be_attached(): void
    {
        $tenant = $this->prepareTenant();

        tenancy()->initialize($tenant);

        $supplier = app(CentralSupplierService::class)->findOrCreate([
            'name' => 'Abu Dhabi Parts',
            'phone' => '+971500000003',
            'email' => 'supplier@example.test',
            'tax_number' => 'TRN-123',
        ], 'inventory', [
            'profile_type' => 'parts_supplier',
            'metadata' => ['rating' => 'preferred'],
        ]);

        $this->assertSame(1, Supplier::query()->count());
        $this->assertSame('TRN-123', $supplier->tax_number);
        $this->assertTrue(ProductSupplierProfile::query()
            ->where('supplier_id', $supplier->id)
            ->where('product_key', 'inventory')
            ->where('profile_type', 'parts_supplier')
            ->exists());
    }

    public function test_employee_can_exist_without_login_user(): void
    {
        $tenant = $this->prepareTenant();

        tenancy()->initialize($tenant);

        $employee = app(CentralEmployeeService::class)->findOrCreate([
            'name' => 'Omar Technician',
            'phone' => '+971500000004',
            'employee_type' => Employee::TYPE_TECHNICIAN,
            'job_title' => 'Technician',
        ], 'automotive_service');

        $this->assertNull($employee->user_id);
        $this->assertSame(Employee::TYPE_TECHNICIAN, $employee->employee_type);
        $this->assertSame(1, Employee::query()->count());
    }

    public function test_employee_can_optionally_link_to_user(): void
    {
        $tenant = $this->prepareTenant();

        tenancy()->initialize($tenant);

        $user = User::query()->create([
            'name' => 'Sara Advisor',
            'email' => 'advisor-user@example.test',
            'password' => bcrypt('secret-pass'),
        ]);

        $service = app(CentralEmployeeService::class);
        $employee = $service->findOrCreate([
            'name' => 'Sara Advisor',
            'email' => 'advisor@example.test',
            'employee_type' => Employee::TYPE_SERVICE_ADVISOR,
        ]);

        $linked = $service->linkUser($employee, $user);

        $this->assertSame($user->id, $linked->user_id);
        $this->assertTrue($linked->user()->is($user));
    }

    public function test_technician_and_service_advisor_are_employees_not_required_users(): void
    {
        $tenant = $this->prepareTenant();

        tenancy()->initialize($tenant);

        $service = app(CentralEmployeeService::class);
        $technician = $service->findOrCreate([
            'name' => 'Mina Technician',
            'phone' => '+971500000005',
            'employee_type' => Employee::TYPE_TECHNICIAN,
        ], 'automotive_service', ['profile_type' => 'technician']);
        $advisor = $service->findOrCreate([
            'name' => 'Noura Advisor',
            'phone' => '+971500000006',
            'employee_type' => Employee::TYPE_SERVICE_ADVISOR,
        ], 'automotive_service', ['profile_type' => 'service_advisor']);

        $this->assertNull($technician->user_id);
        $this->assertNull($advisor->user_id);
        $this->assertSame(2, Employee::query()->count());
        $this->assertSame(2, ProductEmployeeProfile::query()->where('product_key', 'automotive_service')->count());
    }

    public function test_legacy_automotive_customer_and_supplier_creation_is_not_broken(): void
    {
        $tenant = $this->prepareTenant();

        tenancy()->initialize($tenant);

        $workshop = app(WorkshopWorkOrderService::class);
        $customer = $workshop->createCustomer([
            'name' => 'Legacy Workshop Customer',
            'phone' => '+971500000007',
            'email' => 'legacy-customer@example.test',
        ]);
        $sameCustomer = $workshop->createCustomer([
            'name' => 'Legacy Workshop Customer Duplicate',
            'phone' => '+971500000007',
        ]);

        $supplierCatalog = app(SupplierCatalogService::class);
        $supplier = $supplierCatalog->createSupplier([
            'name' => 'Legacy Supplier',
            'phone' => '+971500000008',
            'email' => 'legacy-supplier@example.test',
        ]);
        $sameSupplier = $supplierCatalog->createSupplier([
            'name' => 'Legacy Supplier Duplicate',
            'email' => 'legacy-supplier@example.test',
        ]);

        $this->assertSame($customer->id, $sameCustomer->id);
        $this->assertSame($supplier->id, $sameSupplier->id);
        $this->assertSame(1, Customer::query()->count());
        $this->assertSame(1, Supplier::query()->count());
    }

    public function test_demo_business_entity_seeder_is_idempotent(): void
    {
        $tenant = $this->prepareTenant();

        tenancy()->initialize($tenant);

        $this->seed(TenantBusinessEntityDemoSeeder::class);
        $this->seed(TenantBusinessEntityDemoSeeder::class);

        $this->assertSame(2, Customer::query()->count());
        $this->assertSame(2, Supplier::query()->count());
        $this->assertSame(3, Employee::query()->count());
        $this->assertSame(2, ProductCustomerProfile::query()->count());
        $this->assertSame(2, ProductSupplierProfile::query()->count());
        $this->assertSame(3, ProductEmployeeProfile::query()->count());
    }

    protected function prepareTenant(): Tenant
    {
        $tenant = Tenant::query()->create([
            'id' => 'tenant-business-' . Str::uuid(),
            'data' => ['company_name' => 'Tenant Business Entity Test'],
        ]);

        $this->tenantDatabaseFiles[] = database_path($tenant->database()->getName());

        Domain::query()->create([
            'domain' => $tenant->id . '.example.test',
            'tenant_id' => $tenant->id,
        ]);

        Artisan::call('tenants:migrate', [
            '--tenants' => [$tenant->id],
            '--force' => true,
        ]);

        return $tenant;
    }
}
