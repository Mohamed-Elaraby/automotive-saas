<?php

namespace App\Services\Tenancy;

use App\Models\TenantProductSubscription;
use App\Models\TenantUserProductAccess;
use App\Models\User;

class WorkspaceOwnerAccessService
{
    public function __construct(
        protected ProductEntitlementService $entitlements,
        protected ProductBranchAccessService $branchAccess
    ) {
    }

    public function isWorkspaceOwner(User|int|null $user): bool
    {
        if ($user === null) {
            return false;
        }

        $userId = $user instanceof User ? (int) $user->getKey() : (int) $user;

        return $userId === 1;
    }

    public function ownerConsumesSeat(): bool
    {
        return false;
    }

    public function hasImplicitProductAccess(User|int|null $user, string $productKey, ?string $tenantId = null): bool
    {
        if (! $this->isWorkspaceOwner($user)) {
            return false;
        }

        return $this->entitlements->isSubscribed($tenantId ?: $this->tenantId(), $productKey);
    }

    public function syncOwnerAccess(User $owner): array
    {
        $tenantId = $this->tenantId();
        $productsSynced = 0;
        $branchesSynced = 0;
        $skippedInactiveProducts = 0;

        if (! $this->isWorkspaceOwner($owner)) {
            return [
                'products_synced' => 0,
                'branches_synced' => 0,
                'skipped_inactive_products' => 0,
            ];
        }

        TenantProductSubscription::query()
            ->with(['product'])
            ->where('tenant_id', $tenantId)
            ->orderBy('product_key')
            ->get()
            ->each(function (TenantProductSubscription $subscription) use ($owner, $tenantId, &$productsSynced, &$branchesSynced, &$skippedInactiveProducts): void {
                $productKey = (string) ($subscription->product_key ?: $subscription->product?->code);

                if ($productKey === '' || ! in_array((string) $subscription->status, ['active', 'trialing'], true)) {
                    $skippedInactiveProducts++;

                    return;
                }

                TenantUserProductAccess::query()->updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'user_id' => $owner->id,
                        'product_key' => $productKey,
                    ],
                    [
                        'status' => 'active',
                        'granted_by' => $owner->id,
                        'granted_at' => now(),
                        'revoked_at' => null,
                        'metadata' => [
                            'access_source' => 'owner_sync',
                            'consumes_seat' => false,
                        ],
                    ]
                );
                $productsSynced++;

                $this->branchAccess
                    ->enabledBranchesForProduct($productKey, $tenantId)
                    ->each(function ($branch) use ($owner, $productKey, &$branchesSynced): void {
                        if (! $branch->is_active) {
                            return;
                        }

                        $this->branchAccess->grantUserBranchAccess($owner, $branch, $productKey, 'owner', [
                            'access_source' => 'owner_sync',
                            'implicit_owner_access' => true,
                        ]);
                        $branchesSynced++;
                    });
            });

        return [
            'products_synced' => $productsSynced,
            'branches_synced' => $branchesSynced,
            'skipped_inactive_products' => $skippedInactiveProducts,
        ];
    }

    public function productAccessConsumesSeat(TenantUserProductAccess $access): bool
    {
        $metadata = is_array($access->metadata) ? $access->metadata : [];

        return (bool) ($metadata['consumes_seat'] ?? true);
    }

    protected function tenantId(): string
    {
        $tenant = function_exists('tenant') ? tenant() : null;

        return (string) ($tenant?->id ?? '');
    }
}
