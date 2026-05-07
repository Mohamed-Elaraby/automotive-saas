<?php

namespace App\Services\Tenancy;

use App\Models\Branch;
use App\Models\TenantUserProductAccess;
use App\Models\User;
use Illuminate\Support\Collection;
use RuntimeException;

class BranchContextService
{
    public const SESSION_PRODUCT_KEY = 'current_product_key';
    public const SESSION_BRANCH_ID = 'current_branch_id';

    public function __construct(
        protected ProductBranchAccessService $branchAccess,
        protected TenantUserProductAccessService $productAccess,
        protected WorkspaceOwnerAccessService $ownerAccess
    ) {
    }

    public function currentProductKey(): ?string
    {
        $productKey = session(self::SESSION_PRODUCT_KEY)
            ?: request()->attributes->get('workspace_product_code')
            ?: request()->query('workspace_product')
            ?: 'automotive_service';

        return is_string($productKey) && trim($productKey) !== '' ? trim($productKey) : null;
    }

    public function currentBranchId(): ?int
    {
        $branchId = session(self::SESSION_BRANCH_ID);

        return $branchId ? (int) $branchId : null;
    }

    public function setCurrentBranch(User|int $user, string $productKey, Branch|int $branch): Branch
    {
        $branchModel = $branch instanceof Branch ? $branch : Branch::query()->findOrFail((int) $branch);
        $productKey = $this->normalizeProductKey($productKey);

        $this->assertUserCanUseBranch($user, $productKey, $branchModel);

        session([
            self::SESSION_PRODUCT_KEY => $productKey,
            self::SESSION_BRANCH_ID => (int) $branchModel->getKey(),
        ]);

        return $branchModel;
    }

    public function clearCurrentBranch(): void
    {
        session()->forget([self::SESSION_PRODUCT_KEY, self::SESSION_BRANCH_ID]);
    }

    public function allowedBranchesForUser(User|int $user, string $productKey): Collection
    {
        if ($this->ownerAccess->hasImplicitProductAccess($user, $productKey, $this->tenantId())) {
            return $this->branchAccess
                ->enabledBranchesForProduct($this->normalizeProductKey($productKey), $this->tenantId())
                ->where('is_active', true)
                ->values();
        }

        return $this->branchAccess->userAllowedBranches($user, $this->normalizeProductKey($productKey), $this->tenantId());
    }

    public function productKeysForUser(User|int $user): Collection
    {
        $query = TenantUserProductAccess::query()
            ->where('tenant_id', $this->tenantId())
            ->where('user_id', $this->resolveUserId($user))
            ->active()
            ->orderBy('product_key')
            ->pluck('product_key');

        if ($this->ownerAccess->isWorkspaceOwner($user)) {
            $ownerProducts = \App\Models\TenantProductSubscription::query()
                ->with(['product'])
                ->where('tenant_id', $this->tenantId())
                ->whereIn('status', ['active', 'trialing'])
                ->get()
                ->map(fn ($subscription): string => (string) ($subscription->product_key ?: $subscription->product?->code))
                ->filter();

            $query = $query->merge($ownerProducts);
        }

        return $query->unique()->values();
    }

    public function autoSelectBranchIfOnlyOne(User|int $user, string $productKey): ?Branch
    {
        $allowedBranches = $this->allowedBranchesForUser($user, $productKey);

        if ($allowedBranches->count() !== 1) {
            return null;
        }

        return $this->setCurrentBranch($user, $productKey, $allowedBranches->first());
    }

    public function assertUserCanUseBranch(User|int $user, string $productKey, Branch|int $branch): void
    {
        $productKey = $this->normalizeProductKey($productKey);

        if (! $this->productAccess->hasAccess($user, $productKey, $this->tenantId())) {
            throw new RuntimeException("User does not have product access for [{$productKey}].");
        }

        $branchId = $branch instanceof Branch ? (int) $branch->getKey() : (int) $branch;
        $allowed = $this->allowedBranchesForUser($user, $productKey)
            ->contains(fn (Branch $allowedBranch): bool => (int) $allowedBranch->getKey() === $branchId);

        if (! $allowed) {
            throw new RuntimeException('User is not allowed to use this branch.');
        }
    }

    public function contextForUser(?User $user, ?string $preferredProductKey = null): array
    {
        if (! $user) {
            return [
                'product_key' => null,
                'current_branch_id' => null,
                'current_branch' => null,
                'products' => collect(),
                'allowed_branches' => collect(),
                'has_product_access' => false,
                'requires_selector' => false,
                'has_no_branch_access' => false,
            ];
        }

        $productKeys = $this->productKeysForUser($user);
        $productKey = $preferredProductKey ?: $this->currentProductKey();

        if (! $productKey || ! $productKeys->contains($productKey)) {
            $productKey = $productKeys->first();
        }

        if (! $productKey) {
            return [
                'product_key' => null,
                'current_branch_id' => null,
                'current_branch' => null,
                'products' => $productKeys,
                'allowed_branches' => collect(),
                'has_product_access' => false,
                'requires_selector' => false,
                'has_no_branch_access' => false,
            ];
        }

        $allowedBranches = $this->allowedBranchesForUser($user, $productKey);

        if ($allowedBranches->count() === 1 && ! $this->currentBranchId()) {
            $this->setCurrentBranch($user, $productKey, $allowedBranches->first());
        }

        $currentBranchId = $this->currentBranchId();
        $currentBranch = $allowedBranches->first(fn (Branch $branch): bool => (int) $branch->getKey() === (int) $currentBranchId);

        if ($currentBranchId && ! $currentBranch) {
            $this->clearCurrentBranch();
            $currentBranchId = null;

            if ($allowedBranches->count() === 1) {
                $currentBranch = $this->setCurrentBranch($user, $productKey, $allowedBranches->first());
                $currentBranchId = (int) $currentBranch->getKey();
            }
        }

        return [
            'product_key' => $productKey,
            'current_branch_id' => $currentBranchId,
            'current_branch' => $currentBranch,
            'products' => $productKeys,
            'allowed_branches' => $allowedBranches,
            'has_product_access' => true,
            'requires_selector' => $allowedBranches->count() > 1 && ! $currentBranch,
            'has_no_branch_access' => $allowedBranches->isEmpty(),
        ];
    }

    protected function tenantId(): string
    {
        $tenant = function_exists('tenant') ? tenant() : null;
        $tenantId = (string) ($tenant?->id ?? '');

        if ($tenantId === '') {
            throw new RuntimeException('Tenant context is required for branch context.');
        }

        return $tenantId;
    }

    protected function resolveUserId(User|int $user): int
    {
        return $user instanceof User ? (int) $user->getKey() : (int) $user;
    }

    protected function normalizeProductKey(string $productKey): string
    {
        $productKey = trim($productKey);

        if ($productKey === '') {
            throw new RuntimeException('Product key is required.');
        }

        return $productKey;
    }
}
