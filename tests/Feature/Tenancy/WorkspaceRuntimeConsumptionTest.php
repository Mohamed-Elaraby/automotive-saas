<?php

namespace Tests\Feature\Tenancy;

use App\Models\AppSetting;
use App\Services\Tenancy\WorkspaceIntegrationCatalogService;
use App\Services\Tenancy\WorkspaceIntegrationContractService;
use App\Services\Tenancy\WorkspaceManifestService;
use App\Services\Tenancy\WorkspaceModuleCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class WorkspaceRuntimeConsumptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_runtime_manifest_service_can_consume_saved_writeback_package_without_config_edit(): void
    {
        AppSetting::query()->create([
            'group_key' => 'workspace_products',
            'key' => 'workspace_products.manifest_writeback_package.perfume_retail',
            'value' => json_encode([
                'family_key' => 'perfume_retail',
                'mode' => 'add',
                'family_payload' => [
                    'aliases' => ['perfume', 'fragrance'],
                    'experience' => [
                        'title' => 'Perfume retail workspace',
                    ],
                    'sidebar_section' => [
                        'key' => 'perfume-retail',
                        'title' => 'Perfume Retail',
                        'items' => [
                            [
                                'key' => 'perfume.catalog',
                                'label' => 'Catalog',
                                'route' => 'automotive.admin.dashboard',
                            ],
                        ],
                    ],
                    'dashboard_actions' => [
                        [
                            'key' => 'perfume.open-catalog',
                            'label' => 'Open Catalog',
                            'route' => 'automotive.admin.dashboard',
                        ],
                    ],
                    'integrations' => [
                        [
                            'key' => 'perfume-accounting',
                            'target_product_code' => 'ACCOUNTING',
                            'title' => 'Retail can hand off to accounting',
                            'description' => 'Retail sales can move into accounting workflows.',
                            'target_label' => 'Open Accounting',
                            'target_route' => 'automotive.admin.modules.general-ledger',
                        ],
                    ],
                    'runtime_modules' => [
                        'catalog-management' => [
                            'family' => 'perfume_retail',
                            'focus_code' => 'perfume_retail',
                            'title' => 'Catalog Management',
                            'description' => 'Manage fragrance catalog.',
                        ],
                    ],
                ],
                'captured_at' => now()->toDateTimeString(),
            ], JSON_UNESCAPED_SLASHES),
            'value_type' => 'json',
        ]);

        $manifest = app(WorkspaceManifestService::class);
        $catalog = app(WorkspaceModuleCatalogService::class);
        $integrations = app(WorkspaceIntegrationCatalogService::class);

        $focusedProduct = [
            'product_code' => 'perfume_retail',
            'product_slug' => 'perfume-retail',
            'product_name' => 'Perfume Retail Management',
        ];

        $this->assertContains('perfume_retail', $manifest->familyKeys());
        $this->assertSame('Perfume Retail', $manifest->sidebarSection('perfume_retail')['title']);
        $this->assertSame('Open Catalog', $manifest->dashboardActions('perfume_retail')[0]['label']);
        $this->assertSame('Catalog Management', $manifest->runtimeModule('catalog-management')['title']);

        $sidebarSections = $catalog->getSidebarSections($focusedProduct);
        $dashboardActions = $catalog->getDashboardActions($focusedProduct);

        $this->assertSame('Perfume Retail', $sidebarSections[1]['title']);
        $this->assertSame('Open Catalog', $dashboardActions[0]['label']);

        $workspaceProducts = new Collection([
            [
                'product_code' => 'perfume_retail',
                'product_slug' => 'perfume-retail',
                'product_name' => 'Perfume Retail Management',
                'is_accessible' => true,
            ],
            [
                'product_code' => 'accounting',
                'product_slug' => 'accounting',
                'product_name' => 'Accounting System',
                'status_label' => 'ACTIVE',
                'is_accessible' => false,
            ],
        ]);

        $this->assertSame([], $integrations->getIntegrations($workspaceProducts, $focusedProduct));

        $workspaceProducts = new Collection([
            [
                'product_code' => 'perfume_retail',
                'product_slug' => 'perfume-retail',
                'product_name' => 'Perfume Retail Management',
                'is_accessible' => true,
            ],
            [
                'product_code' => 'accounting',
                'product_slug' => 'accounting',
                'product_name' => 'Accounting System',
                'status_label' => 'ACTIVE',
                'is_accessible' => true,
            ],
        ]);

        $integrationItems = $integrations->getIntegrations($workspaceProducts, $focusedProduct);

        $this->assertCount(1, $integrationItems);
        $this->assertSame('perfume-accounting', $integrationItems[0]['key']);
        $this->assertSame('Accounting System', $integrationItems[0]['target_product_name']);
        $this->assertSame('accounting', $integrationItems[0]['target_product_code']);
        $this->assertSame(['workspace_product' => 'accounting'], $integrationItems[0]['target_params']);
    }

    public function test_dynamic_writeback_products_are_included_in_integration_contract_registry(): void
    {
        AppSetting::query()->create([
            'group_key' => 'workspace_products',
            'key' => 'workspace_products.manifest_writeback_package.quality_control',
            'value' => json_encode([
                'family_key' => 'quality_control',
                'mode' => 'add',
                'family_payload' => [
                    'aliases' => ['quality', 'inspection'],
                    'experience' => [
                        'title' => 'Quality control workspace',
                    ],
                    'runtime_modules' => [
                        'inspection-center' => [
                            'family' => 'quality_control',
                            'focus_code' => 'quality_control',
                            'title' => 'Inspection Center',
                            'description' => 'Review inspections before financial handoff.',
                        ],
                    ],
                    'integrations' => [
                        [
                            'key' => 'quality-accounting',
                            'requires_family' => 'accounting',
                            'title' => 'Quality costs can hand off to accounting',
                            'description' => 'Inspection charges can move into accounting workflows.',
                            'target_label' => 'Open Accounting',
                            'target_route' => 'automotive.admin.modules.general-ledger',
                            'events' => ['inspection.completed'],
                            'source_capabilities' => ['quality.inspections'],
                            'target_capabilities' => ['accounting.journal_posting'],
                            'payload_schema' => ['inspection_id' => 'integer', 'total_amount' => 'decimal'],
                        ],
                    ],
                ],
                'captured_at' => now()->toDateTimeString(),
            ], JSON_UNESCAPED_SLASHES),
            'value_type' => 'json',
        ]);

        $contracts = app(WorkspaceIntegrationContractService::class);

        $contract = $contracts->find('quality-accounting');

        $this->assertNotNull($contract);
        $this->assertSame('quality_control', $contract['source_family']);
        $this->assertSame('accounting', $contract['target_family']);
        $this->assertSame(['inspection.completed'], $contract['events']);
        $this->assertSame(['quality.inspections'], $contract['source_capabilities']);
        $this->assertSame(['accounting.journal_posting'], $contract['target_capabilities']);

        $matchingEnvelope = $contracts->findForEnvelope([
            'integration_key' => 'quality-accounting',
            'event_name' => 'inspection.completed',
            'source_product' => 'quality_control',
            'target_product' => 'accounting',
        ]);

        $this->assertNotNull($matchingEnvelope);
        $this->assertNull($contracts->findForEnvelope([
            'integration_key' => 'quality-accounting',
            'event_name' => 'unknown.completed',
            'source_product' => 'quality_control',
            'target_product' => 'accounting',
        ]));
    }
}
