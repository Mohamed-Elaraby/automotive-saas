<?php

namespace Tests\Feature\Tenancy;

use App\Models\Branch;
use App\Models\Core\Documents\GeneratedDocument;
use App\Models\Customer;
use App\Models\NumberingSequence;
use App\Models\Tenant;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Services\Automotive\Maintenance\MaintenanceDocumentService;
use App\Services\Core\Documents\DocumentFooterBuilder;
use App\Services\Core\Documents\DocumentHeaderBuilder;
use App\Services\Core\Documents\DocumentLayoutManager;
use App\Services\Core\Documents\DocumentRendererInterface;
use App\Services\Core\Documents\DTO\DocumentRenderRequest;
use App\Services\Core\Documents\DTO\DocumentRenderResult;
use App\Services\Core\Documents\MpdfDocumentRenderer;
use App\Services\Tenancy\NumberingSequenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;
use Tests\TestCase;

class DocumentEngineAndNumberingTest extends TestCase
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

    public function test_numbering_sequence_generates_expected_number(): void
    {
        $tenant = $this->prepareTenant();

        tenancy()->initialize($tenant);

        $branch = $this->branch('AUH', 'Abu Dhabi Main Branch');
        $number = app(NumberingSequenceService::class)->next('automotive', 'work_order', $branch->id, 2026);

        $this->assertSame('WO-AUH-2026-0001', $number);
    }

    public function test_numbering_sequence_increments_safely(): void
    {
        $tenant = $this->prepareTenant();

        tenancy()->initialize($tenant);

        $service = app(NumberingSequenceService::class);

        $this->assertSame('RCPT-2026-0001', $service->next('automotive', 'receipt', null, 2026));
        $this->assertSame('RCPT-2026-0002', $service->next('automotive', 'receipt', null, 2026));
        $this->assertSame(3, NumberingSequence::query()->where('document_type', 'receipt')->value('next_number'));
    }

    public function test_numbering_supports_product_document_branch_and_year_scope(): void
    {
        $tenant = $this->prepareTenant();

        tenancy()->initialize($tenant);

        $auh = $this->branch('AUH', 'Abu Dhabi Main Branch');
        $dxb = $this->branch('DXB', 'Dubai Branch');
        $service = app(NumberingSequenceService::class);

        $this->assertSame('INV-AUH-2026-0001', $service->next('automotive', 'invoice', $auh->id, 2026));
        $this->assertSame('INV-DXB-2026-0001', $service->next('automotive', 'invoice', $dxb->id, 2026));
        $this->assertSame('PO-2026-0001', $service->next('inventory', 'purchase_order', null, 2026));
        $this->assertSame(3, NumberingSequence::query()->count());
    }

    public function test_document_generation_creates_metadata_record_with_product_and_type(): void
    {
        $tenant = $this->prepareTenant();

        tenancy()->initialize($tenant);
        Storage::fake('local');
        $renderer = new FakeDocumentRenderer();
        $this->app->instance(DocumentRendererInterface::class, $renderer);

        $document = app(\App\Services\Core\Documents\DocumentGenerationService::class)->generate('accounting_tax_invoice', [
            'invoice' => ['invoice_number' => 'INV-TEST'],
            'customer' => ['name' => 'Ahmed Customer'],
            'lines' => [
                ['description' => 'Service', 'quantity' => 1, 'unit_price' => 100, 'total' => 100],
            ],
        ], [
            'metadata' => ['source' => 'test'],
            'generated_by' => null,
        ]);

        $this->assertSame('accounting', $document->product_key);
        $this->assertSame('accounting_tax_invoice', $document->document_type);
        $this->assertSame('test', $document->metadata['source']);
        $this->assertTrue(Storage::disk('local')->exists($document->file_path));
        $this->assertSame('ltr', $renderer->lastRequest?->direction);
    }

    public function test_document_renderer_supports_rtl_and_ltr_config(): void
    {
        $renderer = new MpdfDocumentRenderer(
            new DocumentLayoutManager(),
            new DocumentHeaderBuilder(),
            new DocumentFooterBuilder()
        );

        $rtlConfig = $renderer->mpdfConfig(new DocumentRenderRequest(
            documentType: 'accounting_tax_invoice',
            template: 'documents.products.accounting.tax_invoice',
            data: [],
            language: 'ar',
            direction: 'rtl',
            layout: ['orientation' => 'L']
        ));
        $ltrConfig = $renderer->mpdfConfig(new DocumentRenderRequest(
            documentType: 'accounting_tax_invoice',
            template: 'documents.products.accounting.tax_invoice',
            data: [],
            language: 'en',
            direction: 'ltr'
        ));

        $this->assertSame('utf-8', $rtlConfig['mode']);
        $this->assertSame('L', $rtlConfig['orientation']);
        $this->assertTrue($rtlConfig['autoScriptToLang']);
        $this->assertTrue($ltrConfig['autoLangToFont']);
    }

    public function test_long_document_layout_includes_repeated_header_footer_structure(): void
    {
        $styles = view('core.documents.layouts.styles')->render();
        $header = view('core.documents.layouts.header', [
            'document' => ['document_title' => 'Long Document', 'document_number' => 'DOC-1', 'version' => 1],
            'company' => ['name' => 'Demo Tenant'],
            'branch' => ['name' => 'Main Branch'],
        ])->render();
        $footer = view('core.documents.layouts.footer', [
            'document' => ['verify_url' => 'https://example.test/verify'],
        ])->render();

        $this->assertStringContainsString('thead { display: table-header-group; }', $styles);
        $this->assertStringContainsString('page-break-inside: avoid', $styles);
        $this->assertStringContainsString('Long Document', $header);
        $this->assertStringContainsString('{PAGENO} / {nbpg}', $footer);
    }

    public function test_automotive_legacy_document_generation_is_bridged_to_central_engine(): void
    {
        $tenant = $this->prepareTenant();

        tenancy()->initialize($tenant);
        Storage::fake('local');
        $this->app->instance(DocumentRendererInterface::class, new FakeDocumentRenderer());

        $branch = $this->branch('AUH', 'Abu Dhabi Main Branch');
        $customer = Customer::query()->create(['name' => 'Legacy Customer', 'phone' => '+971500001111']);
        $vehicle = Vehicle::query()->create(['customer_id' => $customer->id, 'make' => 'Toyota', 'model' => 'Corolla', 'plate_number' => 'AUH-1']);
        $workOrder = WorkOrder::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'work_order_number' => 'WO-LEGACY-1',
            'title' => 'Legacy PDF Work Order',
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $document = app(MaintenanceDocumentService::class)->generateWorkOrder($workOrder, [
            'language' => 'en',
            'direction' => 'ltr',
        ]);

        $this->assertSame('automotive', $document->product_key);
        $this->assertSame('maintenance_work_order', $document->document_type);
        $this->assertSame($workOrder->id, $document->documentable_id);
        $this->assertTrue(GeneratedDocument::query()->whereKey($document->id)->exists());
    }

    protected function prepareTenant(): Tenant
    {
        $tenant = Tenant::query()->create([
            'id' => 'tenant-documents-' . Str::uuid(),
            'data' => ['company_name' => 'Tenant Documents Test'],
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

    protected function branch(string $code, string $name): Branch
    {
        return Branch::query()->create([
            'name' => $name,
            'code' => $code,
            'address' => $name,
            'emirate' => str_contains($name, 'Dubai') ? 'Dubai' : 'Abu Dhabi',
            'city' => $name,
            'country' => 'United Arab Emirates',
            'timezone' => 'Asia/Dubai',
            'is_active' => true,
        ]);
    }
}

class FakeDocumentRenderer implements DocumentRendererInterface
{
    public ?DocumentRenderRequest $lastRequest = null;

    public function render(DocumentRenderRequest $request): DocumentRenderResult
    {
        $this->lastRequest = $request;

        return new DocumentRenderResult("%PDF-1.4\n% Fake PDF\n");
    }
}
