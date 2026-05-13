<?php

namespace App\Services\Tenancy;

use App\Models\ProductRole;
use App\Models\TenantUserProductRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UserRoleAssignmentService
{
    public function __construct(
        protected TenantUserProductAccessService $productAccess,
        protected WorkspaceOwnerAccessService $ownerAccess
    ) {
    }

    public function syncUserProductRoles(User $user, array $roleByProduct): void
    {
        DB::transaction(function () use ($user, $roleByProduct): void {
            $tenantId = $this->tenantId();
            $activeProducts = collect($roleByProduct)
                ->keys()
                ->map(fn ($productKey): string => (string) $productKey)
                ->filter()
                ->values();

            foreach ($activeProducts as $productKey) {
                $roleId = (int) ($roleByProduct[$productKey] ?? 0);

                if ($roleId <= 0) {
                    $this->revokeProductRoles($user, $productKey, $tenantId);
                    continue;
                }

                $role = ProductRole::query()
                    ->where('tenant_id', $tenantId)
                    ->whereKey($roleId)
                    ->firstOrFail();

                if ($role->product_key !== $productKey) {
                    throw new RuntimeException(__('access.cannot_assign_role_from_another_product'));
                }

                if (! $this->productAccess->hasAccess($user, $productKey, $tenantId)) {
                    throw new RuntimeException(__('access.cannot_assign_role_without_product_access'));
                }

                $this->revokeProductRoles($user, $productKey, $tenantId);

                TenantUserProductRole::query()->updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'user_id' => $user->id,
                        'product_key' => $productKey,
                        'product_role_id' => $role->id,
                    ],
                    [
                        'is_active' => true,
                        'assigned_at' => now(),
                        'revoked_at' => null,
                        'metadata' => [
                            'assignment_source' => 'access_profile_ui',
                            'single_role_per_product' => true,
                        ],
                    ]
                );
            }

            $this->assertOwnerSafeRoleState($user, $roleByProduct);
        });
    }

    protected function revokeProductRoles(User $user, string $productKey, string $tenantId): void
    {
        TenantUserProductRole::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->where('product_key', $productKey)
            ->active()
            ->update([
                'is_active' => false,
                'revoked_at' => now(),
            ]);
    }

    protected function assertOwnerSafeRoleState(User $user, array $roleByProduct): void
    {
        if (! $this->ownerAccess->isWorkspaceOwner($user)) {
            return;
        }

        $roleId = (int) ($roleByProduct['automotive_service'] ?? 0);

        if ($roleId <= 0) {
            throw new RuntimeException(__('access.cannot_remove_last_owner_access'));
        }

        $hasAccessManagement = ProductRole::query()
            ->whereKey($roleId)
            ->where('tenant_id', $this->tenantId())
            ->where('product_key', 'automotive_service')
            ->whereHas('permissions', function ($query): void {
                $query->whereIn('permission_key', [
                    'automotive_service.access.manage',
                    'automotive_service.access.roles.manage',
                ]);
            })
            ->exists();

        if (! $hasAccessManagement) {
            throw new RuntimeException(__('access.cannot_remove_last_owner_access'));
        }
    }

    protected function tenantId(): string
    {
        return (string) tenant()->id;
    }
}
