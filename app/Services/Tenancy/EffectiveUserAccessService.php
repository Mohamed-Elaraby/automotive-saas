<?php

namespace App\Services\Tenancy;

use App\Models\Branch;
use App\Models\ProductPermission;
use App\Models\ProductRole;
use App\Models\TenantProductBranch;
use App\Models\TenantProductSubscription;
use App\Models\TenantUserProductAccess;
use App\Models\TenantUserProductBranch;
use App\Models\TenantUserProductRole;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

class EffectiveUserAccessService
{
    public function __construct(
        protected ProductEntitlementService $entitlements,
        protected TenantUserProductAccessService $productAccess,
        protected ProductBranchAccessService $branchAccess,
        protected ProductPermissionCatalogService $catalog,
        protected WorkspaceOwnerAccessService $ownerAccess
    ) {
    }

    public function profile(User $user): array
    {
        $products = $this->effectiveProductsForUser($user);
        $branches = $products->mapWithKeys(fn (array $product): array => [
            $product['product_key'] => $this->effectiveBranchesForUser($user, $product['product_key']),
        ]);
        $roles = $products->mapWithKeys(fn (array $product): array => [
            $product['product_key'] => $this->effectiveRolesForUser($user, $product['product_key']),
        ]);
        $permissions = $products->mapWithKeys(fn (array $product): array => [
            $product['product_key'] => $this->effectivePermissionsForUser($user, $product['product_key']),
        ]);
        $warnings = $this->accessWarningsForUser($user);

        return [
            'products' => $products,
            'branches' => $branches,
            'roles' => $roles,
            'permissions' => $permissions,
            'warnings' => $warnings,
            'summary' => [
                'product_count' => $products->where('has_access', true)->count(),
                'branch_count' => $branches->flatten(1)->where('has_access', true)->count(),
                'role_count' => $roles->flatten(1)->count(),
                'permission_count' => $permissions->flatten(2)->where('granted', true)->count(),
                'warning_count' => $warnings->count(),
                'consumes_seats' => $products->contains(fn (array $row): bool => (bool) $row['consumes_seat']),
            ],
            'is_owner' => $this->ownerAccess->isWorkspaceOwner($user),
        ];
    }

    public function effectiveProductsForUser(User $user): Collection
    {
        $tenantId = $this->tenantId();
        $accessRows = TenantUserProductAccess::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('product_key');

        return $this->subscriptions()
            ->map(function (TenantProductSubscription $subscription) use ($user, $tenantId, $accessRows): array {
                $productKey = (string) ($subscription->product_key ?: $subscription->product?->code);
                $access = $accessRows->get($productKey);
                $isSubscribed = in_array((string) $subscription->status, ['active', 'trialing'], true);
                $isOwner = $this->ownerAccess->isWorkspaceOwner($user);
                $hasExplicitAccess = $access?->status === 'active' && $access?->revoked_at === null;
                $hasImplicitOwnerAccess = $isOwner && $isSubscribed;
                $hasAccess = $hasImplicitOwnerAccess || $hasExplicitAccess;
                $source = $hasImplicitOwnerAccess
                    ? 'implicit_owner'
                    : (string) ($access->metadata['access_source'] ?? ($hasExplicitAccess ? 'manual' : ($access?->status ?? 'none')));
                $limit = $this->entitlements->seatLimit($tenantId, $productKey);
                $used = $this->productAccess->countUsedSeats($productKey, $tenantId);

                return [
                    'product_key' => $productKey,
                    'product_name' => $subscription->product?->name ?? $productKey,
                    'subscription_status' => (string) $subscription->status,
                    'plan_name' => $subscription->plan?->name,
                    'has_access' => $hasAccess,
                    'access_state' => $hasImplicitOwnerAccess ? 'implicit_owner_access' : ($hasExplicitAccess ? 'enabled' : ($access?->status ?: 'no_access')),
                    'access_source' => $source,
                    'explicit_access' => (bool) $hasExplicitAccess,
                    'owner_implicit' => (bool) $hasImplicitOwnerAccess,
                    'consumes_seat' => $access ? $this->ownerAccess->productAccessConsumesSeat($access) : false,
                    'used_seats' => $used,
                    'seat_limit' => $limit,
                    'available_seats' => $limit === null ? null : max(0, $limit - $used),
                    'subscription_active' => $isSubscribed,
                ];
            })
            ->filter(fn (array $row): bool => $row['product_key'] !== '')
            ->values();
    }

