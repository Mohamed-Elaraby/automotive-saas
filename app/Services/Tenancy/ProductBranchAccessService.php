<?php

namespace App\Services\Tenancy;

use App\Models\Branch;
use App\Models\TenantProductBranch;
use App\Models\TenantUserProductBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ProductBranchAccessService
{
    public function __construct(
        protected ProductEntitlementService $entitlements
    ) {
    }

    public function enableBranch(Branch|int $branch, string $productKey, array $metadata = []): TenantProductBranch
    {
        $branchId = $this->resolveBranchId($branch);
        $productKey = $this->normalizeProductKey($productKey);
        $tenantId = $this->tenantId();

        $this->assertCanEnableBranch($branchId, $productKey, $tenantId);

        return TenantProductBranch::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'product_key' => $productKey,
                'branch_id' => $branchId,
            ],
            [
                'is_enabled' => true,
                'activated_at' => now(),
                'deactivated_at' => null,
                'metadata' => $metadata,
            ]
        );
    }

    public function disableBranch(Branch|int $branch, string $productKey): bool
    {
        $activation = TenantProductBranch::query()
            ->where('tenant_id', $this->tenantId())
            ->where('product_key', $this->normalizeProductKey($productKey))
            ->where('branch_id', $this->resolveBranchId($branch))
            ->first();

        if (! $activation) {
            return false;
        }

        $activation->update([
            'is_enabled' => false,
            'deactivated_at' => now(),
        ]);

        return true;
    }

    public function grantUserBranchAccess(User|int $user, Branch|int $branch, string $productKey, string $accessLevel = 'member', array $metadata = []): TenantUserProductBranch
    {
        $branchId = $this->resolveBranchId($branch);
        $userId = $this->resolveUserId($user);
        $productKey = $this->normalizeProductKey($productKey);
        $tenantId = $this->tenantId();

        if (! $this->isBranchEnabled($branchId, $productKey, $tenantId)) {
            throw new RuntimeException("Branch is not enabled for product [{$productKey}].");
        }

        return TenantUserProductBranch::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'product_key' => $productKey,
                'branch_id' => $branchId,
            ],
            [
                'access_level' => $accessLevel,
                'is_enabled' => true,
                'granted_at' => now(),
                'revoked_at' => null,
                'metadata' => $metadata,
            ]
        );
    }

    public function userAllowedBranches(User|int $user, string $productKey, ?string $tenantId = null): Collection
    {
        $tenantId = $tenantId ?: $this->tenantId();

        return Branch::query()
            ->whereIn('id', TenantUserProductBranch::query()
                ->where('tenant_id', $tenantId)
                ->where('user_id', $this->resolveUserId($user))
                ->where('product_key', $this->normalizeProductKey($productKey))
                ->enabled()
                ->select('branch_id'))
            ->whereIn('id', TenantProductBranch::query()
                ->where('tenant_id', $tenantId)
                ->where('product_key', $this->normalizeProductKey($productKey))
                ->enabled()
                ->select('branch_id'))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function countEnabledBranches(string $productKey, ?string $tenantId = null): int
    {
        $tenantId = $tenantId ?: $this->tenantId();

        if (! Schema::hasTable('tenant_product_branches')) {
            return 0;
        }

        return (int) TenantProductBranch::query()
            ->where('tenant_id', $tenantId)
            ->where('product_key', $this->normalizeProductKey($productKey))
            ->enabled()
            ->count();
    }

    public function availableBranches(string $productKey, ?string $tenantId = null): ?int
    {
        $tenantId = $tenantId ?: $this->tenantId();
        $branchLimit = $this->entitlements->branchLimit($tenantId, $this->normalizeProductKey($productKey));

        if ($branchLimit === null) {
            return null;
        }

        return max(0, $branchLimit - $this->countEnabledBranches($productKey, $tenantId));
    }

    public function assertCanEnableBranch(Branch|int $branch, string $productKey, ?string $tenantId = null): void
    {
        $tenantId = $tenantId ?: $this->tenantId();
        $branchId = $this->resolveBranchId($branch);
        $productKey = $this->normalizeProductKey($productKey);

        if (! Schema::hasTable('tenant_product_branches')) {
            throw new RuntimeException('Product branch activation table is not available for this tenant.');
        }

        if (! $this->entitlements->isSubscribed($tenantId, $productKey)) {
            throw new RuntimeException("Tenant is not actively subscribed to product [{$productKey}].");
        }

        if ($this->isBranchEnabled($branchId, $productKey, $tenantId)) {
            return;
        }

        $availableBranches = $this->availableBranches($productKey, $tenantId);

        if ($availableBranches !== null && $availableBranches <= 0) {
            throw new RuntimeException("No available branches for product [{$productKey}].");
        }
    }

    public function isBranchEnabled(Branch|int $branch, string $productKey, ?string $tenantId = null): bool
    {
        $tenantId = $tenantId ?: $this->tenantId();

        return TenantProductBranch::query()
            ->where('tenant_id', $tenantId)
            ->where('product_key', $this->normalizeProductKey($productKey))
            ->where('branch_id', $this->resolveBranchId($branch))
            ->enabled()
            ->exists();
    }

    protected function tenantId(): string
    {
        $tenant = function_exists('tenant') ? tenant() : null;
        $tenantId = (string) ($tenant?->id ?? '');

        if ($tenantId === '') {
            throw new RuntimeException('Tenant context is required for product branch access.');
        }

        return $tenantId;
    }

    protected function resolveUserId(User|int $user): int
    {
        return $user instanceof User ? (int) $user->getKey() : (int) $user;
    }

    protected function resolveBranchId(Branch|int $branch): int
    {
        return $branch instanceof Branch ? (int) $branch->getKey() : (int) $branch;
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
