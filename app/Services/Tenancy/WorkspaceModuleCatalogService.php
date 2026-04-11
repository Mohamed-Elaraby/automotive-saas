<?php

namespace App\Services\Tenancy;

class WorkspaceModuleCatalogService
{
    public function workspaceQuery(?array $focusedProduct): array
    {
        $productCode = trim((string) data_get($focusedProduct, 'product_code'));

        return $productCode !== '' ? ['workspace_product' => $productCode] : [];
    }

    public function getQuickCreateActions(?array $focusedProduct): array
    {
        $actions = [
            [
                'label' => 'New User',
                'icon' => 'isax-user-add',
                'route' => 'automotive.admin.users.create',
                'params' => $this->workspaceQuery($focusedProduct),
            ],
            [
                'label' => 'New Branch',
                'icon' => 'isax-buildings',
                'route' => 'automotive.admin.branches.create',
                'params' => $this->workspaceQuery($focusedProduct),
            ],
        ];

        if ($this->isFocusedProduct($focusedProduct, 'parts_inventory')) {
            $actions[] = [
                'label' => 'New Stock Item',
                'icon' => 'isax-box-add',
                'route' => 'automotive.admin.products.create',
                'params' => $this->workspaceQuery($focusedProduct),
            ];
            $actions[] = [
                'label' => 'Inventory Adjustment',
                'icon' => 'isax-arrows-swap',
                'route' => 'automotive.admin.inventory-adjustments.create',
                'params' => $this->workspaceQuery($focusedProduct),
            ];
            $actions[] = [
                'label' => 'Stock Transfer',
                'icon' => 'isax-arrow-right-3',
                'route' => 'automotive.admin.stock-transfers.create',
                'params' => $this->workspaceQuery($focusedProduct),
            ];
        }

        return $actions;
    }

    public function getSidebarSections(?array $focusedProduct): array
    {
        $query = $this->workspaceQuery($focusedProduct);

        $sections = [
            [
                'title' => 'Workspace',
                'items' => [
                    [
                        'label' => 'Dashboard',
                        'icon' => 'isax-element-45',
                        'route' => 'automotive.admin.dashboard',
                        'params' => $query,
                        'pages' => ['dashboard'],
                    ],
                    [
                        'label' => 'Users',
                        'icon' => 'isax-profile-2user5',
                        'route' => 'automotive.admin.users.index',
                        'params' => $query,
                        'pages' => ['users'],
                    ],
                    [
                        'label' => 'Branches',
                        'icon' => 'isax-buildings-25',
                        'route' => 'automotive.admin.branches.index',
                        'params' => $query,
                        'pages' => ['branches'],
                    ],
                    [
                        'label' => 'Plans & Billing',
                        'icon' => 'isax-crown5',
                        'route' => 'automotive.admin.billing.status',
                        'params' => $query,
                        'pages' => ['billing'],
                    ],
                ],
            ],
        ];

        if ($this->isFocusedProduct($focusedProduct, 'automotive_service')) {
            $sections[] = [
                'title' => 'Service Operations',
                'items' => [
                    [
                        'label' => 'Workshop Operations',
                        'icon' => 'isax-car',
                        'route' => 'automotive.admin.modules.workshop-operations',
                        'params' => $query,
                        'pages' => ['workshop-operations'],
                    ],
                ],
            ];
        }

        if ($this->isFocusedProduct($focusedProduct, 'parts_inventory')) {
            $sections[] = [
                'title' => 'Spare Parts & Inventory',
                'items' => [
                    [
                        'label' => 'Supplier Catalog',
                        'icon' => 'isax-shop',
                        'route' => 'automotive.admin.modules.supplier-catalog',
                        'params' => $query,
                        'pages' => ['supplier-catalog'],
                    ],
                    [
                        'label' => 'Stock Items',
                        'icon' => 'isax-box5',
                        'route' => 'automotive.admin.products.index',
                        'params' => $query,
                        'pages' => ['products'],
                    ],
                    [
                        'label' => 'Inventory Adjustments',
                        'icon' => 'isax-arrow-right-3',
                        'route' => 'automotive.admin.inventory-adjustments.index',
                        'params' => $query,
                        'pages' => ['inventory-adjustments'],
                    ],
                    [
                        'label' => 'Stock Transfers',
                        'icon' => 'isax-arrow-right-35',
                        'route' => 'automotive.admin.stock-transfers.index',
                        'params' => $query,
                        'pages' => ['stock-transfers'],
                    ],
                    [
                        'label' => 'Inventory Report',
                        'icon' => 'isax-chart-35',
                        'route' => 'automotive.admin.inventory-report.index',
                        'params' => $query,
                        'pages' => ['inventory-report'],
                    ],
                    [
                        'label' => 'Stock Movement Report',
                        'icon' => 'isax-arrow-3',
                        'route' => 'automotive.admin.stock-movements.index',
                        'params' => $query,
                        'pages' => ['stock-movements'],
                    ],
                ],
            ];
        }

        if ($this->isFocusedProduct($focusedProduct, 'accounting')) {
            $sections[] = [
                'title' => 'Accounting',
                'items' => [
                    [
                        'label' => 'General Ledger',
                        'icon' => 'isax-wallet-3',
                        'route' => 'automotive.admin.modules.general-ledger',
                        'params' => $query,
                        'pages' => ['general-ledger'],
                    ],
                ],
            ];
        }

        return $sections;
    }