    public function effectiveBranchesForUser(User $user, string $productKey): Collection
    {
        $tenantId = $this->tenantId();
        $isOwner = $this->ownerAccess->isWorkspaceOwner($user);
        $enabledIds = TenantProductBranch::query()
            ->where('tenant_id', $tenantId)
            ->where('product_key', $productKey)
            ->enabled()
            ->pluck('branch_id')
            ->map(fn ($id): int => (int) $id);
        $assignedRows = TenantUserProductBranch::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->where('product_key', $productKey)
            ->enabled()
            ->get()
            ->keyBy('branch_id');

        return Branch::query()
            ->whereIn('id', $enabledIds->merge($assignedRows->keys())->unique())
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(function (Branch $branch) use ($isOwner, $enabledIds, $assignedRows): array {
                $assignment = $assignedRows->get($branch->id);
                $productBranchEnabled = $enabledIds->contains((int) $branch->id);
                $ownerImplicit = $isOwner && $productBranchEnabled && (bool) $branch->is_active;

                return [
                    'branch_id' => (int) $branch->id,
                    'branch_name' => $branch->name,
                    'branch_active' => (bool) $branch->is_active,
                    'product_branch_enabled' => $productBranchEnabled,
                    'assigned' => (bool) $assignment,
                    'has_access' => $ownerImplicit || ((bool) $assignment && $productBranchEnabled && (bool) $branch->is_active),
                    'source' => $ownerImplicit ? 'implicit_owner' : (string) ($assignment->metadata['access_source'] ?? ($assignment ? 'manual' : 'none')),
                    'access_level' => $assignment?->access_level,
                    'current_branch_eligible' => $ownerImplicit || ((bool) $assignment && $productBranchEnabled && (bool) $branch->is_active),
                ];
            })
            ->values();
    }

    public function effectiveRolesForUser(User $user, string $productKey): Collection
    {
        return TenantUserProductRole::query()
            ->with(['role' => fn ($query) => $query->withCount('permissions')])
            ->where('tenant_id', $this->tenantId())
            ->where('user_id', $user->id)
            ->where('product_key', $productKey)
            ->active()
            ->get()
            ->filter(fn (TenantUserProductRole $assignment): bool => (bool) $assignment->role)
            ->map(fn (TenantUserProductRole $assignment): array => [
                'role_id' => $assignment->role->id,
                'role_name' => $assignment->role->name,
                'product_key' => $assignment->product_key,
                'permissions_count' => $assignment->role->permissions_count,
                'source' => $assignment->metadata['assignment_source'] ?? 'manual',
                'is_system' => (bool) $assignment->role->is_system,
            ])
            ->values();
    }

    public function effectivePermissionsForUser(User $user, string $productKey, ?int $branchId = null): Collection
    {
        $this->catalog->seedDefaultPermissionsIfMissing($productKey);
        $catalogGroups = $this->catalog->groupedPermissionsForProduct($productKey);
        $roleRows = $this->rolePermissions($user, $productKey);

        return $catalogGroups->map(function (array $group) use ($user, $productKey, $branchId, $roleRows): array {
            return [
                'product_key' => $productKey,
                'module_key' => $group['module_key'],
                'module' => $group['module'],
                'permissions' => $group['permissions']->map(function (array $permission) use ($user, $productKey, $branchId, $roleRows): array {
                    return $this->explainPermission($user, $productKey, $permission['key'], $branchId, $permission, $roleRows);
                })->values(),
            ];
        })->values();
    }

