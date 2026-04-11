<?php

namespace Tests\Feature\Tenancy;

use App\Services\Tenancy\WorkspaceIntegrationCatalogService;
use App\Services\Tenancy\WorkspaceModuleCatalogService;
use App\Services\Tenancy\WorkspaceProductFamilyResolver;
use Illuminate\Support\Collection;
use Tests\TestCase;

class WorkspaceManifestServiceTest extends TestCase
{
    public function test_family_can_be_resolved_from_manifest_alias_without_service_code_changes(): void
    {
        config()->set('workspace_products.families.retail_commerce', [
            'aliases' => ['retail', 'commerce', 'pos'],
            'experience' => [
                'eyebrow' => 'Retail Focus',
                'title' => 'Retail commerce workspace',
                'description' => 'Retail-first module manifest.',
                'accent' => 'success',
            ],
            'sidebar_section' => [
                'key' => 'retail',
                'title' => 'Retail',
                'items' => [
                    [
                        'key' => 'retail.pos',
                        'label' => 'Point of Sale',
                        'icon' => 'isax-shop',
                        'route' => 'automotive.admin.dashboard',
                        'pages' => ['dashboard'],
                    ],
                ],
            ],
            'dashboard_actions' => [
                [
                    'key' => 'retail.open-pos',
                    'label' => 'Open POS',
                    'icon' => 'isax-shop',
                    'route' => 'automotive.admin.dashboard',
                    'variant' => 'primary',
                ],
            ],
            'integrations' => [
                [
                    'key' => 'retail-accounting',
                    'requires_family' => 'accounting',
                    'title' => 'Retail can hand off to accounting',
                    'description' => 'Retail posting bridge.',
                    'target_label' => 'Open Accounting',
                    'target_route' => 'automotive.admin.modules.general-ledger',
                ],
            ],
        ]);

        $resolver = app(WorkspaceProductFamilyResolver::class);
        $catalog = app(WorkspaceModuleCatalogService::class);
        $integrations = app(WorkspaceIntegrationCatalogService::class);

        $focusedProduct = [
            'product_code' => 'retail_pos_suite',
            'product_slug' => 'retail-pos-suite',
            'product_name' => 'Retail POS Suite',
        ];

        $this->assertSame('retail_commerce', $resolver->resolveFromWorkspaceProduct($focusedProduct));

        $sidebarSections = $catalog->getSidebarSections($focusedProduct);
        $dashboardActions = $catalog->getDashboardActions($focusedProduct);

        $this->assertSame('Retail', $sidebarSections[1]['title']);
        $this->assertSame('Point of Sale', $sidebarSections[1]['items'][0]['label']);
        $this->assertSame('Open POS', $dashboardActions[0]['label']);

        $workspaceProducts = new Collection([
            [
                'product_code' => 'retail_pos_suite',
                'product_slug' => 'retail-pos-suite',
                'product_name' => 'Retail POS Suite',
                'is_accessible' => true,
            ],
            [
                'product_code' => 'accounting_suite',
                'product_slug' => 'accounting-suite',
                'product_name' => 'Accounting Suite',
                'is_accessible' => true,
            ],
        ]);

        $integrationItems = $integrations->getIntegrations($workspaceProducts, $focusedProduct);

        $this->assertCount(1, $integrationItems);
        $this->assertSame('retail-accounting', $integrationItems[0]['key']);
        $this->assertSame('accounting_suite', $integrationItems[0]['target_params']['workspace_product']);
    }
}
