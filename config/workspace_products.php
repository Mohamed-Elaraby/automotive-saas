<?php

return [
    'default_family' => 'automotive_service',

    'shared' => [
        'sidebar_section' => [
            'key' => 'shared-workspace',
            'title' => 'Shared Workspace',
            'items' => [
                [
                    'key' => 'shared.dashboard',
                    'label' => 'Dashboard',
                    'icon' => 'isax-element-45',
                    'route' => 'automotive.admin.dashboard',
                    'pages' => ['dashboard'],
                ],
                [
                    'key' => 'shared.users',
                    'label' => 'Users',
                    'icon' => 'isax-profile-2user5',
                    'route' => 'automotive.admin.users.index',
                    'pages' => ['users'],
                ],
                [
                    'key' => 'shared.branches',
                    'label' => 'Branches',
                    'icon' => 'isax-buildings-25',
                    'route' => 'automotive.admin.branches.index',
                    'pages' => ['branches'],
                ],
            ],
        ],
        'quick_create_actions' => [
            [
                'key' => 'shared.new-user',
                'label' => 'New User',
                'icon' => 'isax-user-add',
                'route' => 'automotive.admin.users.create',
            ],
            [
                'key' => 'shared.new-branch',
                'label' => 'New Branch',
                'icon' => 'isax-buildings',
                'route' => 'automotive.admin.branches.create',
            ],
        ],
    ],

    'families' => [
        'automotive_service' => [
            'aliases' => ['automotive', 'service', 'workshop', 'maint'],
            'experience' => [
                'eyebrow' => 'Automotive Service Focus',
                'title' => 'Core workshop and service operations',
                'description' => 'Shared modules are shown once at workspace level. Automotive contributes only service modules, while inventory stays under Spare Parts.',
                'accent' => 'primary',
            ],
            'sidebar_section' => [
                'key' => 'automotive-service',
                'title' => 'Automotive Service',
                'items' => [
                    [
                        'key' => 'service.workshop',
                        'label' => 'Workshop Operations',
                        'icon' => 'isax-car',
                        'route' => 'automotive.admin.modules.workshop-operations',
                        'pages' => ['workshop-operations'],
                    ],
                    [
                        'key' => 'service.maintenance',
                        'label' => 'Maintenance Intake',
                        'icon' => 'isax-clipboard-text',
                        'route' => 'automotive.admin.maintenance.index',
                        'pages' => ['maintenance'],
                    ],
                    [
                        'key' => 'service.work-orders',
                        'label' => 'Work Orders',
                        'icon' => 'isax-note-text',
                        'route' => 'automotive.admin.modules.workshop-work-orders',
                        'pages' => ['workshop-work-orders', 'work-order-show'],
                    ],
                    [
                        'key' => 'service.customers',
                        'label' => 'Customers',
                        'icon' => 'isax-profile-2user',
                        'route' => 'automotive.admin.modules.workshop-customers',
                        'pages' => ['workshop-customers'],
                    ],
                    [
                        'key' => 'service.vehicles',
                        'label' => 'Vehicles',
                        'icon' => 'isax-car',
                        'route' => 'automotive.admin.modules.workshop-vehicles',
                        'pages' => ['workshop-vehicles'],
                    ],
                ],
            ],
            'dashboard_actions' => [
                [
                    'key' => 'service.workshop',
                    'label' => 'Open Workshop',
                    'icon' => 'isax-car',
                    'route' => 'automotive.admin.modules.workshop-operations',
                    'variant' => 'primary',
                ],
                [
                    'key' => 'shared.users',
                    'label' => 'Manage Users',
                    'icon' => 'isax-profile-2user',
                    'route' => 'automotive.admin.users.index',
                    'variant' => 'outline-white',
                ],
                [
                    'key' => 'shared.branches',
                    'label' => 'Manage Branches',
                    'icon' => 'isax-buildings',
                    'route' => 'automotive.admin.branches.index',
                    'variant' => 'outline-white',
                ],
            ],
            'integrations' => [
                [
                    'key' => 'automotive-parts',
                    'requires_family' => 'parts_inventory',
                    'events' => ['work_order.consume_part'],
                    'source_capabilities' => ['workshop.work_order_operations'],
                    'target_capabilities' => ['inventory.stock_movements'],
                    'payload_schema' => [
                        'work_order_id' => 'integer',
                        'stock_item_id' => 'integer',
                        'quantity' => 'decimal',
                    ],
                    'title' => 'Workshop uses spare-parts stock',
                    'description' => 'Service operations can consume stock items, transfers, and inventory visibility from the Spare Parts workspace without duplicating inventory modules inside Automotive.',
                    'target_label' => 'Open Spare Parts',
                    'target_route' => 'automotive.admin.modules.supplier-catalog',
                ],
                [
                    'key' => 'automotive-accounting',
                    'requires_family' => 'accounting',
                    'events' => ['work_order.completed'],
                    'source_capabilities' => ['workshop.work_order_completion'],
                    'target_capabilities' => ['accounting.event_review', 'accounting.journal_posting'],
                    'payload_schema' => [
                        'work_order_id' => 'integer',
                        'labor_amount' => 'decimal',
                        'parts_amount' => 'decimal',
                        'total_amount' => 'decimal',
                    ],
                    'title' => 'Workshop can hand off financial events',
                    'description' => 'Labor, service revenue, and future workshop costs can flow into Accounting instead of living in a separate isolated product.',
                    'target_label' => 'Open Accounting',
                    'target_route' => 'automotive.admin.modules.general-ledger',
                ],
            ],
            'runtime_modules' => [
                'workshop-operations' => [
                    'family' => 'automotive_service',
                    'focus_code' => 'automotive_service',
                    'title' => 'Workshop Operations',
                    'description' => 'Core maintenance and workshop execution flows should live here. This keeps the automotive product limited to service operations only.',
                    'links' => [
                        ['label' => 'Manage Users', 'route' => 'automotive.admin.users.index', 'icon' => 'isax-profile-2user'],
                        ['label' => 'Manage Branches', 'route' => 'automotive.admin.branches.index', 'icon' => 'isax-buildings'],
                    ],
                ],
                'workshop-customers' => [
                    'family' => 'automotive_service',
                    'focus_code' => 'automotive_service',
                    'title' => 'Workshop Customers',
                    'description' => 'Customer records attached to service operations in this workspace.',
                    'links' => [
                        ['label' => 'Open Workshop', 'route' => 'automotive.admin.modules.workshop-operations', 'icon' => 'isax-car'],
                    ],
                ],
                'workshop-vehicles' => [
                    'family' => 'automotive_service',
                    'focus_code' => 'automotive_service',
                    'title' => 'Workshop Vehicles',
                    'description' => 'Vehicle records linked to service history and work orders.',
                    'links' => [
                        ['label' => 'Open Workshop', 'route' => 'automotive.admin.modules.workshop-operations', 'icon' => 'isax-car'],
                    ],
                ],
                'workshop-work-orders' => [
                    'family' => 'automotive_service',
                    'focus_code' => 'automotive_service',
                    'title' => 'Work Orders',
                    'description' => 'All workshop job records with lifecycle status and linked service context.',
                    'links' => [
                        ['label' => 'Open Workshop', 'route' => 'automotive.admin.modules.workshop-operations', 'icon' => 'isax-car'],
                    ],
                ],
            ],
        ],
        'parts_inventory' => [
            'aliases' => ['spare', 'part', 'inventor', 'stock'],
            'experience' => [
                'eyebrow' => 'Spare Parts Focus',
                'title' => 'Inventory and stock movement workspace',
                'description' => 'Shared modules such as users and branches stay global, while spare-parts-specific inventory modules live here once and only once.',
                'accent' => 'warning',
            ],
            'quick_create_actions' => [
                [
                    'key' => 'parts.new-stock-item',
                    'label' => 'New Stock Item',
                    'icon' => 'isax-box-add',
                    'route' => 'automotive.admin.products.create',
                ],
                [
                    'key' => 'parts.inventory-adjustment',
                    'label' => 'Inventory Adjustment',
                    'icon' => 'isax-arrows-swap',
                    'route' => 'automotive.admin.inventory-adjustments.create',
                ],
                [
                    'key' => 'parts.stock-transfer',
                    'label' => 'Stock Transfer',
                    'icon' => 'isax-arrow-right-3',
                    'route' => 'automotive.admin.stock-transfers.create',
                ],
            ],
            'sidebar_section' => [
                'key' => 'spare-parts',
                'title' => 'Spare Parts',
                'items' => [
                    [
                        'key' => 'parts.supplier-catalog',
                        'label' => 'Supplier Catalog',
                        'icon' => 'isax-shop',
                        'route' => 'automotive.admin.modules.supplier-catalog',
                        'pages' => ['supplier-catalog'],
                    ],
                    [
                        'key' => 'parts.stock-items',
                        'label' => 'Stock Items',
                        'icon' => 'isax-box5',
                        'route' => 'automotive.admin.products.index',
                        'pages' => ['products'],
                    ],
                    [
                        'key' => 'parts.inventory-adjustments',
                        'label' => 'Inventory Adjustments',
                        'icon' => 'isax-arrow-right-3',
                        'route' => 'automotive.admin.inventory-adjustments.index',
                        'pages' => ['inventory-adjustments'],
                    ],
                    [
                        'key' => 'parts.stock-transfers',
                        'label' => 'Stock Transfers',
                        'icon' => 'isax-arrow-right-35',
                        'route' => 'automotive.admin.stock-transfers.index',
                        'pages' => ['stock-transfers'],
                    ],
                    [
                        'key' => 'parts.inventory-report',
                        'label' => 'Inventory Report',
                        'icon' => 'isax-chart-35',
                        'route' => 'automotive.admin.inventory-report.index',
                        'pages' => ['inventory-report'],
                    ],
                    [
                        'key' => 'parts.stock-movements',
                        'label' => 'Stock Movement Report',
                        'icon' => 'isax-arrow-3',
                        'route' => 'automotive.admin.stock-movements.index',
                        'pages' => ['stock-movements'],
                    ],
                ],
            ],
            'dashboard_actions' => [
                [
                    'key' => 'parts.add-stock-item',
                    'label' => 'Add Stock Item',
                    'icon' => 'isax-box-add',
                    'route' => 'automotive.admin.products.create',
                    'variant' => 'primary',
                ],
                [
                    'key' => 'parts.adjustment',
                    'label' => 'Adjustment',
                    'icon' => 'isax-arrows-swap',
                    'route' => 'automotive.admin.inventory-adjustments.create',
                    'variant' => 'outline-white',
                ],
                [
                    'key' => 'parts.transfer',
                    'label' => 'Transfer',
                    'icon' => 'isax-arrow-right-3',
                    'route' => 'automotive.admin.stock-transfers.create',
                    'variant' => 'outline-white',
                ],
            ],
            'integrations' => [
                [
                    'key' => 'parts-automotive',
                    'requires_family' => 'automotive_service',
                    'title' => 'Spare parts feed workshop operations',
                    'description' => 'Stock items and supplier-backed inventory remain here, while workshop operations consume those items from the Automotive workspace.',
                    'target_label' => 'Open Workshop',
                    'target_route' => 'automotive.admin.modules.workshop-operations',
                ],
                [
                    'key' => 'parts-accounting',
                    'requires_family' => 'accounting',
                    'events' => ['stock_movement.valued'],
                    'source_capabilities' => ['inventory.stock_movements', 'inventory.valuation'],
                    'target_capabilities' => ['accounting.journal_posting'],
                    'payload_schema' => [
                        'stock_movement_id' => 'integer',
                        'quantity' => 'decimal',
                        'unit_cost' => 'decimal',
                        'valuation_amount' => 'decimal',
                    ],
                    'title' => 'Inventory can flow into accounting',
                    'description' => 'Purchasing, valuation, and stock costs can later be posted into Accounting without duplicating inventory controls there.',
                    'target_label' => 'Open Accounting',
                    'target_route' => 'automotive.admin.modules.general-ledger',
                ],
            ],
            'runtime_modules' => [
                'supplier-catalog' => [
                    'family' => 'parts_inventory',
                    'focus_code' => 'parts_inventory',
                    'title' => 'Supplier Catalog',
                    'description' => 'Spare parts purchasing, supplier references, inventory adjustments, and transfers belong to this product context.',
                    'links' => [
                        ['label' => 'Stock Items', 'route' => 'automotive.admin.products.index', 'icon' => 'isax-box'],
                        ['label' => 'Inventory Report', 'route' => 'automotive.admin.inventory-report.index', 'icon' => 'isax-chart-35'],
                        ['label' => 'Stock Transfers', 'route' => 'automotive.admin.stock-transfers.index', 'icon' => 'isax-arrow-right-3'],
                    ],
                ],
            ],
        ],
        'accounting' => [
            'aliases' => ['account'],
            'experience' => [
                'eyebrow' => 'Accounting Focus',
                'title' => 'Finance workspace foundation',
                'description' => 'Shared modules stay global across the tenant. Accounting contributes only its own finance modules, such as the general ledger.',
                'accent' => 'info',
            ],
            'sidebar_section' => [
                'key' => 'accounting',
                'title' => 'Accounting',
                'items' => [
                    [
                        'key' => 'accounting.general-ledger',
                        'label' => 'General Ledger',
                        'icon' => 'isax-wallet-3',
                        'route' => 'automotive.admin.modules.general-ledger',
                        'pages' => ['general-ledger'],
                    ],
                    [
                        'key' => 'accounting.events',
                        'label' => 'Accounting Events',
                        'icon' => 'isax-note-favorite',
                        'route' => 'automotive.admin.modules.general-ledger',
                        'pages' => ['general-ledger'],
                    ],
                ],
            ],
            'dashboard_actions' => [
                [
                    'key' => 'accounting.general-ledger',
                    'label' => 'Open General Ledger',
                    'icon' => 'isax-wallet-3',
                    'route' => 'automotive.admin.modules.general-ledger',
                    'variant' => 'primary',
                ],
            ],
            'integrations' => [
                [
                    'key' => 'accounting-automotive',
                    'requires_family' => 'automotive_service',
                    'title' => 'Accounting can receive service-side activity',
                    'description' => 'Service revenue, labor, and future workshop costing events can be integrated into accounting flows from the Automotive workspace.',
                    'target_label' => 'Open Workshop',
                    'target_route' => 'automotive.admin.modules.workshop-operations',
                ],
                [
                    'key' => 'accounting-parts',
                    'requires_family' => 'parts_inventory',
                    'title' => 'Accounting can receive stock valuation events',
                    'description' => 'Inventory purchases and stock valuation can be integrated from Spare Parts without forcing duplicate stock modules inside Accounting.',
                    'target_label' => 'Open Spare Parts',
                    'target_route' => 'automotive.admin.modules.supplier-catalog',
                ],
            ],
            'runtime_modules' => [
                'general-ledger' => [
                    'family' => 'accounting',
                    'focus_code' => 'accounting',
                    'title' => 'General Ledger',
                    'description' => 'This is the accounting runtime entry point for ledgers, journals, and future finance modules inside the shared tenant workspace.',
                    'links' => [
                        ['label' => 'Dashboard', 'route' => 'automotive.admin.dashboard', 'icon' => 'isax-element-45'],
                    ],
                ],
            ],
        ],
    ],
];