    public function explainPermission(User $user, string $productKey, string $permissionKey, ?int $branchId = null, ?array $definition = null, ?Collection $roleRows = null): array
    {
        $tenantId = $this->tenantId();
        $roleRows = $roleRows ?: $this->rolePermissions($user, $productKey);
        $subscriptionActive = $this->entitlements->isSubscribed($tenantId, $productKey);
        $hasProductAccess = $this->productAccess->hasAccess($user, $productKey, $tenantId);
        $hasBranchAccess = $branchId === null || $this->ownerAccess->isWorkspaceOwner($user) || $this->branchAccess->userAllowedBranches($user, $productKey, $tenantId)
            ->contains(fn (Branch $branch): bool => (int) $branch->id === (int) $branchId);

        if (! $subscriptionActive) {
            $source = 'blocked_inactive_subscription';
            $granted = false;
        } elseif (! $hasProductAccess) {
            $source = 'blocked_no_product_access';
            $granted = false;
        } elseif (! $hasBranchAccess) {
            $source = 'blocked_no_branch_access';
            $granted = false;
        } elseif ($this->ownerAccess->isWorkspaceOwner($user)) {
            $source = 'owner_implicit';
            $granted = true;
        } elseif ($roleRows->has($permissionKey)) {
            $source = 'role';
            $granted = true;
        } else {
            $source = $this->effectiveRolesForUser($user, $productKey)->isEmpty() ? 'blocked_missing_role' : 'blocked_missing_permission';
            $granted = false;
        }

        $parts = explode('.', $permissionKey);

        return [
            'permission_key' => $permissionKey,
            'module' => $definition['module'] ?? $parts[1] ?? 'Other',
            'module_key' => $definition['module_key'] ?? $parts[1] ?? 'other',
            'action' => $definition['action'] ?? collect($parts)->last(),
            'granted' => $granted,
            'source' => $source,
            'role_names' => $roleRows->get($permissionKey, collect())->values()->all(),
        ];
    }

    public function accessWarningsForUser(User $user): Collection
    {
        $tenantId = $this->tenantId();
        $warnings = collect();
        $products = $this->effectiveProductsForUser($user);
        $isOwner = $this->ownerAccess->isWorkspaceOwner($user);

        if (! $isOwner && $products->where('has_access', true)->isEmpty()) {
            $warnings->push($this->warning('warning', null, null, __('access.warning_user_has_no_product_access'), $this->routeUrl('automotive.admin.access.users.products.edit', $user)));
        }

        foreach ($products as $product) {
            $productKey = $product['product_key'];

            if (! $product['subscription_active']) {
                $warnings->push($this->warning('danger', $productKey, null, __('access.warning_subscription_inactive'), null));
            }

            if ($product['has_access']) {
                $branches = $this->effectiveBranchesForUser($user, $productKey);

                if (! $isOwner && $branches->where('has_access', true)->isEmpty()) {
                    $warnings->push($this->warning('warning', $productKey, null, __('access.user_has_product_access_but_no_branch_access'), $this->routeUrl('automotive.admin.access.users.branches.edit', $user)));
                }

                if (! $isOwner && $this->effectiveRolesForUser($user, $productKey)->isEmpty()) {
                    $warnings->push($this->warning('warning', $productKey, null, __('access.user_has_product_access_but_no_role'), $this->routeUrl('automotive.admin.access.users.roles.edit', $user)));
                }
            }
        }

        TenantUserProductRole::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->active()
            ->get()
            ->each(function (TenantUserProductRole $assignment) use ($user, $tenantId, $warnings): void {
                if (! $this->productAccess->hasAccess($user, $assignment->product_key, $tenantId)) {
                    $warnings->push($this->warning('danger', $assignment->product_key, null, __('access.warning_role_without_active_product_access'), $this->routeUrl('automotive.admin.access.users.products.edit', $user)));
                }
            });

        TenantUserProductBranch::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->enabled()
            ->get()
            ->each(function (TenantUserProductBranch $assignment) use ($user, $warnings): void {
                if (! $this->branchAccess->isBranchEnabled($assignment->branch_id, $assignment->product_key, $assignment->tenant_id)) {
                    $warnings->push($this->warning('danger', $assignment->product_key, (int) $assignment->branch_id, __('access.warning_branch_assignment_disabled_product_branch'), $this->routeUrl('automotive.admin.access.users.branches.edit', $user)));
                }
            });

        if ($isOwner) {
            $missingExplicitAccess = $products->contains(fn (array $product): bool => $product['owner_implicit'] && ! $product['explicit_access']);

            if ($missingExplicitAccess) {
                $warnings->push($this->warning('info', null, null, __('access.warning_owner_missing_explicit_sync'), $this->routeUrl('automotive.admin.access.users.show', $user)));
            }
        }

        return $warnings->values();
    }

