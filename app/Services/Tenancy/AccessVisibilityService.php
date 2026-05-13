<?php

namespace App\Services\Tenancy;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AccessVisibilityService
{
    protected array $actionCache = [];

    protected array $menuCache = [];

    protected array $ownerCache = [];

    public function __construct(
        protected ProductPermissionService $permissions,
        protected WorkspaceOwnerAccessService $ownerAccess,
        protected BranchContextService $branchContext
    ) {
    }

    public function canSeeMenu(User $user, string $menuKey, ?string $productKey = null, ?int $branchId = null): bool
    {
        $menuKey = trim($menuKey);
        $productKey = $this->normalizeProductKey($productKey, $menuKey);
        $cacheKey = implode('|', [(int) $user->getKey(), $menuKey, $productKey, (string) $branchId]);

        if (array_key_exists($cacheKey, $this->menuCache)) {
            return $this->menuCache[$cacheKey];
        }

        if ($this->isOwner($user)) {
            return $this->menuCache[$cacheKey] = true;
        }

        $permissions = $this->menuPermissions($menuKey, $productKey);

        if ($permissions === []) {
            return $this->menuCache[$cacheKey] = true;
        }

        foreach ($permissions as $permissionKey) {
            if ($this->canSeeAction($user, $permissionKey, $productKey, $branchId)) {
                return $this->menuCache[$cacheKey] = true;
            }
        }

        return $this->menuCache[$cacheKey] = false;
    }

    public function canSeeModule(User $user, string $moduleKey, string $productKey, ?int $branchId = null): bool
    {
        foreach ($this->modulePermissionCandidates($moduleKey, $productKey) as $permissionKey) {
            if ($this->canSeeAction($user, $permissionKey, $productKey, $branchId)) {
                return true;
            }
        }

        return false;
    }

    public function canSeeAction(User $user, string $permissionKey, string $productKey, ?int $branchId = null): bool
    {
        $permissionKey = $this->normalizePermissionKey($permissionKey, $productKey);
        $cacheKey = implode('|', [(int) $user->getKey(), $permissionKey, $productKey, (string) $branchId]);

        if (array_key_exists($cacheKey, $this->actionCache)) {
            return $this->actionCache[$cacheKey];
        }

        try {
            if ($this->permissions->can($user, $productKey, $permissionKey, $branchId)) {
                return $this->actionCache[$cacheKey] = true;
            }

            foreach ($this->managerFallbackPermissions($permissionKey, $productKey) as $fallbackPermission) {
                if ($fallbackPermission !== $permissionKey && $this->permissions->can($user, $productKey, $fallbackPermission, $branchId)) {
                    return $this->actionCache[$cacheKey] = true;
                }
            }

            return $this->actionCache[$cacheKey] = false;
        } catch (\Throwable) {
            return $this->actionCache[$cacheKey] = false;
        }
    }

    public function visibleMenusForUser(User $user): array
    {
        return collect($this->knownMenuKeys())
            ->filter(fn (string $menuKey): bool => $this->canSeeMenu($user, $menuKey))
            ->values()
            ->all();
    }

    public function visibleActionsForUser(User $user, array $actions, string $productKey, ?int $branchId = null): array
    {
        return collect($actions)
            ->filter(function (mixed $action) use ($user, $productKey, $branchId): bool {
                $permissionKey = is_array($action) ? (string) ($action['permission'] ?? $action['permission_key'] ?? '') : (string) $action;

                return $permissionKey !== '' && $this->canSeeAction($user, $permissionKey, $productKey, $branchId);
            })
            ->values()
            ->all();
    }

    public function explainHiddenAction(User $user, string $permissionKey, string $productKey, ?int $branchId = null): array
    {
        if ($this->canSeeAction($user, $permissionKey, $productKey, $branchId)) {
            return [
                'hidden' => false,
                'reason' => 'allowed',
                'permission' => $this->normalizePermissionKey($permissionKey, $productKey),
            ];
        }

        return [
            'hidden' => true,
            'reason' => $branchId === null && $this->isBranchScopedPermission($permissionKey)
                ? 'missing_or_unselected_branch_context'
                : 'missing_permission',
            'permission' => $this->normalizePermissionKey($permissionKey, $productKey),
        ];
    }

