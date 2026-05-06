<?php

namespace Tests\Feature\Tenancy;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Maintenance\MaintenanceAttachment;
use App\Models\Maintenance\MaintenanceNotification;
use App\Models\NotificationTemplate;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantAttachment;
use App\Models\TenantNotification;
use App\Models\TenantProductSubscription;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Services\Automotive\Maintenance\MaintenanceAttachmentService;
use App\Services\Automotive\Maintenance\MaintenanceNotificationService;
use App\Services\Tenancy\AttachmentService;
use App\Services\Tenancy\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Stancl\Tenancy\Database\Models\Domain;
use Tests\TestCase;

class AttachmentAndNotificationFoundationTest extends TestCase
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

    public function test_attachment_can_be_stored_with_product_key(): void
    {
        $tenant = $this->prepareTenant();
        $this->attachProductSubscription($tenant, 'automotive', ['storage_limit_mb' => 5, 'max_file_size_mb' => 2, 'allowed_file_types' => 'pdf,jpg,png']);

        tenancy()->initialize($tenant);
        Storage::fake('public');

        $workOrder = $this->workOrder();
        $attachment = app(AttachmentService::class)->storeAttachment(
            $workOrder,
            UploadedFile::fake()->create('job-card.pdf', 128, 'application/pdf'),
            'automotive',
            ['branch_id' => $workOrder->branch_id]
        );

        $this->assertSame('automotive', $attachment->product_key);
        $this->assertSame(WorkOrder::class, $attachment->attachable_type);
        $this->assertTrue(Storage::disk('public')->exists($attachment->storage_path));
    }

    public function test_attachment_can_be_linked_to_any_model_using_morph(): void
    {
        $tenant = $this->prepareTenant();
        $this->attachProductSubscription($tenant, 'automotive', ['storage_limit_mb' => 5, 'max_file_size_mb' => 2, 'allowed_file_types' => 'pdf,jpg,png']);

        tenancy()->initialize($tenant);
        Storage::fake('public');

        $vehicle = $this->vehicle();
        $attachment = app(AttachmentService::class)->storeAttachment(
            $vehicle,
            UploadedFile::fake()->create('vehicle-photo.jpg', 64, 'image/jpeg'),
            'automotive'
        );

        $this->assertTrue($attachment->attachable()->is($vehicle));
        $this->assertSame([$attachment->id], app(AttachmentService::class)->listForEntity($vehicle, 'automotive')->pluck('id')->all());
    }

    public function test_storage_usage_can_be_calculated_per_tenant_and_product(): void
    {
        $tenant = $this->prepareTenant();
        $this->attachProductSubscription($tenant, 'automotive', ['storage_limit_mb' => 5, 'max_file_size_mb' => 2, 'allowed_file_types' => 'pdf,jpg']);
        $this->attachProductSubscription($tenant, 'accounting', ['storage_limit_mb' => 5, 'max_file_size_mb' => 2, 'allowed_file_types' => 'pdf']);

        tenancy()->initialize($tenant);
        Storage::fake('public');

        $workOrder = $this->workOrder();
        $service = app(AttachmentService::class);
        $service->storeAttachment($workOrder, UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'), 'automotive');
        $service->storeAttachment($workOrder, UploadedFile::fake()->create('b.pdf', 200, 'application/pdf'), 'accounting');

        $this->assertGreaterThan(0, $service->calculateTenantUsage());
        $this->assertSame(TenantAttachment::query()->where('product_key', 'automotive')->sum('file_size'), $service->calculateProductUsage('automotive'));
    }

    public function test_file_size_limit_is_enforced(): void
    {
        $tenant = $this->prepareTenant();
        $this->attachProductSubscription($tenant, 'automotive', ['storage_limit_mb' => 5, 'max_file_size_mb' => 1, 'allowed_file_types' => 'pdf']);

        tenancy()->initialize($tenant);
        Storage::fake('public');

        $this->expectException(ValidationException::class);

        app(AttachmentService::class)->storeAttachment(
            $this->workOrder(),
            UploadedFile::fake()->create('too-large.pdf', 2048, 'application/pdf'),
            'automotive'
        );
    }

    public function test_extra_storage_addon_increases_storage_limit(): void
    {
        $tenant = $this->prepareTenant();
        $this->attachProductSubscription($tenant, 'automotive', ['storage_limit_mb' => 1, 'max_file_size_mb' => 2, 'allowed_file_types' => 'pdf']);
        $this->addAddon($tenant->id, 'automotive', 'extra_storage', 1);

        tenancy()->initialize($tenant);
        Storage::fake('public');

        $service = app(AttachmentService::class);
        $workOrder = $this->workOrder();
        $service->storeAttachment($workOrder, UploadedFile::fake()->create('one.pdf', 900, 'application/pdf'), 'automotive');
        $service->storeAttachment($workOrder, UploadedFile::fake()->create('two.pdf', 900, 'application/pdf'), 'automotive');

        $this->assertSame(2, TenantAttachment::query()->count());
    }

    public function test_allowed_file_types_are_enforced(): void
    {
        $tenant = $this->prepareTenant();
        $this->attachProductSubscription($tenant, 'automotive', ['storage_limit_mb' => 5, 'max_file_size_mb' => 2, 'allowed_file_types' => 'pdf']);

        tenancy()->initialize($tenant);
        Storage::fake('public');

        $this->expectException(ValidationException::class);

        app(AttachmentService::class)->storeAttachment(
            $this->workOrder(),
            UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream'),
            'automotive'
        );
    }

    public function test_in_app_notification_can_be_created_for_product_event(): void
    {
        $tenant = $this->prepareTenant();
        $this->attachProductSubscription($tenant, 'automotive');
        $this->addAddon($tenant->id, 'automotive', 'notifications.in_app', 1);

        tenancy()->initialize($tenant);

        $notification = app(NotificationService::class)->createInAppNotification('automotive', 'automotive.work_order_created', [
            'title' => 'Work order created',
            'body' => 'WO-1 created',
        ], [
            'recipient_type' => 'user',
            'recipient_id' => 10,
        ]);

        $this->assertSame('automotive', $notification->product_key);
        $this->assertSame('automotive.work_order_created', $notification->event_key);
        $this->assertSame('delivered', $notification->status);
    }

    public function test_notification_template_can_be_rendered(): void
    {
        $tenant = $this->prepareTenant();
        $this->attachProductSubscription($tenant, 'automotive');

        tenancy()->initialize($tenant);

        NotificationTemplate::query()->create([
            'tenant_id' => $tenant->id,
            'product_key' => 'automotive',
            'event_key' => 'automotive.vehicle_ready',
            'channel' => 'in_app',
            'subject' => 'Vehicle {{ plate_number }} ready',
            'body' => 'Customer {{ customer.name }} can collect {{ plate_number }}.',
        ]);

        $rendered = app(NotificationService::class)->renderTemplate('automotive', 'automotive.vehicle_ready', 'in_app', [
            'plate_number' => 'AUH-123',
            'customer' => ['name' => 'Ahmed'],
        ]);

        $this->assertSame('Vehicle AUH-123 ready', $rendered['subject']);
        $this->assertSame('Customer Ahmed can collect AUH-123.', $rendered['body']);
    }

    public function test_notification_respects_product_key(): void
    {
        $tenant = $this->prepareTenant();
        $this->attachProductSubscription($tenant, 'automotive');
        $this->attachProductSubscription($tenant, 'accounting');
        $this->addAddon($tenant->id, 'automotive', 'notifications.in_app', 1);
        $this->addAddon($tenant->id, 'accounting', 'notifications.in_app', 1);

        tenancy()->initialize($tenant);

        $service = app(NotificationService::class);
        $service->createInAppNotification('automotive', 'automotive.work_order_created', ['body' => 'WO']);
        $service->createInAppNotification('accounting', 'accounting.payment_received', ['body' => 'PAY']);

        $this->assertSame(1, TenantNotification::query()->where('product_key', 'automotive')->count());
        $this->assertSame(1, TenantNotification::query()->where('product_key', 'accounting')->count());
    }

    public function test_disabled_channel_is_blocked_by_entitlement(): void
    {
        $tenant = $this->prepareTenant();
        $this->attachProductSubscription($tenant, 'automotive');

        tenancy()->initialize($tenant);

        $this->expectException(RuntimeException::class);

        app(NotificationService::class)->dispatchEvent('automotive', 'automotive.vehicle_ready', [], [
            'channels' => ['email'],
        ]);
    }

    public function test_mark_as_read_and_archive_work(): void
    {
        $tenant = $this->prepareTenant();
        $this->attachProductSubscription($tenant, 'automotive');
        $this->addAddon($tenant->id, 'automotive', 'notifications.in_app', 1);

        tenancy()->initialize($tenant);

        $service = app(NotificationService::class);
        $notification = $service->createInAppNotification('automotive', 'automotive.quotation_approved', ['body' => 'Approved']);

        $this->assertNotNull($service->markAsRead($notification)->read_at);
        $this->assertNotNull($service->archive($notification)->archived_at);
    }

    public function test_legacy_maintenance_attachment_and_notification_are_bridged_without_breaking(): void
    {
        $tenant = $this->prepareTenant();
        $this->attachProductSubscription($tenant, 'automotive', ['storage_limit_mb' => 5, 'max_file_size_mb' => 2, 'allowed_file_types' => 'jpg,pdf']);

        tenancy()->initialize($tenant);
        Storage::fake('public');

        $workOrder = $this->workOrder();
        $legacyAttachment = app(MaintenanceAttachmentService::class)->store(
            $workOrder,
            UploadedFile::fake()->create('legacy.jpg', 64, 'image/jpeg'),
            ['branch_id' => $workOrder->branch_id, 'category' => 'job_photo']
        );
        $legacyNotification = app(MaintenanceNotificationService::class)->create('work_order_created', 'Work order created', [
            'branch_id' => $workOrder->branch_id,
            'payload' => ['work_order_number' => $workOrder->work_order_number],
        ]);

        $this->assertInstanceOf(MaintenanceAttachment::class, $legacyAttachment);
        $this->assertInstanceOf(MaintenanceNotification::class, $legacyNotification);
        $this->assertSame(1, TenantAttachment::query()->where('product_key', 'automotive')->count());
        $this->assertSame(1, TenantNotification::query()->where('event_key', 'automotive.work_order_created')->count());
    }

    protected function prepareTenant(): Tenant
    {
        $tenant = Tenant::query()->create([
            'id' => 'tenant-attachments-' . Str::uuid(),
            'data' => ['company_name' => 'Tenant Attachment Test'],
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

    protected function attachProductSubscription(Tenant $tenant, string $productKey, array $limits = []): void
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
            'name' => Str::headline($productKey) . ' Attachment Plan',
            'slug' => Str::slug($productKey) . '-attachment-plan-' . uniqid(),
            'price' => 99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'max_users' => 5,
            'max_branches' => 3,
        ]);

        foreach ($limits as $key => $value) {
            DB::table('plan_limits')->insert([
                'product_key' => $productKey,
                'plan_id' => $plan->id,
                'limit_key' => $key,
                'limit_value' => is_array($value) ? json_encode($value) : (string) $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

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

    protected function addAddon(string $tenantId, string $productKey, string $addonKey, int $quantity): void
    {
        DB::table('subscription_addons')->insert([
            'tenant_id' => $tenantId,
            'product_key' => $productKey,
            'addon_key' => $addonKey,
            'quantity' => $quantity,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function workOrder(): WorkOrder
    {
        $branch = Branch::query()->create(['name' => 'Abu Dhabi Main', 'code' => 'AUH', 'is_active' => true]);
        $customer = Customer::query()->create(['name' => 'Ahmed Customer']);
        $vehicle = Vehicle::query()->create(['customer_id' => $customer->id, 'make' => 'Toyota', 'model' => 'Corolla']);

        return WorkOrder::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'work_order_number' => 'WO-' . Str::upper(Str::random(6)),
            'title' => 'Attachment Work Order',
            'status' => 'open',
            'opened_at' => now(),
        ]);
    }

    protected function vehicle(): Vehicle
    {
        $customer = Customer::query()->create(['name' => 'Vehicle Customer']);

        return Vehicle::query()->create(['customer_id' => $customer->id, 'make' => 'Nissan', 'model' => 'Patrol']);
    }
}