    public function roleAssignmentRows(User $user): Collection
    {
        $assigned = TenantUserProductRole::query()
            ->where('tenant_id', $this->tenantId())
            ->where('user_id', $user->id)
            ->active()
            ->get()
            ->groupBy('product_key');

        return $this->effectiveProductsForUser($user)
            ->map(function (array $product) use ($assigned): array {
                $roles = ProductRole::query()
                    ->where('tenant_id', $this->tenantId())
                    ->where('product_key', $product['product_key'])
                    ->active()
                    ->withCount('permissions')
                    ->orderBy('name')
                    ->get();

                return [
                    'product' => $product,
                    'available_roles' => $roles,
                    'assigned_role_ids' => $assigned->get($product['product_key'], collect())->pluck('product_role_id')->map(fn ($id): int => (int) $id)->all(),
                ];
            });
    }

    protected function rolePermissions(User $user, string $productKey): Collection
    {
        return ProductPermission::query()
            ->join('product_role_permission', 'product_role_permission.product_permission_id', '=', 'product_permissions.id')
            ->join('product_roles', 'product_roles.id', '=', 'product_role_permission.product_role_id')
            ->join('tenant_user_product_roles', 'tenant_user_product_roles.product_role_id', '=', 'product_roles.id')
            ->where('tenant_user_product_roles.tenant_id', $this->tenantId())
            ->where('tenant_user_product_roles.user_id', $user->id)
            ->where('tenant_user_product_roles.product_key', $productKey)
            ->where('tenant_user_product_roles.is_active', true)
            ->whereNull('tenant_user_product_roles.revoked_at')
            ->where('product_permissions.product_key', $productKey)
            ->where('product_permissions.is_active', true)
            ->select('product_permissions.permission_key', 'product_roles.name as role_name')
            ->get()
            ->groupBy('permission_key')
            ->map(fn (Collection $rows): Collection => $rows->pluck('role_name')->unique()->values());
    }

    protected function warning(string $severity, ?string $productKey, ?int $branchId, string $message, ?string $actionUrl): array
    {
        return [
            'severity' => $severity,
            'product_key' => $productKey,
            'branch_id' => $branchId,
            'message' => $message,
            'suggested_action' => $this->suggestedActionForSeverity($severity),
            'action_url' => $actionUrl,
        ];
    }

    protected function suggestedActionForSeverity(string $severity): string
    {
        return match ($severity) {
            'danger' => __('access.review_required'),
            'warning' => __('access.update_access_assignment'),
            default => __('access.review_access_state'),
        };
    }

    protected function routeUrl(string $routeName, User $user): ?string
    {
        if (! Route::has($routeName)) {
            return null;
        }

        return route($routeName, $user);
    }

    protected function subscriptions(): Collection
    {
        return TenantProductSubscription::query()
            ->with(['product', 'plan'])
            ->where('tenant_id', $this->tenantId())
            ->orderBy('product_key')
            ->get();
    }

    protected function tenantId(): string
    {
        return (string) tenant()->id;
    }
}
