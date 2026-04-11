<?php

namespace App\Services\Tenancy;

class WorkspaceModuleCatalogService
{
    public function __construct(
        protected WorkspaceProductFamilyResolver $workspaceProductFamilyResolver
    ) {
    }

    public function getFocusedProductFamily(?array $focusedProduct): string
    {
        return $this->workspaceProductFamilyResolver->resolveFromWorkspaceProduct($focusedProduct);
    }

    public function workspaceQuery(?array $focusedProduct): array
    {
        $productCode = trim((string) data_get($focusedProduct, 'product_code'));

        return $productCode !== '' ? ['workspace_product' => $productCode] : [];
    }

    public function getQuickCreateActions(?array $focusedProduct): array
    {
        $query = $this->workspaceQuery($focusedProduct);
        $sharedActions = [
            [
                'key' => 'shared.new-user',
                'label' => 'New User',
                'icon' => 'isax-user-add',
                'route' => 'automotive.admin.users.create',
                'params' => $query,
            ],
            [
                'key' => 'shared.new-branch',
                'label' => 'New Branch',
                'icon' => 'isax-buildings',
                'route' => 'automotive.admin.branches.create',
                'params' => $query,
            ],
        ];

        return $this->dedupeItems(array_merge(
            $sharedActions,
            $this->productQuickActions($focusedProduct, $query)
        ));
    }

    public function getSidebarSections(?array $focusedProduct): array
    {
        $query = $this->workspaceQuery($focusedProduct);
        $sections = [
            [
                'key' => 'shared-workspace',
                'title' => 'Shared Workspace',
                'items' => $this->dedupeItems([
                    [
                        'key' => 'shared.dashboard',
                        'label' => 'Dashboard',
                        'icon' => 'isax-element-45',
                        'route' => 'automotive.admin.dashboard',
                        'params' => $query,
                        'pages' => ['dashboard'],
                    ],
                    [
                        'key' => 'shared.users',
                        'label' => 'Users',
                        'icon' => 'isax-profile-2user5',
                        'route' => 'automotive.admin.users.index',
                        'params' => $query,
                        'pages' => ['users'],
                    ],
                    [
                        'key' => 'shared.branches',
                        'label' => 'Branches',
                        'icon' => 'isax-buildings-25',
                        'route' => 'automotive.admin.branches.index',
                        'params' => $query,
                        'pages' => ['branches'],
                    ],
                    [
                        'key' => 'shared.billing',
                        'label' => 'Plans & Billing',
                        'icon' => 'isax-crown5',
                        'route' => 'automotive.admin.billing.status',
                        'params' => $query,
                        'pages' => ['billing'],
                    ],
                ]),
            ],
        ];

        $productSection = $this->productSidebarSection($focusedProduct, $query);

        if ($productSection !== null) {
            $sections[] = $productSection;
        }

        return $sections;
    }

    public function getDashboardActions(?array $focusedProduct): array
    {
        $query = $this->workspaceQuery($focusedProduct);

        return $this->dedupeItems(match ($this->getFocusedProductFamily($focusedProduct)) {
            'parts_inventory' => [
                [
                    'key' => 'parts.add-stock-item',
                    'label' => 'Add Stock Item',
                    'icon' => 'isax-box-add',
                    'route' => 'automotive.admin.products.create',
                    'params' => $query,
                    'variant' => 'primary',
                ],
                [
                    'key' => 'parts.adjustment',
                    'label' => 'Adjustment',
                    'icon' => 'isax-arrows-swap',
                    'route' => 'automotive.admin.inventory-adjustments.create',
                    'params' => $query,
                    'variant' => 'outline-white',
                ],
                [
                    'key' => 'parts.transfer',
                    'label' => 'Transfer',
                    'icon' => 'isax-arrow-right-3',
                    'route' => 'automotive.admin.stock-transfers.create',
                    'params' => $query,
                    'variant' => 'outline-white',
                ],
            ],
            'accounting' => [
                [
                    'key' => 'accounting.general-ledger',
                    'label' => 'Open General Ledger',
                    'icon' => 'isax-wallet-3',
                    'route' => 'automotive.admin.modules.general-ledger',
                    'params' => $query,
                    'variant' => 'primary',
                ],
                [
                    'key' => 'shared.billing',
                    'label' => 'Manage Billing',
                    'icon' => 'isax-crown5',
                    'route' => 'automotive.admin.billing.status',
                    'params' => $query,
                    'variant' => 'outline-white',
                ],
            ],
            default => [
                [
                    'key' => 'service.workshop',
                    'label' => 'Open Workshop',
                    'icon' => 'isax-car',
                    'route' => 'automotive.admin.modules.workshop-operations',
                    'params' => $query,
                    'variant' => 'primary',
                ],
                [
                    'key' => 'shared.users',
                    'label' => 'Manage Users',
                    'icon' => 'isax-profile-2user',
                    'route' => 'automotive.admin.users.index',
                    'params' => $query,
                    'variant' => 'outline-white',
                ],
                [
                    'key' => 'shared.branches',
                    'label' => 'Manage Branches',
                    'icon' => 'isax-buildings',
                    'route' => 'automotive.admin.branches.index',
                    'params' => $query,
                    'variant' => 'outline-white',
                ],
            ],
        });
    }

