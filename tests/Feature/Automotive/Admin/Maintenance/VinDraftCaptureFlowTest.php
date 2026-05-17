<?php

namespace Tests\Feature\Automotive\Admin\Maintenance;

use App\Models\Branch;
use App\Models\Maintenance\VehicleCheckIn;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\User;
use App\Services\Automotive\Maintenance\VinOcrService;
use App\Services\Tenancy\ProductBranchAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;
use Tests\TestCase;

class VinDraftCaptureFlowTest extends TestCase
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

    public function test_authenticated_admin_can_access_check_in_create_page(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->ownerUser();
        $this->enableBranch('Dubai Branch');
        tenancy()->end();

        $this->actingAs($owner, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/maintenance/check-ins/create")
            ->assertOk()
            ->assertSee('VIN Verification');
    }

    public function test_create_page_contains_camera_capture_ui_hooks(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->ownerUser();
        $this->enableBranch('Dubai Branch');
        tenancy()->end();

        $this->actingAs($owner, 'automotive_admin')
            ->get("http://{$domain}/workspace/admin/maintenance/check-ins/create")
            ->assertOk()
            ->assertSee('Capture VIN Photo')
            ->assertSee('Upload VIN Photo')
            ->assertSee('Confirm VIN')
            ->assertSee('Manual VIN Entry')
            ->assertSee('VIN Source')
            ->assertSee('Why was VIN entered manually?')
            ->assertSee('data-vin-source', false)
            ->assertSee('data-vin-unreadable-reason', false)
            ->assertSee('data-vin-evidence-guidance', false)
            ->assertSee('OCR is only a suggestion', false)
            ->assertSee('photo still acts as evidence', false)
            ->assertSee('data-vin-camera', false)
            ->assertSee('data-vin-open-camera', false)
            ->assertSee('data-vin-video-preview', false)
            ->assertSee('data-vin-capture-photo', false)
            ->assertSee('data-vin-cancel-camera', false)
            ->assertSee('data-vin-upload-fallback', false)
            ->assertSee('data-vin-manual-entry', false)
            ->assertSee('data-vin-camera-alert', false)
            ->assertSee('data-vin-camera-error-name', false)
            ->assertSee('navigator.mediaDevices.getUserMedia', false)
            ->assertSee('facingMode', false)
            ->assertSee('environment', false)
            ->assertSee('playsinline', false)
            ->assertSee('autoplay', false)
            ->assertSee('muted', false)
            ->assertSee('accept="image/*"', false)
            ->assertSee('capture="environment"', false)
            ->assertSee('Camera permission was denied', false)
            ->assertSee('Camera access requires HTTPS', false)
            ->assertSee('This browser does not support direct camera access', false)
            ->assertSee('vinCameraPreview', false)
            ->assertSee('vinCameraCaptureButton', false)
            ->assertSee('vinPhotoInput', false)
            ->assertSee('vinCaptureUrl', false)
            ->assertSee('vinSearchUrl', false);
    }

    public function test_draft_vin_capture_endpoint_accepts_image_upload_and_returns_ocr_payload(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->ownerUser();
        $this->enableBranch('Dubai Branch');
        tenancy()->end();

        $this->mock(VinOcrService::class, function ($mock): void {
            $mock->shouldReceive('analyzeUploadedFile')
                ->once()
                ->andReturn([
                    'ocr_available' => true,
                    'raw_text' => 'VIN JTDKB20U777777777',
                    'detected_vin' => 'JTDKB20U777777777',
                    'extracted_vin' => 'JTDKB20U777777777',
                    'normalized_vin' => 'JTDKB20U777777777',
                    'ocr_status' => 'detected',
                    'confidence_score' => 90,
                    'vin_ocr_confidence' => 90,
                    'candidates' => ['JTDKB20U777777777'],
                    'vehicle_matches' => [],
                ]);
        });

        $this->actingAs($owner, 'automotive_admin')
            ->postJson("http://{$domain}/workspace/admin/maintenance/check-ins/capture-vin", [
                'vin_photo' => UploadedFile::fake()->image('vin.jpg', 800, 400),
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('attachment.category', 'vin_draft')
            ->assertJsonPath('analysis.detected_vin', 'JTDKB20U777777777')
            ->assertJsonPath('analysis.ocr_status', 'detected')
            ->assertJsonPath('analysis.normalized_vin', 'JTDKB20U777777777')
            ->assertJsonPath('analysis.confidence_score', 90)
            ->assertSee('Possible VIN detected');

        tenancy()->initialize($tenant);
        $this->assertSame(0, VehicleCheckIn::query()->count());
    }

    public function test_draft_vin_capture_gracefully_handles_ocr_unavailable(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->ownerUser();
        $this->enableBranch('Dubai Branch');
        tenancy()->end();

        $this->mock(VinOcrService::class, function ($mock): void {
            $mock->shouldReceive('analyzeUploadedFile')
                ->once()
                ->andReturn([
                    'ocr_available' => false,
                    'raw_text' => null,
                    'detected_vin' => null,
                    'extracted_vin' => null,
                    'normalized_vin' => null,
                    'ocr_status' => 'unavailable',
                    'confidence_score' => null,
                    'vin_ocr_confidence' => null,
                    'candidates' => [],
                    'vehicle_matches' => [],
                ]);
        });

        $this->actingAs($owner, 'automotive_admin')
            ->postJson("http://{$domain}/workspace/admin/maintenance/check-ins/capture-vin", [
                'vin_photo' => UploadedFile::fake()->image('vin.jpg', 800, 400),
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('attachment.category', 'vin_draft')
            ->assertJsonPath('analysis.ocr_available', false)
            ->assertJsonPath('analysis.ocr_status', 'unavailable')
            ->assertJsonPath('analysis.detected_vin', null)
            ->assertSee('OCR is unavailable. The photo was saved as evidence. Please enter the VIN manually.');
    }

    public function test_draft_vin_capture_saves_evidence_when_no_ocr_candidate_is_detected(): void
    {
        [$tenant, $domain] = $this->prepareTenantWorkspace();

        tenancy()->initialize($tenant);
        $owner = $this->ownerUser();
        $this->enableBranch('Dubai Branch');
        tenancy()->end();

        $this->mock(VinOcrService::class, function ($mock): void {
            $mock->shouldReceive('analyzeUploadedFile')
                ->once()
                ->andReturn([
                    'ocr_available' => true,
                    'ocr_status' => 'not_detected',
                    'raw_text' => 'dirty glare unreadable',
                    'detected_vin' => null,
                    'extracted_vin' => null,
                    'normalized_vin' => null,
                    'confidence_score' => null,
                    'vin_ocr_confidence' => null,
                    'candidates' => [],
                    'vehicle_matches' => [],
                ]);
        });

        $this->actingAs($owner, 'automotive_admin')
            ->postJson("http://{$domain}/workspace/admin/maintenance/check-ins/capture-vin", [
                'vin_photo' => UploadedFile::fake()->image('vin.jpg', 800, 400),
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('attachment.category', 'vin_draft')
            ->assertJsonPath('analysis.ocr_status', 'not_detected')
            ->assertJsonPath('analysis.detected_vin', null)
            ->assertSee('No VIN detected. The photo was saved as evidence. Please enter the VIN manually.');
    }

    protected function prepareTenantWorkspace(): array
    {
        $tenant = Tenant::query()->create([
            'id' => 'tenant-vin-draft-' . Str::uuid(),
            'data' => ['company_name' => 'VIN Draft Test'],
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

    protected function enableBranch(string $name): Branch
    {
        $branch = Branch::query()->create([
            'name' => $name,
            'code' => Str::slug($name),
            'is_active' => true,
        ]);

        app(ProductBranchAccessService::class)->enableBranch($branch, 'automotive_service');

        return $branch;
    }
}
