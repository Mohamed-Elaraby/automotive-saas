<?php

namespace App\Services\Tenancy;

use App\Models\ProductRole;
use App\Models\TenantProductSubscription;
use App\Models\TenantUserProductRole;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ProductRoleManagementService
{
    public function __construct(
        protected ProductPermissionService $permissions,
        protected ProductPermissionCatalogService $catalog
    ) {
    }

    public function listRoles(array $filters = []): LengthAwarePaginator
    {
        $tenantId = $this->tenantId();

        return ProductRole::query()
            ->where('tenant_id', $tenantId)
            ->withCount('permissions')
            ->withCount(['userAssignments as users_count' => fn (Builder $query) => $query->active()])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('product_key', 'like', "%{$search}%");
                });
            })
            ->when($filters['product_key'] ?? null, fn (Builder $query, string $productKey) => $query->where('product_key', $productKey))
            ->when(($filters['status'] ?? null) !== null && ($filters['status'] ?? '') !== '', function (Builder $query) use ($filters): void {
                $query->where('is_active', $filters['status'] === 'active');
            })
            ->orderBy('product_key')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();
    }

    public function createRole(array $data): ProductRole
    {
        return DB::transaction(function () use ($data): ProductRole {
            $this->ensureUniqueName($data['product_key'], $data['name']);

            $role = ProductRole::query()->create([
                'tenant_id' => $this->tenantId(),
                'product_key' => $data['product_key'],
                'name' => $data['name'],
                'slug' => Str::slug($data['name']),
                'description' => $data['description'] ?? null,
                'is_system' => (bool) ($data['is_system'] ?? false),
                'is_active' => (bool) ($data['is_active'] ?? true),
                'metadata' => [
                    'is_template' => (bool) ($data['is_template'] ?? false),
                    'sort_order' => (int) ($data['sort_order'] ?? 0),
                ],
            ]);

            if (! empty($data['permissions'])) {
                $this->permissions->syncRolePermissions($role, $data['permissions']);
            }

            return $role->refresh();
        });
    }

    public function updateRole(ProductRole $role, array $data): ProductRole
    {
        $this->assertTenantRole($role);

        return DB::transaction(function () use ($role, $data): ProductRole {
            $name = $data['name'];
            $productKey = $role->is_system ? $role->product_key : $data['product_key'];

            $this->ensureUniqueName($productKey, $name, $role->id);

            $metadata = $role->metadata ?? [];
            $metadata['is_template'] = (bool) ($data['is_template'] ?? ($metadata['is_template'] ?? false));
            $metadata['sort_order'] = (int) ($data['sort_order'] ?? ($metadata['sort_order'] ?? 0));

            $role->update([
                'product_key' => $productKey,
                'name' => $role->is_system ? $role->name : $name,
                'slug' => $role->is_system ? $role->slug : Str::slug($name),
                'description' => $data['description'] ?? null,
                'is_active' => $this->isTenantOwnerRole($role) ? true : (bool) ($data['is_active'] ?? true),
                'metadata' => $metadata,
            ]);

            return $role->refresh();
        });
    }

    public function deleteRole(ProductRole $role): void
    {
        $this->assertTenantRole($role);

        if ($this->isTenantOwnerRole($role)) {
            throw new RuntimeException(__('access.tenant_owner_role_cannot_be_deleted'));
        }

        if ($role->is_system) {
            throw new RuntimeException(__('access.system_role_cannot_be_deleted'));
        }

        if ($this->activeAssignmentCount($role) > 0) {
            throw new RuntimeException(__('access.role_assigned_users_cannot_delete'));
        }

        DB::transaction(function () use ($role): void {
            $role->permissions()->detach();
            $role->delete();
        });
    }

    public function duplicateRole(ProductRole $role): ProductRole
    {
        $this->assertTenantRole($role);

        return DB::transaction(function () use ($role): ProductRole {
            $baseName = $role->name . ' Copy';
            $name = $baseName;
            $index = 2;

            while ($this->roleNameExists($role->product_key, $name)) {
                $name = "{$baseName} {$index}";
                $index++;
            }

            $copy = ProductRole::query()->create([
                'tenant_id' => $this->tenantId(),
                'product_key' => $role->product_key,
                'name' => $name,
                'slug' => Str::slug($name),
                'description' => $role->description,
                'is_system' => false,
                'is_active' => true,
                'metadata' => array_merge($role->metadata ?? [], [
                    'is_template' => false,
                    'duplicated_from_role_id' => $role->id,
                ]),
            ]);

            $copy->permissions()->sync($role->permissions()->pluck('product_permissions.id')->all());

            return $copy->refresh();
        });
    }

    public function syncRolePermissions(ProductRole $role, array $permissionKeys): ProductRole
    {
        $this->assertTenantRole($role);

        if ($role->is_active && empty($permissionKeys)) {
            throw new RuntimeException(__('access.active_role_requires_permission'));
        }

        if ($this->isTenantOwnerRole($role)) {
            $required = [
                "{$role->product_key}.access.manage",
                "{$role->product_key}.access.roles.manage",
            ];

            if (collect($required)->diff($permissionKeys)->isNotEmpty()) {
                throw new RuntimeException(__('access.cannot_remove_critical_owner_permissions'));
            }
        }

        return DB::transaction(fn (): ProductRole => $this->permissions->syncRolePermissions($role, $permissionKeys));
    }

    public function groupedPermissionsForProduct(string $productKey): Collection
    {
        return $this->catalog->groupedPermissionsForProduct($productKey);
    }

    public function productOptions(): Collection
    {
        return TenantProductSubscription::query()
            ->with('product')
            ->where('tenant_id', $this->tenantId())
            ->orderBy('product_key')
            ->get()
            ->map(fn (TenantProductSubscription $subscription): array => [
                'key' => (string) ($subscription->product_key ?: $subscription->product?->code),
                'name' => $subscription->product?->name ?: (string) $subscription->product_key,
            ])
            ->filter(fn (array $row): bool => $row['key'] !== '')
            ->values();
    }

    public function activeAssignmentCount(ProductRole $role): int
    {
        return TenantUserProductRole::query()
            ->where('tenant_id', $role->tenant_id)
            ->where('product_role_id', $role->id)
            ->active()
            ->count();
    }

    protected function ensureUniqueName(string $productKey, string $name, ?int $ignoreRoleId = null): void
    {
        if ($this->roleNameExists($productKey, $name, $ignoreRoleId)) {
            throw new RuntimeException(__('access.role_name_duplicate_for_product'));
        }
    }

    protected function roleNameExists(string $productKey, string $name, ?int $ignoreRoleId = null): bool
    {
        return ProductRole::query()
            ->where('tenant_id', $this->tenantId())
            ->where('product_key', $productKey)
            ->where('slug', Str::slug($name))
            ->when($ignoreRoleId, fn (Builder $query) => $query->whereKeyNot($ignoreRoleId))
            ->exists();
    }

    protected function assertTenantRole(ProductRole $role): void
    {
        abort_unless((string) $role->tenant_id === $this->tenantId(), 404);
    }

    protected function isTenantOwnerRole(ProductRole $role): bool
    {
        return $role->slug === 'tenant-owner'
            || ($role->metadata['template_key'] ?? null) === 'tenant_owner'
            || strcasecmp($role->name, 'Tenant Owner') === 0;
    }

    protected function tenantId(): string
    {
        return (string) tenant()->id;
    }
}
