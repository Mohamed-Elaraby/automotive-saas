<?php

namespace App\Services\Tenancy;

use App\Models\ProductPermission;
use App\Models\ProductRole;
use App\Models\TenantUserProductRole;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class ProductPermissionService
{
    public function __construct(
        protected ProductEntitlementService $entitlements,
        protected TenantUserProductAccessService $productAccess,
        protected ProductBranchAccessService $branchAccess
    ) {
    }

    public function createRole(string $productKey, string $name, array $attributes = []): ProductRole
    {
        $tenantId = $this->tenantId();
        $productKey = $this->normalizeProductKey($productKey);
        $slug = (string) ($attributes['slug'] ?? Str::slug($name));

        if ($slug === '') {
            throw new InvalidArgumentException('Role slug could not be generated.');
        }

        return ProductRole::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'product_key' => $productKey,
                'slug' => $slug,
            ],
            [
                'name' => $name,
                'description' => $attributes['description'] ?? null,
                'is_system' => (bool) ($attributes['is_system'] ?? false),
                'is_active' => (bool) ($attributes['is_active'] ?? true),
                'metadata' => $attributes['metadata'] ?? null,
            ]
        );
    }

    public function createPermission(string $productKey, string $permissionKey, array $attributes = []): ProductPermission
    {
        $tenantId = $this->tenantId();
        $productKey = $this->normalizeProductKey($productKey);
        $permissionKey = $this->normalizePermissionKey($permissionKey);

        return ProductPermission::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'product_key' => $productKey,
                'permission_key' => $permissionKey,
            ],
            [
                'name' => $attributes['name'] ?? Str::headline(str_replace('.', ' ', $permissionKey)),
                'group_key' => $attributes['group_key'] ?? $this->permissionGroup($permissionKey),
                'description' => $attributes['description'] ?? null,
                'is_system' => (bool) ($attributes['is_system'] ?? false),
                'is_active' => (bool) ($attributes['is_active'] ?? true),
                'metadata' => $attributes['metadata'] ?? null,
            ]
        );
    }

    public function syncRolePermissions(ProductRole|int $role, array $permissionKeys): ProductRole
    {
        $role = $this->resolveRole($role);
        $permissionKeys = collect($permissionKeys)
            ->map(fn (string $permissionKey): string => $this->normalizePermissionKey($permissionKey))
            ->unique()
            ->values();

        $permissionIds = $permissionKeys
            ->map(fn (string $permissionKey): int => $this->createPermission($role->product_key, $permissionKey)->id)
            ->all();

        $role->permissions()->sync($permissionIds);

        return $role->refresh();
    }

    public function assignRole(User|int $user, ProductRole|int $role, array $metadata = []): TenantUserProductRole
    {
        $role = $this->resolveRole($role);
        $userId = $this->resolveUserId($user);

        if (! $this->productAccess->hasAccess($userId, $role->product_key, $role->tenant_id)) {
            throw new RuntimeException("User does not have active product access for [{$role->product_key}].");
        }

        return TenantUserProductRole::query()->updateOrCreate(
            [
                'tenant_id' => $role->tenant_id,
                'user_id' => $userId,
                'product_key' => $role->product_key,
                'product_role_id' => $role->id,
            ],
            [
                'is_active' => true,
                'assigned_at' => now(),
                'revoked_at' => null,
                'metadata' => $metadata,
            ]
        );
    }

    public function revokeRole(User|int $user, ProductRole|int $role): bool
    {
        $role = $this->resolveRole($role);
        $assignment = TenantUserProductRole::query()
            ->where('tenant_id', $role->tenant_id)
            ->where('user_id', $this->resolveUserId($user))
            ->where('product_key', $role->product_key)
            ->where('product_role_id', $role->id)
            ->first();

        if (! $assignment) {
            return false;
        }

        $assignment->update([
            'is_active' => false,
            'revoked_at' => now(),
        ]);

        return true;
    }

    public function can(User|int|null $user, string $productKey, string $permissionKey, ?int $branchId = null, ?string $tenantId = null): bool
    {
        if ($user === null || ! $this->tablesExist()) {
            return false;
        }

        $tenantId = $tenantId ?: $this->tenantId();
        $userId = $this->resolveUserId($user);
        $productKey = $this->normalizeProductKey($productKey);
        $permissionKey = $this->normalizePermissionKey($permissionKey);

        if (! $this->entitlements->isSubscribed($tenantId, $productKey)) {
            return false;
        }

        if (! $this->productAccess->hasAccess($userId, $productKey, $tenantId)) {
            return false;
        }

        if ($branchId !== null && ! $this->hasBranchAccess($userId, $productKey, $branchId, $tenantId)) {
            return false;
        }

        return $this->userPermissionKeys($userId, $productKey, $tenantId)
            ->contains($permissionKey);
    }

    public function userPermissionKeys(User|int $user, string $productKey, ?string $tenantId = null): Collection
    {
        $tenantId = $tenantId ?: $this->tenantId();
        $userId = $this->resolveUserId($user);
        $productKey = $this->normalizeProductKey($productKey);

        return ProductPermission::query()
            ->join('product_role_permission', 'product_role_permission.product_permission_id', '=', 'product_permissions.id')
            ->join('product_roles', 'product_roles.id', '=', 'product_role_permission.product_role_id')
            ->join('tenant_user_product_roles', 'tenant_user_product_roles.product_role_id', '=', 'product_roles.id')
            ->where('tenant_user_product_roles.tenant_id', $tenantId)
            ->where('tenant_user_product_roles.user_id', $userId)
            ->where('tenant_user_product_roles.product_key', $productKey)
            ->where('tenant_user_product_roles.is_active', true)
            ->whereNull('tenant_user_product_roles.revoked_at')
            ->where('product_roles.tenant_id', $tenantId)
            ->where('product_roles.product_key', $productKey)
            ->where('product_roles.is_active', true)
            ->where('product_permissions.tenant_id', $tenantId)
            ->where('product_permissions.product_key', $productKey)
            ->where('product_permissions.is_active', true)
            ->distinct()
            ->pluck('product_permissions.permission_key');
    }

    protected function hasBranchAccess(int $userId, string $productKey, int $branchId, string $tenantId): bool
    {
        return $this->branchAccess
            ->userAllowedBranches($userId, $productKey, $tenantId)
            ->contains(fn ($branch): bool => (int) $branch->id === $branchId);
    }

    protected function resolveRole(ProductRole|int $role): ProductRole
    {
        if ($role instanceof ProductRole) {
            return $role;
        }

        return ProductRole::query()->findOrFail($role);
    }

    protected function resolveUserId(User|int $user): int
    {
        return $user instanceof User ? (int) $user->getKey() : (int) $user;
    }

    protected function tenantId(): string
    {
        $tenant = function_exists('tenant') ? tenant() : null;
        $tenantId = (string) ($tenant?->id ?? '');

        if ($tenantId === '') {
            throw new RuntimeException('Tenant context is required for product permissions.');
        }

        return $tenantId;
    }

    protected function normalizeProductKey(string $productKey): string
    {
        $productKey = trim($productKey);

        if ($productKey === '') {
            throw new InvalidArgumentException('Product key is required.');
        }

        return $productKey;
    }

    protected function normalizePermissionKey(string $permissionKey): string
    {
        $permissionKey = trim($permissionKey);

        if ($permissionKey === '' || ! str_contains($permissionKey, '.')) {
            throw new InvalidArgumentException('Permission key must be product-scoped.');
        }

        return $permissionKey;
    }

    protected function permissionGroup(string $permissionKey): ?string
    {
        $parts = explode('.', $permissionKey);

        return $parts[1] ?? null;
    }

    protected function tablesExist(): bool
    {
        return Schema::hasTable('product_roles')
            && Schema::hasTable('product_permissions')
            && Schema::hasTable('tenant_user_product_roles')
            && Schema::hasTable('product_role_permission');
    }
}
