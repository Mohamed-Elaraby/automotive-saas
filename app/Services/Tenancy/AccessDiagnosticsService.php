<?php

namespace App\Services\Tenancy;

use App\Models\ProductPermission;
use App\Models\TenantProductBranch;
use App\Models\TenantProductSubscription;
use App\Models\TenantUserProductAccess;
use App\Models\TenantUserProductBranch;
use App\Models\TenantUserProductRole;
use App\Models\User;
use Illuminate\Support\Facades\Route;

class AccessDiagnosticsService
{
    public function __construct(
        protected ProductEntitlementService $entitlements,
        protected TenantUserProductAccessService $productAccess,
        protected ProductBranchAccessService $branchAccess,
        protected ProductPermissionService $permissions,
        protected WorkspaceOwnerAccessService $ownerAccess,
        protected BranchContextService $branchContext
    ) {
    }

    public function diagnoseUserAccess(User $user): array
    {
        $products = TenantProductSubscription::query()
            ->where('tenant_id', $this->tenantId())
            ->get()
            ->map(fn (TenantProductSubscription $subscription): array => $this->diagnoseProductAccess(
                $user,
                (string) ($subscription->product_key ?: $subscription->product?->code)
            ));

        return [
            'user' => $user,
            'owner' => $this->ownerAccess->isWorkspaceOwner($user),
            'products' => $products->all(),
            'final' => [
                'allowed' => $products->contains(fn (array $row): bool => (bool) $row['final']['allowed']),
                'reason_code' => $products->isEmpty() ? 'missing_product_access' : 'allowed',
                'message' => $products->isEmpty() ? 'No subscribed products were found for this tenant.' : 'User access was diagnosed across subscribed products.',
            ],
        ];
    }

    public function diagnoseProductAccess(User $user, string $productKey): array
    {
        $subscription = $this->subscription($productKey);
        $explicitAccess = TenantUserProductAccess::query()
            ->where('tenant_id', $this->tenantId())
            ->where('user_id', $user->id)
            ->where('product_key', $productKey)
            ->first();
        $ownerImplicit = $this->ownerAccess->hasImplicitProductAccess($user, $productKey, $this->tenantId());
        $subscribed = $this->entitlements->isSubscribed($this->tenantId(), $productKey);
        $hasAccess = $this->productAccess->hasAccess($user, $productKey, $this->tenantId());

        $reason = match (true) {
            $ownerImplicit => 'owner_implicit_access',
            ! $subscribed => 'inactive_subscription',
            ! $explicitAccess => 'missing_product_access',
            $explicitAccess->status === 'revoked' => 'revoked_product_access',
            $hasAccess => 'allowed',
            default => 'missing_product_access',
        };

        return [
            'product_key' => $productKey,
            'subscription' => [
                'exists' => (bool) $subscription,
                'status' => $subscription?->status,
                'active_or_trialing' => $subscribed,
            ],
            'product_access' => [
                'exists' => (bool) $explicitAccess,
                'status' => $explicitAccess?->status,
                'source' => $explicitAccess?->metadata['access_source'] ?? $explicitAccess?->metadata['source'] ?? null,
            ],
            'owner_access' => [
                'implicit' => $ownerImplicit,
            ],
            'final' => $this->decision($hasAccess, $reason, $this->messageFor($reason), $this->fixFor($reason)),
        ];
    }

    public function diagnoseBranchAccess(User $user, string $productKey, ?int $branchId): array
    {
        $context = $this->branchContext->contextForUser($user, $productKey);
        $enabled = $branchId ? $this->branchAccess->isBranchEnabled($branchId, $productKey, $this->tenantId()) : false;
        $assignment = $branchId
            ? TenantUserProductBranch::query()
                ->where('tenant_id', $this->tenantId())
                ->where('user_id', $user->id)
                ->where('product_key', $productKey)
                ->where('branch_id', $branchId)
                ->first()
            : null;
        $ownerImplicit = $this->ownerAccess->hasImplicitProductAccess($user, $productKey, $this->tenantId());
        $allowed = $branchId
            ? collect($context['allowed_branches'] ?? [])->contains(fn ($branch): bool => (int) $branch->id === (int) $branchId)
            : ! ($context['has_no_branch_access'] ?? true);

        $reason = match (true) {
            $ownerImplicit && $allowed => 'owner_implicit_access',
            ! $branchId && ($context['current_branch_id'] ?? null) === null => 'current_branch_missing',
            $branchId && ! $enabled => 'branch_disabled_for_product',
            $branchId && ! $assignment && ! $ownerImplicit => 'missing_branch_access',
            $allowed => 'allowed',
            default => 'missing_branch_access',
        };

        return [
            'product_key' => $productKey,
            'branch_id' => $branchId,
            'current_branch_id' => $context['current_branch_id'] ?? null,
            'branch_enabled_for_product' => $enabled,
            'branch_assignment' => [
                'exists' => (bool) $assignment,
                'is_enabled' => (bool) ($assignment?->is_enabled ?? false),
            ],
            'owner_access' => [
                'implicit' => $ownerImplicit,
            ],
            'final' => $this->decision($allowed, $reason, $this->messageFor($reason), $this->fixFor($reason)),
        ];
    }

