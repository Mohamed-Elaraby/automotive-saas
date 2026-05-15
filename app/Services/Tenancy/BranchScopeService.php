<?php

namespace App\Services\Tenancy;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

class BranchScopeService
{
    public function __construct(
        protected BranchContextService $branchContext,
        protected ProductBranchAccessService $branchAccess,
        protected WorkspaceOwnerAccessService $ownerAccess,
        protected ProductEntitlementService $entitlements,
        protected TenantUserProductAccessService $productAccess
    ) {
    }

    public function allowedBranchIdsForUser(User $user, string $productKey): array
    {
        if (! $this->productAccess->hasAccess($user, $productKey, $this->tenantId())
            && ! $this->ownerAccess->hasImplicitProductAccess($user, $productKey, $this->tenantId())) {
            return [];
        }

        return $this->branchContext
            ->allowedBranchesForUser($user, $productKey)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function visibleBranchIds(User $user, string $productKey): array
    {
        return $this->allowedBranchIdsForUser($user, $productKey);
    }

    public function currentBranchIdForUser(User $user, string $productKey): ?int
    {
        $context = $this->branchContext->contextForUser($user, $productKey);
        $branchId = $context['current_branch_id'] ?? null;

        if (! $branchId) {
            return null;
        }

        return $this->canAccessBranch($user, $productKey, (int) $branchId) ? (int) $branchId : null;
    }

    public function canAccessBranch(User $user, string $productKey, int $branchId): bool
    {
        return in_array($branchId, $this->allowedBranchIdsForUser($user, $productKey), true);
    }

    public function assertCanAccessBranch(User $user, string $productKey, int $branchId): void
    {
        if (! $this->canAccessBranch($user, $productKey, $branchId)) {
            abort(403, 'User is not allowed to access this branch.');
        }
    }

    public function applyAllowedBranches(Builder $query, User $user, string $productKey, string $branchColumn = 'branch_id'): Builder
    {
        $branchIds = $this->allowedBranchIdsForUser($user, $productKey);

        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($branchColumn, $branchIds);
    }

    public function applyAllowedBranchesOrGlobal(Builder $query, User $user, string $productKey, string $branchColumn = 'branch_id'): Builder
    {
        $branchIds = $this->allowedBranchIdsForUser($user, $productKey);

        if ($branchIds === []) {
            return $query->whereNull($branchColumn);
        }

        return $query->where(function (Builder $scoped) use ($branchColumn, $branchIds): void {
            $scoped->whereNull($branchColumn)->orWhereIn($branchColumn, $branchIds);
        });
    }

    public function applyCurrentBranch(Builder $query, User $user, string $productKey, string $branchColumn = 'branch_id'): Builder
    {
        $branchId = $this->currentBranchIdForUser($user, $productKey);

        if (! $branchId) {
            return $this->applyAllowedBranches($query, $user, $productKey, $branchColumn);
        }

        return $query->where($branchColumn, $branchId);
    }

    public function filterRequestedBranch(?int $requestedBranchId, User $user, string $productKey): ?int
    {
        if (! $requestedBranchId) {
            return null;
        }

        $this->assertCanAccessBranch($user, $productKey, $requestedBranchId);

        return $requestedBranchId;
    }

    protected function tenantId(): string
    {
        $tenant = function_exists('tenant') ? tenant() : null;
        $tenantId = (string) ($tenant?->id ?? '');

        if ($tenantId === '') {
            throw new RuntimeException('Tenant context is required for branch scoping.');
        }

        return $tenantId;
    }
}
