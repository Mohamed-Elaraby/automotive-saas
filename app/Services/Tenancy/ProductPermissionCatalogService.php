<?php

namespace App\Services\Tenancy;

use App\Models\ProductPermission;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProductPermissionCatalogService
{
    public const PRODUCT_AUTOMOTIVE = 'automotive_service';

    public function __construct(
        protected ProductPermissionService $permissions
    ) {
    }

    public function seedDefaultPermissionsIfMissing(string $productKey = self::PRODUCT_AUTOMOTIVE): array
    {
        $permissionCount = 0;
        $roleCount = 0;

        foreach ($this->catalog($productKey) as $definition) {
            $permission = $this->permissions->createPermission($productKey, $definition['key'], [
                'name' => $definition['name'],
                'group_key' => $definition['module_key'],
                'description' => $definition['description'] ?? null,
                'is_system' => true,
                'is_active' => true,
                'metadata' => [
                    'module' => $definition['module'],
                    'module_key' => $definition['module_key'],
                    'action' => $definition['action'],
                    'dangerous' => $definition['dangerous'],
                    'catalog' => 'package_13',
                ],
            ]);

            if ($permission->wasRecentlyCreated) {
                $permissionCount++;
            }
        }

        foreach ($this->roleTemplates($productKey) as $template) {
            $role = $this->permissions->createRole($productKey, $template['name'], [
                'description' => $template['description'],
                'is_system' => (bool) $template['is_system'],
                'is_active' => true,
                'metadata' => [
                    'is_template' => true,
                    'template_key' => $template['template_key'],
                    'catalog' => 'package_13',
                ],
            ]);

            if ($role->wasRecentlyCreated) {
                $roleCount++;
            }

            $this->permissions->syncRolePermissions($role, $template['permissions']);
        }

        return [
            'permissions_created' => $permissionCount,
            'roles_created' => $roleCount,
        ];
    }

    public function catalog(string $productKey = self::PRODUCT_AUTOMOTIVE): Collection
    {
        $modules = [
            'dashboard' => ['Dashboard', ['view', 'export']],
            'access' => ['Access Control', ['view', 'manage']],
            'access.users' => ['Users', ['view', 'create', 'edit', 'delete', 'manage', 'assign']],
            'access.roles' => ['Roles', ['view', 'create', 'edit', 'delete', 'manage', 'assign']],
            'access.branches' => ['Branches', ['view', 'create', 'edit', 'delete', 'manage', 'assign', 'switch_branch']],
            'products' => ['Products', ['view', 'manage']],
            'customers' => ['Customers', ['view', 'create', 'edit', 'delete', 'export', 'import']],
            'vehicles' => ['Vehicles', ['view', 'create', 'edit', 'delete', 'export', 'import']],
            'work_orders' => ['Work Orders', ['view', 'create', 'edit', 'delete', 'approve', 'print', 'manage']],
            'check_ins' => ['Check-ins', ['view', 'create', 'edit', 'delete', 'print']],
            'appointments' => ['Appointments', ['view', 'create', 'edit', 'delete', 'manage']],
            'estimates' => ['Estimates', ['view', 'create', 'edit', 'delete', 'approve', 'reject', 'print', 'manage']],
            'invoices' => ['Invoices', ['view', 'create', 'edit', 'delete', 'approve', 'print', 'void', 'manage']],
            'payments' => ['Payments', ['view', 'create', 'edit', 'delete', 'post', 'void', 'reconcile', 'manage']],
            'inventory' => ['Inventory', ['view', 'create', 'edit', 'delete', 'import', 'export', 'manage']],
            'stock_transfers' => ['Stock Transfers', ['view', 'create', 'edit', 'delete', 'approve', 'reject', 'manage']],
            'reports' => ['Reports', ['view', 'export', 'print', 'download']],
            'documents' => ['Documents', ['view', 'create', 'edit', 'delete', 'print', 'download', 'manage']],
            'attachments' => ['Attachments', ['view', 'upload', 'download', 'delete', 'manage']],
            'notifications' => ['Notifications', ['view', 'create', 'edit', 'delete', 'manage']],
            'settings' => ['Settings', ['view', 'edit', 'manage']],
            'billing' => ['Billing', ['view', 'edit', 'manage', 'export']],
            'jobs' => ['Jobs', ['view', 'edit', 'manage']],
            'inspections' => ['Inspections', ['view', 'create', 'edit', 'manage']],
            'stock_adjustments' => ['Stock Adjustments', ['view', 'create', 'approve', 'reject', 'manage']],
        ];

        return collect($modules)->flatMap(function (array $module, string $moduleKey) use ($productKey): array {
            [$moduleName, $actions] = $module;

            return collect($actions)->map(function (string $action) use ($productKey, $moduleKey, $moduleName): array {
                $key = "{$productKey}.{$moduleKey}.{$action}";
                $dangerous = $this->isDangerous($moduleKey, $action);

                return [
                    'key' => $key,
                    'module_key' => $moduleKey,
                    'module' => $moduleName,
                    'action' => $action,
                    'name' => Str::headline("{$moduleName} {$action}"),
                    'description' => Str::headline(str_replace('.', ' ', $key)),
                    'dangerous' => $dangerous,
                ];
            })->all();
        })->values();
    }

    public function roleTemplates(string $productKey = self::PRODUCT_AUTOMOTIVE): array
    {
        $all = $this->catalog($productKey)->pluck('key')->all();
        $readOnly = $this->keysForActions($productKey, ['view']);

        return [
            [
                'template_key' => 'tenant_owner',
                'name' => 'Tenant Owner',
                'description' => 'Workspace owner template with full access-control, product, branch, user, role, billing, and report access.',
                'is_system' => true,
                'permissions' => $all,
            ],
            [
                'template_key' => 'automotive_owner',
                'name' => 'Automotive Owner',
                'description' => 'Full automotive_service access.',
                'is_system' => false,
                'permissions' => $all,
            ],
            [
                'template_key' => 'branch_manager',
                'name' => 'Automotive Branch Manager',
                'description' => 'Branch operations, work orders, estimate approvals, and branch reporting without platform billing.',
                'is_system' => false,
                'permissions' => $this->keysForModules($productKey, ['dashboard', 'access.branches', 'work_orders', 'estimates', 'reports'], ['view', 'create', 'edit', 'approve', 'export', 'manage', 'switch_branch']),
            ],
            [
                'template_key' => 'service_advisor',
                'name' => 'Automotive Service Advisor',
                'description' => 'Customer, vehicle, check-in, work-order, and estimate workflow without delete, billing, or role access.',
                'is_system' => false,
                'permissions' => array_merge(
                    $this->keysForModules($productKey, ['customers', 'vehicles'], ['view', 'create', 'edit']),
                    $this->keysForModules($productKey, ['check_ins'], ['view', 'create', 'edit']),
                    $this->keysForModules($productKey, ['work_orders'], ['view', 'create', 'edit']),
                    $this->keysForModules($productKey, ['estimates'], ['view', 'create', 'edit'])
                ),
            ],
            [
                'template_key' => 'technician',
                'name' => 'Automotive Technician',
                'description' => 'Assigned job and inspection updates plus attachment uploads.',
                'is_system' => false,
                'permissions' => array_merge(
                    $this->keysForModules($productKey, ['jobs', 'inspections'], ['view', 'edit']),
                    $this->keysForModules($productKey, ['attachments'], ['view', 'upload', 'download'])
                ),
            ],
            [
                'template_key' => 'accountant',
                'name' => 'Automotive Accountant',
                'description' => 'Invoice, payment, reconciliation, and finance reporting access.',
                'is_system' => false,
                'permissions' => array_merge(
                    $this->keysForModules($productKey, ['invoices'], ['view', 'create', 'edit', 'print']),
                    $this->keysForModules($productKey, ['payments'], ['view', 'create', 'post', 'reconcile']),
                    $this->keysForModules($productKey, ['reports'], ['view', 'export'])
                ),
            ],
            [
                'template_key' => 'inventory_keeper',
                'name' => 'Automotive Inventory Keeper',
                'description' => 'Inventory and stock-transfer operations without financial reporting.',
                'is_system' => false,
                'permissions' => array_merge(
                    $this->keysForModules($productKey, ['inventory'], ['view', 'create', 'edit', 'manage']),
                    $this->keysForModules($productKey, ['stock_transfers', 'stock_adjustments'], ['view', 'create', 'edit'])
                ),
            ],
            [
                'template_key' => 'viewer',
                'name' => 'Automotive Viewer',
                'description' => 'View-only access without create, edit, delete, approve, or export permissions.',
                'is_system' => false,
                'permissions' => $readOnly,
            ],
        ];
    }

    public function groupedPermissionsForProduct(string $productKey): Collection
    {
        $this->seedDefaultPermissionsIfMissing($productKey);

        $catalog = $this->catalog($productKey)->keyBy('key');

        return ProductPermission::query()
            ->where('tenant_id', (string) tenant()->id)
            ->where('product_key', $productKey)
            ->active()
            ->orderBy('group_key')
            ->orderBy('permission_key')
            ->get()
            ->map(function (ProductPermission $permission) use ($catalog): array {
                $definition = $catalog->get($permission->permission_key);
                $metadata = $permission->metadata ?? [];

                return [
                    'id' => $permission->id,
                    'key' => $permission->permission_key,
                    'name' => $permission->name,
                    'module_key' => $permission->group_key ?: ($metadata['module_key'] ?? 'other'),
                    'module' => $definition['module'] ?? $metadata['module'] ?? Str::headline((string) $permission->group_key),
                    'action' => $definition['action'] ?? $metadata['action'] ?? collect(explode('.', $permission->permission_key))->last(),
                    'dangerous' => (bool) ($definition['dangerous'] ?? $metadata['dangerous'] ?? false),
                ];
            })
            ->groupBy('module_key')
            ->map(function (Collection $permissions): array {
                return [
                    'module_key' => (string) $permissions->first()['module_key'],
                    'module' => (string) $permissions->first()['module'],
                    'permissions' => $permissions->values(),
                ];
            })
            ->values();
    }

    protected function keysForActions(string $productKey, array $actions): array
    {
        return $this->catalog($productKey)
            ->filter(fn (array $permission): bool => in_array($permission['action'], $actions, true))
            ->pluck('key')
            ->all();
    }

    protected function keysForModules(string $productKey, array $modules, array $actions): array
    {
        return $this->catalog($productKey)
            ->filter(fn (array $permission): bool => in_array($permission['module_key'], $modules, true)
                && in_array($permission['action'], $actions, true))
            ->pluck('key')
            ->all();
    }

    protected function isDangerous(string $moduleKey, string $action): bool
    {
        return in_array($action, ['delete', 'void', 'manage', 'reconcile'], true)
            || in_array("{$moduleKey}.{$action}", [
                'access.roles.manage',
                'access.users.delete',
                'billing.manage',
                'access.branches.manage',
            ], true);
    }
}
