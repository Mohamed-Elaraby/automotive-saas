<?php

namespace App\Services\Tenancy;

use Illuminate\Support\Collection;

class WorkspaceIntegrationCatalogService
{
    public function __construct(
        protected WorkspaceProductFamilyResolver $workspaceProductFamilyResolver
    ) {
    }

    public function getIntegrations(Collection $workspaceProducts, ?array $focusedProduct): array
    {
        $focusedFamily = $this->workspaceProductFamilyResolver->resolveFromWorkspaceProduct($focusedProduct);
        $familyProducts = $workspaceProducts
            ->filter(fn (array $product) => ! empty($product['is_accessible']))
            ->mapWithKeys(fn (array $product) => [
                $this->workspaceProductFamilyResolver->resolveFromWorkspaceProduct($product) => $product,
            ]);

        return match ($focusedFamily) {
            'parts_inventory' => $this->partsInventoryIntegrations($familyProducts),
            'accounting' => $this->accountingIntegrations($familyProducts),
            default => $this->automotiveIntegrations($familyProducts),
        };
    }

    protected function automotiveIntegrations(Collection $familyProducts): array
    {
        $integrations = [];

        if ($familyProducts->has('parts_inventory')) {
            $partsProduct = $familyProducts->get('parts_inventory');
            $integrations[] = [
                'key' => 'automotive-parts',
                'title' => 'Workshop uses spare-parts stock',
                'description' => 'Service operations can consume stock items, transfers, and inventory visibility from the Spare Parts workspace without duplicating inventory modules inside Automotive.',
                'target_label' => 'Open Spare Parts',
                'target_route' => 'automotive.admin.modules.supplier-catalog',
                'target_params' => ['workspace_product' => $partsProduct['product_code']],
            ];
        }

        if ($familyProducts->has('accounting')) {
            $accountingProduct = $familyProducts->get('accounting');
            $integrations[] = [
                'key' => 'automotive-accounting',
                'title' => 'Workshop can hand off financial events',
                'description' => 'Labor, service revenue, and future workshop costs can flow into Accounting instead of living in a separate isolated product.',
                'target_label' => 'Open Accounting',
                'target_route' => 'automotive.admin.modules.general-ledger',
                'target_params' => ['workspace_product' => $accountingProduct['product_code']],
            ];
        }

        return $integrations;
    }

    protected function partsInventoryIntegrations(Collection $familyProducts): array
    {
        $integrations = [];

        if ($familyProducts->has('automotive_service')) {
            $serviceProduct = $familyProducts->get('automotive_service');
            $integrations[] = [
                'key' => 'parts-automotive',
                'title' => 'Spare parts feed workshop operations',
                'description' => 'Stock items and supplier-backed inventory remain here, while workshop operations consume those items from the Automotive workspace.',
                'target_label' => 'Open Workshop',
                'target_route' => 'automotive.admin.modules.workshop-operations',
                'target_params' => ['workspace_product' => $serviceProduct['product_code']],
            ];
        }

        if ($familyProducts->has('accounting')) {
            $accountingProduct = $familyProducts->get('accounting');
            $integrations[] = [
                'key' => 'parts-accounting',
                'title' => 'Inventory can flow into accounting',
                'description' => 'Purchasing, valuation, and stock costs can later be posted into Accounting without duplicating inventory controls there.',
                'target_label' => 'Open Accounting',
                'target_route' => 'automotive.admin.modules.general-ledger',
                'target_params' => ['workspace_product' => $accountingProduct['product_code']],
            ];
        }

        return $integrations;
    }

    protected function accountingIntegrations(Collection $familyProducts): array
    {
        $integrations = [];

        if ($familyProducts->has('automotive_service')) {
            $serviceProduct = $familyProducts->get('automotive_service');
            $integrations[] = [
                'key' => 'accounting-automotive',
                'title' => 'Accounting can receive service-side activity',
                'description' => 'Service revenue, labor, and future workshop costing events can be integrated into accounting flows from the Automotive workspace.',
                'target_label' => 'Open Workshop',
                'target_route' => 'automotive.admin.modules.workshop-operations',
                'target_params' => ['workspace_product' => $serviceProduct['product_code']],
            ];
        }

        if ($familyProducts->has('parts_inventory')) {
            $partsProduct = $familyProducts->get('parts_inventory');
            $integrations[] = [
                'key' => 'accounting-parts',
                'title' => 'Accounting can receive stock valuation events',
                'description' => 'Inventory purchases and stock valuation can be integrated from Spare Parts without forcing duplicate stock modules inside Accounting.',
                'target_label' => 'Open Spare Parts',
                'target_route' => 'automotive.admin.modules.supplier-catalog',
                'target_params' => ['workspace_product' => $partsProduct['product_code']],
            ];
        }

        return $integrations;
    }
}