    public function currentBranchId(User $user, string $productKey): ?int
    {
        try {
            $context = $this->branchContext->contextForUser($user, $productKey);

            return isset($context['current_branch']['id']) ? (int) $context['current_branch']['id'] : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function filterSidebarSections(array $sections, User $user, ?array $focusedProduct = null): array
    {
        $productKey = trim((string) data_get($focusedProduct, 'product_key', 'automotive_service')) ?: 'automotive_service';

        return collect($sections)
            ->map(function (array $section) use ($user, $productKey): array {
                $section['items'] = collect((array) ($section['items'] ?? []))
                    ->filter(fn (array $item): bool => $this->canSeeMenu(
                        $user,
                        (string) ($item['key'] ?? ''),
                        $this->normalizeProductKey($productKey, (string) ($item['key'] ?? ''))
                    ))
                    ->values()
                    ->all();

                return $section;
            })
            ->filter(fn (array $section): bool => ! empty($section['items']))
            ->values()
            ->all();
    }

    public function filterQuickCreateActions(array $actions, User $user, ?array $focusedProduct = null): array
    {
        $productKey = trim((string) data_get($focusedProduct, 'product_key', 'automotive_service')) ?: 'automotive_service';

        return collect($actions)
            ->filter(fn (array $action): bool => $this->canSeeMenu(
                $user,
                (string) ($action['key'] ?? ''),
                $this->normalizeProductKey($productKey, (string) ($action['key'] ?? ''))
            ))
            ->values()
            ->all();
    }

    protected function menuPermissions(string $menuKey, string $productKey): array
    {
        $map = [
            'shared.dashboard' => ["{$productKey}.dashboard.view"],
            'shared.users' => [
                'automotive_service.access.users.view',
                'automotive_service.access.users.manage',
                'automotive_service.access.manage',
            ],
            'shared.branches' => [
                'automotive_service.access.branches.view',
                'automotive_service.access.branches.manage',
                'automotive_service.access.manage',
            ],
            'shared.access-control' => [
                'automotive_service.access.view',
                'automotive_service.access.manage',
                'automotive_service.access.users.view',
                'automotive_service.access.users.manage',
                'automotive_service.access.roles.view',
                'automotive_service.access.roles.manage',
                'automotive_service.access.branches.view',
                'automotive_service.access.branches.manage',
                'automotive_service.products.view',
                'automotive_service.products.manage',
            ],
            'shared.new-user' => ['automotive_service.access.users.create', 'automotive_service.access.users.manage'],
            'shared.new-branch' => ['automotive_service.access.branches.create', 'automotive_service.access.branches.manage'],
            'service.workshop' => [
                'automotive_service.work_orders.view',
                'automotive_service.customers.view',
                'automotive_service.vehicles.view',
                'automotive_service.dashboard.view',
            ],
            'service.maintenance' => [
                'automotive_service.check_ins.view',
                'automotive_service.appointments.view',
                'automotive_service.work_orders.view',
            ],
            'service.work-orders' => ['automotive_service.work_orders.view', 'automotive_service.work_orders.manage'],
            'service.customers' => ['automotive_service.customers.view', 'automotive_service.customers.manage'],
            'service.vehicles' => ['automotive_service.vehicles.view', 'automotive_service.vehicles.manage'],
            'parts.supplier-catalog' => ['parts_inventory.inventory.view', 'parts_inventory.inventory.manage'],
            'parts.stock-items' => ['parts_inventory.inventory.view', 'parts_inventory.inventory.manage'],
            'parts.inventory-adjustments' => ['parts_inventory.stock_adjustments.view', 'parts_inventory.stock_adjustments.manage'],
            'parts.stock-transfers' => ['parts_inventory.stock_transfers.view', 'parts_inventory.stock_transfers.manage'],
            'parts.inventory-report' => ['parts_inventory.reports.view', 'parts_inventory.reports.export'],
            'parts.stock-movements' => ['parts_inventory.reports.view', 'parts_inventory.inventory.view'],
            'parts.new-stock-item' => ['parts_inventory.inventory.create', 'parts_inventory.inventory.manage'],
            'parts.inventory-adjustment' => ['parts_inventory.stock_adjustments.create', 'parts_inventory.stock_adjustments.manage'],
            'parts.stock-transfer' => ['parts_inventory.stock_transfers.create', 'parts_inventory.stock_transfers.manage'],
            'parts.add-stock-item' => ['parts_inventory.inventory.create', 'parts_inventory.inventory.manage'],
            'parts.adjustment' => ['parts_inventory.stock_adjustments.create', 'parts_inventory.stock_adjustments.manage'],
            'parts.transfer' => ['parts_inventory.stock_transfers.create', 'parts_inventory.stock_transfers.manage'],
            'accounting.general-ledger' => ['accounting.reports.view', 'accounting.billing.view', 'accounting.payments.view'],
            'accounting.events' => ['accounting.reports.view', 'accounting.billing.view'],
        ];

        return Arr::get($map, $menuKey, $this->modulePermissionCandidates($menuKey, $productKey));
    }

    protected function modulePermissionCandidates(string $moduleKey, string $productKey): array
    {
        $moduleKey = Str::of($moduleKey)
            ->after('.')
            ->replace('-', '_')
            ->toString();

        if ($moduleKey === '') {
            return [];
        }

        return [
            "{$productKey}.{$moduleKey}.view",
            "{$productKey}.{$moduleKey}.manage",
        ];
    }

    protected function knownMenuKeys(): array
    {
        return [
            'shared.dashboard',
            'shared.users',
            'shared.branches',
            'shared.access-control',
            'service.workshop',
            'service.maintenance',
            'service.work-orders',
            'service.customers',
            'service.vehicles',
        ];
    }

    protected function normalizeProductKey(?string $productKey, string $menuKey = ''): string
    {
        if (str_starts_with($menuKey, 'parts.')) {
            return 'parts_inventory';
        }

        if (str_starts_with($menuKey, 'accounting.')) {
            return 'accounting';
        }

        return trim((string) $productKey) ?: 'automotive_service';
    }

    protected function normalizePermissionKey(string $permissionKey, string $productKey): string
    {
        $permissionKey = trim($permissionKey);

        if (str_starts_with($permissionKey, "{$productKey}.")) {
            return $permissionKey;
        }

        if (Str::substrCount($permissionKey, '.') >= 2) {
            return $permissionKey;
        }

        return "{$productKey}.{$permissionKey}";
    }

    protected function managerFallbackPermissions(string $permissionKey, string $productKey): array
    {
        $relativeKey = Str::after($permissionKey, "{$productKey}.");
        $parts = explode('.', $relativeKey);

        if (count($parts) < 2) {
            return [];
        }

        array_pop($parts);
        $moduleKey = implode('.', $parts);
        return ["{$productKey}.{$moduleKey}.manage"];
    }

    protected function isBranchScopedPermission(string $permissionKey): bool
    {
        return Str::contains($permissionKey, [
            '.work_orders.',
            '.check_ins.',
            '.appointments.',
            '.customers.',
            '.vehicles.',
            '.inventory.',
            '.stock_transfers.',
        ]);
    }

    protected function isOwner(User $user): bool
    {
        $cacheKey = (int) $user->getKey();

        if (! array_key_exists($cacheKey, $this->ownerCache)) {
            $this->ownerCache[$cacheKey] = $this->ownerAccess->isWorkspaceOwner($user);
        }

        return $this->ownerCache[$cacheKey];
    }
}