    public function diagnosePermission(User $user, string $productKey, string $permissionKey, ?int $branchId = null): array
    {
        $product = $this->diagnoseProductAccess($user, $productKey);
        $branch = $branchId ? $this->diagnoseBranchAccess($user, $productKey, $branchId) : null;
        $permission = ProductPermission::query()
            ->where('tenant_id', $this->tenantId())
            ->where('product_key', $productKey)
            ->where('permission_key', $permissionKey)
            ->first();
        $roles = TenantUserProductRole::query()
            ->with('role.permissions')
            ->where('tenant_id', $this->tenantId())
            ->where('user_id', $user->id)
            ->where('product_key', $productKey)
            ->active()
            ->get();
        $grantingRoles = $roles
            ->filter(fn (TenantUserProductRole $assignment): bool => $assignment->role?->permissions->contains('permission_key', $permissionKey))
            ->map(fn (TenantUserProductRole $assignment): array => [
                'id' => $assignment->role?->id,
                'name' => $assignment->role?->name,
            ])
            ->values()
            ->all();
        $allowed = $this->permissions->can($user, $productKey, $permissionKey, $branchId, $this->tenantId());
        $ownerImplicit = $this->ownerAccess->hasImplicitProductAccess($user, $productKey, $this->tenantId());

        $reason = match (true) {
            $ownerImplicit && $allowed => 'owner_implicit_access',
            ! $product['final']['allowed'] => $product['final']['reason_code'],
            $branch && ! $branch['final']['allowed'] => $branch['final']['reason_code'],
            ! $permission => 'missing_permission',
            $roles->isEmpty() => 'missing_role',
            $grantingRoles !== [] && $allowed => 'permission_granted_by_role',
            $allowed => 'allowed',
            default => 'missing_permission',
        };

        return [
            'product' => $product,
            'branch' => $branch,
            'permission' => [
                'key' => $permissionKey,
                'exists' => (bool) $permission,
                'module' => $permission?->group_key ?? $this->moduleFromPermissionKey($permissionKey),
                'action' => $this->actionFromPermissionKey($permissionKey),
            ],
            'roles' => [
                'assigned_count' => $roles->count(),
                'granting_roles' => $grantingRoles,
            ],
            'owner_access' => [
                'implicit' => $ownerImplicit,
            ],
            'final' => $this->decision($allowed, $reason, $this->messageFor($reason), $this->fixFor($reason)),
        ];
    }