    public function getDashboardActions(?array $focusedProduct): array
    {
        $query = $this->workspaceQuery($focusedProduct);

        if ($this->isFocusedProduct($focusedProduct, 'parts_inventory')) {
            return [
                [
                    'label' => 'Add Stock Item',
                    'icon' => 'isax-box-add',
                    'route' => 'automotive.admin.products.create',
                    'params' => $query,
                    'variant' => 'primary',
                ],
                [
                    'label' => 'Adjustment',
                    'icon' => 'isax-arrows-swap',
                    'route' => 'automotive.admin.inventory-adjustments.create',
                    'params' => $query,
                    'variant' => 'outline-white',
                ],
                [
                    'label' => 'Transfer',
                    'icon' => 'isax-arrow-right-3',
                    'route' => 'automotive.admin.stock-transfers.create',
                    'params' => $query,
                    'variant' => 'outline-white',
                ],
            ];
        }

        if ($this->isFocusedProduct($focusedProduct, 'accounting')) {
            return [
                [
                    'label' => 'Open General Ledger',
                    'icon' => 'isax-wallet-3',
                    'route' => 'automotive.admin.modules.general-ledger',
                    'params' => $query,
                    'variant' => 'primary',
                ],
                [
                    'label' => 'Manage Billing',
                    'icon' => 'isax-crown5',
                    'route' => 'automotive.admin.billing.status',
                    'params' => $query,
                    'variant' => 'outline-white',
                ],
            ];
        }

        return [
            [
                'label' => 'Open Workshop',
                'icon' => 'isax-car',
                'route' => 'automotive.admin.modules.workshop-operations',
                'params' => $query,
                'variant' => 'primary',
            ],
            [
                'label' => 'Manage Users',
                'icon' => 'isax-profile-2user',
                'route' => 'automotive.admin.users.index',
                'params' => $query,
                'variant' => 'outline-white',
            ],
            [
                'label' => 'Manage Branches',
                'icon' => 'isax-buildings',
                'route' => 'automotive.admin.branches.index',
                'params' => $query,
                'variant' => 'outline-white',
            ],
        ];
    }

    public function getFocusedProductExperience(?array $focusedProduct): array
    {
        $productCode = trim((string) data_get($focusedProduct, 'product_code'));

        return match ($productCode) {
            'parts_inventory' => [
                'eyebrow' => 'Spare Parts Focus',
                'title' => 'Inventory and stock movement workspace',
                'description' => 'This area owns stock items, adjustments, transfers, and inventory reporting. These modules are no longer treated as part of automotive service itself.',
                'accent' => 'warning',
            ],
            'accounting' => [
                'eyebrow' => 'Accounting Focus',
                'title' => 'Finance workspace foundation',
                'description' => 'This area is reserved for accounting modules such as the general ledger and future financial flows tied to the same tenant workspace.',
                'accent' => 'info',
            ],
            default => [
                'eyebrow' => 'Automotive Service Focus',
                'title' => 'Core workshop and service operations',
                'description' => 'This area should stay limited to maintenance-oriented modules. Inventory and transfer modules are now attached to Spare Parts instead.',
                'accent' => 'primary',
            ],
        };
    }

    protected function isFocusedProduct(?array $focusedProduct, string $productCode): bool
    {
        return trim((string) data_get($focusedProduct, 'product_code')) === $productCode;
    }
}
