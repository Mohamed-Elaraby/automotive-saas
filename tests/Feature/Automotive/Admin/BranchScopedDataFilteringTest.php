<?php

namespace Tests\Feature\Automotive\Admin;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Maintenance\VehicleCheckIn;
use App\Models\Plan;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\TenantAttachment;
use App\Models\TenantNotification;
use App\Models\TenantProductSubscription;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Services\Tenancy\BranchContextService;
use App\Services\Tenancy\BranchScopeService;
use App\Services\Tenancy\ProductBranchAccessService;
use App\Services\Tenancy\TenantUserProductAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;
use Tests\TestCase;

class BranchScopedDataFilteringTest extends TestCase
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

    public function test_user_sees_records_from_allowed_branch_only(): void
    {
        [$tenant] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        [$owner, $user, $dubai, $ajman] = $this->prepareBranchUsers();
        $dubaiOrder = $this->workOrder($dubai, 'WO-DUBAI');
        $this->workOrder($ajman, 'WO-AJMAN');

        $orders = app(BranchScopeService::class)
            ->applyAllowedBranches(WorkOrder::query(), $user, 'automotive_service')
            ->pluck('work_order_number')
            ->all();

        $this->assertSame(['WO-DUBAI'], $orders);
        $this->assertNotNull($owner);
        $this->assertTrue(app(BranchScopeService::class)->canAccessBranch($user, 'automotive_service', $dubaiOrder->branch_id));
    }

    public function test_user_cannot_open_detail_page_for_forbidden_branch_record(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        [, $user, , $ajman] = $this->prepareBranchUsers();
        $forbiddenCheckIn = $this->checkIn($ajman, 'CHK-AJMAN');
        tenancy()->end();

        $this->actingAs($user, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/maintenance/check-ins/{$forbiddenCheckIn->id}")
            ->assertForbidden();
    }

    public function test_user_with_multiple_branches_sees_records_from_both_allowed_branches(): void
    {
        [$tenant] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        [$owner, $user, $dubai, $ajman] = $this->prepareBranchUsers(grantAjman: true);
        $this->workOrder($dubai, 'WO-DUBAI');
        $this->workOrder($ajman, 'WO-AJMAN');

        $orders = app(BranchScopeService::class)
            ->applyAllowedBranches(WorkOrder::query()->orderBy('work_order_number'), $user, 'automotive_service')
            ->pluck('work_order_number')
            ->all();

        $this->assertSame(['WO-AJMAN', 'WO-DUBAI'], $orders);
        $this->assertNotNull($owner);
    }

    public function test_owner_sees_records_from_all_enabled_product_branches(): void
    {
        [$tenant] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        [$owner, , $dubai, $ajman] = $this->prepareBranchUsers();
        $this->workOrder($dubai, 'WO-DUBAI');
        $this->workOrder($ajman, 'WO-AJMAN');

        $orders = app(BranchScopeService::class)
            ->applyAllowedBranches(WorkOrder::query()->orderBy('work_order_number'), $owner, 'automotive_service')
            ->pluck('work_order_number')
            ->all();

        $this->assertSame(['WO-AJMAN', 'WO-DUBAI'], $orders);
    }

    public function test_current_branch_context_filters_dashboard_or_list_when_required(): void
    {
        [$tenant] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        [, $user, $dubai, $ajman] = $this->prepareBranchUsers(grantAjman: true);
        $this->workOrder($dubai, 'WO-DUBAI');
        $this->workOrder($ajman, 'WO-AJMAN');

        app(BranchContextService::class)->setCurrentBranch($user, 'automotive_service', $ajman);

        $orders = app(BranchScopeService::class)
            ->applyCurrentBranch(WorkOrder::query(), $user, 'automotive_service')
            ->pluck('work_order_number')
            ->all();

        $this->assertSame(['WO-AJMAN'], $orders);
    }

    public function test_reports_counts_exclude_forbidden_branches(): void
    {
        [$tenant] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        [, $user, $dubai, $ajman] = $this->prepareBranchUsers();
        $this->workOrder($dubai, 'WO-DUBAI', ['status' => 'open']);
        $this->workOrder($ajman, 'WO-AJMAN', ['status' => 'open']);

        $dashboard = app(\App\Services\Automotive\Maintenance\MaintenanceReportingService::class)->dashboard($user);

        $this->assertSame(1, $dashboard['open_work_orders']);
    }

    public function test_branch_scoped_attachments_are_hidden_outside_allowed_branches(): void
    {
        [$tenant] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        [, $user, $dubai, $ajman] = $this->prepareBranchUsers();
        $customer = Customer::query()->create(['name' => 'Attachment Customer']);
        $visible = TenantAttachment::query()->create($this->attachmentPayload($customer, $dubai, 'visible.pdf'));
        TenantAttachment::query()->create($this->attachmentPayload($customer, $ajman, 'hidden.pdf'));

        $attachments = TenantAttachment::query()
            ->forProduct('automotive_service')
            ->visibleToUserOrGlobal($user, 'automotive_service')
            ->pluck('original_name')
            ->all();

        $this->assertSame(['visible.pdf'], $attachments);
        $this->assertSame($dubai->id, $visible->branch_id);
    }

    public function test_branch_scoped_notifications_are_hidden_outside_allowed_branches(): void
    {
        [$tenant] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        [, $user, $dubai, $ajman] = $this->prepareBranchUsers();
        TenantNotification::query()->create($this->notificationPayload($dubai, 'Visible notification'));
        TenantNotification::query()->create($this->notificationPayload($ajman, 'Hidden notification'));

        $notifications = app(\App\Services\Tenancy\NotificationService::class)
            ->visibleForUser($user, 'automotive_service')
            ->pluck('title')
            ->all();

        $this->assertSame(['Visible notification'], $notifications);
    }

    public function test_central_customer_record_is_not_incorrectly_duplicated_or_deleted(): void
    {
        [$tenant] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        [, , $dubai] = $this->prepareBranchUsers();
        $customer = Customer::query()->create(['name' => 'Central Customer', 'phone' => '971500000000']);
        $vehicle = Vehicle::query()->create(['customer_id' => $customer->id, 'make' => 'Toyota', 'model' => 'Camry']);
        WorkOrder::query()->create([
            'branch_id' => $dubai->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'work_order_number' => 'WO-CUSTOMER',
            'title' => 'Central customer transaction',
            'status' => 'open',
            'customer_tracking_token' => Str::random(48),
        ]);

        $this->assertSame(1, Customer::query()->where('phone', '971500000000')->count());
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'Central Customer']);
    }

    public function test_transaction_level_customer_visibility_respects_allowed_branches_where_implemented(): void
    {
        [$tenant] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        [, $user, $dubai, $ajman] = $this->prepareBranchUsers();
        $visibleCustomer = Customer::query()->create(['name' => 'Dubai Customer']);
        $hiddenCustomer = Customer::query()->create(['name' => 'Ajman Customer']);
        $this->workOrder($dubai, 'WO-DUBAI-CUSTOMER', ['customer_id' => $visibleCustomer->id]);
        $this->workOrder($ajman, 'WO-AJMAN-CUSTOMER', ['customer_id' => $hiddenCustomer->id]);

        $customerIds = app(BranchScopeService::class)
            ->applyAllowedBranches(WorkOrder::query(), $user, 'automotive_service')
            ->pluck('customer_id')
            ->filter()
            ->all();

        $this->assertSame([$visibleCustomer->id], $customerIds);
    }

    public function test_revoked_branch_access_removes_visibility(): void
    {
        [$tenant] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        [, $user, $dubai] = $this->prepareBranchUsers();
        $this->workOrder($dubai, 'WO-DUBAI');
        app(ProductBranchAccessService::class)->revokeUserBranchAccess($user, $dubai, 'automotive_service');

        $this->assertSame([], app(BranchScopeService::class)->visibleBranchIds($user, 'automotive_service'));
    }

    public function test_disabled_product_branch_removes_visibility(): void
    {
        [$tenant] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        [, $user, $dubai] = $this->prepareBranchUsers();
        $this->workOrder($dubai, 'WO-DUBAI');
        app(ProductBranchAccessService::class)->disableBranch($dubai, 'automotive_service');

        $this->assertSame([], app(BranchScopeService::class)->visibleBranchIds($user, 'automotive_service'));
    }

    protected function prepareTenantWorkspace(): array
    {
        $tenant = Tenant::query()->create([
            'id' => 'tenant-branch-scope-' . Str::uuid(),
            'data' => ['company_name' => 'Branch Scope Test'],
        ]);

        $this->tenantDatabaseFiles[] = database_path($tenant->database()->getName());

        $domain = $tenant->id . '.example.test';
        Domain::query()->create(['domain' => $domain, 'tenant_id' => $tenant->id]);
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
            'name' => Str::headline(str_replace('_', ' ', $productKey)),
            'slug' => Str::slug($productKey) . '-' . Str::uuid(),
            'is_active' => true,
        ]);

        $plan = Plan::query()->create([
            'product_id' => $product->id,
            'name' => Str::headline($productKey) . ' Plan',
            'slug' => Str::slug($productKey) . '-plan-' . Str::uuid(),
            'price' => 99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'max_users' => 10,
            'max_branches' => 5,
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'product_key' => $productKey,
            'plan_id' => $plan->id,
            'status' => 'active',
            'included_seats' => 10,
            'extra_seats' => 0,
            'branch_limit' => 5,
        ]);
    }

    protected function prepareBranchUsers(bool $grantAjman = false): array
    {
        $owner = $this->tenantUser('owner@example.test');
        $user = $this->tenantUser('advisor@example.test');
        $dubai = $this->branch('Dubai Branch');
        $ajman = $this->branch('Ajman Branch');

        app(TenantUserProductAccessService::class)->grantAccess($user, 'automotive_service', $owner);
        app(ProductBranchAccessService::class)->enableBranch($dubai, 'automotive_service');
        app(ProductBranchAccessService::class)->enableBranch($ajman, 'automotive_service');
        app(ProductBranchAccessService::class)->grantUserBranchAccess($user, $dubai, 'automotive_service');

        if ($grantAjman) {
            app(ProductBranchAccessService::class)->grantUserBranchAccess($user, $ajman, 'automotive_service');
        }

        return [$owner, $user, $dubai, $ajman];
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
            'code' => Str::slug($name),
            'is_active' => true,
        ]);
    }

    protected function workOrder(Branch $branch, string $number, array $overrides = []): WorkOrder
    {
        $customer = Customer::query()->firstOrCreate(['name' => $number . ' Customer']);
        $vehicle = Vehicle::query()->create([
            'customer_id' => $customer->id,
            'make' => 'Toyota',
            'model' => 'Camry',
        ]);

        return WorkOrder::query()->create($overrides + [
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'work_order_number' => $number,
            'title' => $number,
            'status' => 'open',
            'customer_tracking_token' => Str::random(48),
        ]);
    }

    protected function checkIn(Branch $branch, string $number): VehicleCheckIn
    {
        $customer = Customer::query()->create(['name' => $number . ' Customer']);
        $vehicle = Vehicle::query()->create([
            'customer_id' => $customer->id,
            'make' => 'Nissan',
            'model' => 'Patrol',
        ]);

        return VehicleCheckIn::query()->create([
            'check_in_number' => $number,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'status' => 'checked_in',
            'checked_in_at' => now(),
        ]);
    }

    protected function attachmentPayload(Customer $customer, Branch $branch, string $name): array
    {
        return [
            'tenant_id' => (string) tenant()->id,
            'product_key' => 'automotive_service',
            'branch_id' => $branch->id,
            'attachable_type' => Customer::class,
            'attachable_id' => $customer->id,
            'original_name' => $name,
            'stored_name' => $name,
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'file_size' => 100,
            'disk' => 'local',
            'storage_path' => 'tests/' . $name,
            'visibility' => 'private',
        ];
    }

    protected function notificationPayload(Branch $branch, string $title): array
    {
        return [
            'tenant_id' => (string) tenant()->id,
            'product_key' => 'automotive_service',
            'branch_id' => $branch->id,
            'event_key' => Str::slug($title),
            'channel' => 'in_app',
            'title' => $title,
            'body' => $title,
            'status' => 'delivered',
            'sent_at' => now(),
        ];
    }
}