    public function getFocusedProductExperience(?array $focusedProduct): array
    {
        $productCode = $this->getFocusedProductFamily($focusedProduct);

        return match ($productCode) {
            'parts_inventory' => [
                'eyebrow' => 'Spare Parts Focus',
                'title' => 'Inventory and stock movement workspace',
                'description' => 'Shared modules such as users and branches stay global, while spare-parts-specific inventory modules live here once and only once.',
                'accent' => 'warning',
            ],
            'accounting' => [
                'eyebrow' => 'Accounting Focus',
                'title' => 'Finance workspace foundation',
                'description' => 'Shared modules stay global across the tenant. Accounting contributes only its own finance modules, such as the general ledger.',
                'accent' => 'info',
            ],
            default => [
                'eyebrow' => 'Automotive Service Focus',
                'title' => 'Core workshop and service operations',
                'description' => 'Shared modules are shown once at workspace level. Automotive contributes only service modules, while inventory stays under Spare Parts.',
                'accent' => 'primary',
            ],
        };
    }

    protected function productQuickActions(?array $focusedProduct, array $query): array
    {
        return match ($this->getFocusedProductFamily($focusedProduct)) {
            'parts_inventory' => [
                [
                    'key' => 'parts.new-stock-item',
                    'label' => 'New Stock Item',
                    'icon' => 'isax-box-add',
                    'route' => 'automotive.admin.products.create',
                    'params' => $query,
                ],
                [
                    'key' => 'parts.inventory-adjustment',
                    'label' => 'Inventory Adjustment',
                    'icon' => 'isax-arrows-swap',
                    'route' => 'automotive.admin.inventory-adjustments.create',
                    'params' => $query,
                ],
                [
                    'key' => 'parts.stock-transfer',
                    'label' => 'Stock Transfer',
                    'icon' => 'isax-arrow-right-3',
                    'route' => 'automotive.admin.stock-transfers.create',
                    'params' => $query,
                ],
            ],
            default => [],
        };
    }

    protected function productSidebarSection(?array $focusedProduct, array $query): ?array
    {
        return match ($this->getFocusedProductFamily($focusedProduct)) {
            'automotive_service' => [
                'key' => 'automotive-service',
                'title' => 'Automotive Service',
                'items' => $this->dedupeItems([
                    [
                        'key' => 'service.workshop',
                        'label' => 'Workshop Operations',
                        'icon' => 'isax-car',
                        'route' => 'automotive.admin.modules.workshop-operations',
                        'params' => $query,
                        'pages' => ['workshop-operations'],
                    ],
                ]),
            ],
            'parts_inventory' => [
                'key' => 'spare-parts',
                'title' => 'Spare Parts',
                'items' => $this->dedupeItems([
                    [
                        'key' => 'parts.supplier-catalog',
                        'label' => 'Supplier Catalog',
                        'icon' => 'isax-shop',
                        'route' => 'automotive.admin.modules.supplier-catalog',
                        'params' => $query,
                        'pages' => ['supplier-catalog'],
                    ],
                    [
                        'key' => 'parts.stock-items',
                        'label' => 'Stock Items',
                        'icon' => 'isax-box5',
                        'route' => 'automotive.admin.products.index',
                        'params' => $query,
                        'pages' => ['products'],
                    ],
                    [
                        'key' => 'parts.inventory-adjustments',
                        'label' => 'Inventory Adjustments',
                        'icon' => 'isax-arrow-right-3',
                        'route' => 'automotive.admin.inventory-adjustments.index',
                        'params' => $query,
                        'pages' => ['inventory-adjustments'],
                    ],
                    [
                        'key' => 'parts.stock-transfers',
                        'label' => 'Stock Transfers',
                        'icon' => 'isax-arrow-right-35',
                        'route' => 'automotive.admin.stock-transfers.index',
                        'params' => $query,
                        'pages' => ['stock-transfers'],
                    ],
                    [
                        'key' => 'parts.inventory-report',
                        'label' => 'Inventory Report',
                        'icon' => 'isax-chart-35',
                        'route' => 'automotive.admin.inventory-report.index',
                        'params' => $query,
                        'pages' => ['inventory-report'],
                    ],
                    [
                        'key' => 'parts.stock-movements',
                        'label' => 'Stock Movement Report',
                        'icon' => 'isax-arrow-3',
                        'route' => 'automotive.admin.stock-movements.index',
                        'params' => $query,
                        'pages' => ['stock-movements'],
                    ],
                ]),
            ],
            'accounting' => [
                'key' => 'accounting',
                'title' => 'Accounting',
                'items' => $this->dedupeItems([
                    [
                        'key' => 'accounting.general-ledger',
                        'label' => 'General Ledger',
                        'icon' => 'isax-wallet-3',
                        'route' => 'automotive.admin.modules.general-ledger',
                        'params' => $query,
                        'pages' => ['general-ledger'],
                    ],
                ]),
            ],
            default => null,
        };
    }

    protected function dedupeItems(array $items): array
    {
        return collect($items)
            ->filter(fn (array $item) => trim((string) ($item['key'] ?? '')) !== '')
            ->unique('key')
            ->values()
            ->all();
    }

}