    public function diagnoseRoute(User $user, string $routeName, array $parameters = []): array
    {
        $route = Route::getRoutes()->getByName($routeName);
        $exists = (bool) $route;
        $middleware = $route?->gatherMiddleware() ?? [];
        $permissionMiddleware = collect($middleware)
            ->first(fn (string $entry): bool => str_starts_with($entry, 'tenant.product.permission:'));

        if ($exists && $permissionMiddleware) {
            $arguments = explode(',', substr($permissionMiddleware, strlen('tenant.product.permission:')));
            $productKey = trim((string) ($arguments[0] ?? ''));
            $permissionExpression = trim((string) ($arguments[1] ?? ''));
            $branchMode = trim((string) ($arguments[2] ?? 'optional'));
            $branchId = in_array($branchMode, ['current_branch', 'branch_required'], true)
                ? ($this->branchContext->contextForUser($user, $productKey)['current_branch_id'] ?? null)
                : null;
            $permissionResults = collect(explode('|', $permissionExpression))
                ->map(fn (string $permissionKey): array => $this->diagnosePermission($user, $productKey, trim($permissionKey), $branchId ?: null))
                ->values();
            $allowed = $permissionResults->contains(fn (array $result): bool => (bool) ($result['final']['allowed'] ?? false));
            $primaryResult = $permissionResults->first();
            $reason = $allowed ? 'allowed' : (string) ($primaryResult['final']['reason_code'] ?? 'missing_permission');

            return [
                'route' => [
                    'name' => $routeName,
                    'exists' => true,
                    'uri' => $route->uri(),
                    'methods' => $route->methods(),
                    'middleware' => $middleware,
                ],
                'product' => [
                    'key' => $productKey,
                ],
                'branch' => [
                    'mode' => $branchMode,
                    'current_branch_id' => $branchId,
                ],
                'permission' => [
                    'expression' => $permissionExpression,
                    'results' => $permissionResults->all(),
                ],
                'final' => $this->decision(
                    $allowed,
                    $reason,
                    $allowed ? 'Route permission middleware allows this user.' : $this->messageFor($reason),
                    $allowed ? null : $this->fixFor($reason)
                ),
            ];
        }

        return [
            'route' => [
                'name' => $routeName,
                'exists' => $exists,
                'uri' => $route?->uri(),
                'methods' => $route?->methods() ?? [],
                'middleware' => $middleware,
            ],
            'parameters' => $parameters,
            'final' => $this->decision($exists, $exists ? 'allowed' : 'route_not_found', $exists ? 'Route exists.' : 'Route name was not found.', $exists ? null : 'Check the route name.'),
        ];
    }

    public function explainDeniedAction(User $user, string $productKey, string $permissionKey, ?int $branchId = null): array
    {
        return $this->diagnosePermission($user, $productKey, $permissionKey, $branchId);
    }

    protected function subscription(string $productKey): ?TenantProductSubscription
    {
        return TenantProductSubscription::query()
            ->where('tenant_id', $this->tenantId())
            ->where('product_key', $productKey)
            ->first();
    }

    protected function decision(bool $allowed, string $reason, string $message, ?string $fix = null): array
    {
        return [
            'allowed' => $allowed,
            'reason_code' => $reason,
            'message' => $message,
            'suggested_fix' => $fix,
        ];
    }

    protected function messageFor(string $reason): string
    {
        return match ($reason) {
            'owner_implicit_access' => 'Workspace Owner has implicit access.',
            'missing_product_access' => 'User does not have active product access.',
            'revoked_product_access' => 'Product access exists but is revoked.',
            'inactive_subscription' => 'The product subscription is not active or trialing.',
            'missing_branch_access' => 'User is not assigned to this product branch.',
            'branch_disabled_for_product' => 'Branch is not enabled for this product.',
            'missing_role' => 'User does not have an active role for this product.',
            'missing_permission' => 'Required permission is not granted.',
            'permission_granted_by_role' => 'Permission is granted through an assigned product role.',
            'current_branch_missing' => 'No current branch is selected.',
            'allowed' => 'Access is allowed.',
            default => 'Access is denied.',
        };
    }

    protected function fixFor(string $reason): ?string
    {
        return match ($reason) {
            'missing_product_access', 'revoked_product_access' => 'Grant or restore product access from Access Control > Users.',
            'inactive_subscription' => 'Reactivate the product subscription or plan.',
            'missing_branch_access' => 'Assign the user to the branch or sync owner access.',
            'branch_disabled_for_product' => 'Enable the branch for this product.',
            'missing_role' => 'Assign a product role to the user.',
            'missing_permission' => 'Add the permission to the user role or choose a role that grants it.',
            'current_branch_missing' => 'Select a current branch.',
            default => null,
        };
    }

    protected function tenantId(): string
    {
        return (string) tenant()->id;
    }

    protected function moduleFromPermissionKey(string $permissionKey): string
    {
        $parts = explode('.', $permissionKey);

        return count($parts) > 2 ? implode('.', array_slice($parts, 1, -1)) : $permissionKey;
    }

    protected function actionFromPermissionKey(string $permissionKey): string
    {
        $parts = explode('.', $permissionKey);

        return (string) end($parts);
    }
}
