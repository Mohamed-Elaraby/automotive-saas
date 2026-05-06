<?php

namespace App\Services\Tenancy;

use App\Models\TenantUserProductAccess;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class TenantUserProductAccessService
{
    public function __construct(
        protected ProductEntitlementService $entitlements
    ) {
    }

    public function grantAccess(User|int $user, string $productKey, User|int|null $grantedBy = null, array $metadata = []): TenantUserProductAccess
    {
        $userId = $this->resolveUserId($user);
        $grantedById = $grantedBy === null ? null : $this->resolveUserId($grantedBy);
        $productKey = $this->normalizeProductKey($productKey);
        $tenantId = $this->tenantId();

        $this->assertCanGrantAccess($userId, $productKey, $tenantId);

        return TenantUserProductAccess::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'product_key' => $productKey,
            ],
            [
                'status' => 'active',
                'granted_by' => $grantedById,
                'granted_at' => now(),
                'revoked_at' => null,
                'metadata' => $metadata,
            ]
        );
    }

    public function revokeAccess(User|int $user, string $productKey): bool
    {
        $access = TenantUserProductAccess::query()
            ->where('tenant_id', $this->tenantId())
            ->where('user_id', $this->resolveUserId($user))
            ->where('product_key', $this->normalizeProductKey($productKey))
            ->first();

        if (! $access) {
            return false;
        }

        $access->update([
            'status' => 'revoked',
            'revoked_at' => now(),
        ]);

        return true;
    }

    public function hasAccess(User|int|null $user, string $productKey, ?string $tenantId = null): bool
    {
        if ($user === null) {
            return false;
        }

        $tenantId = $tenantId ?: $this->tenantId();

        return TenantUserProductAccess::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $this->resolveUserId($user))
            ->where('product_key', $this->normalizeProductKey($productKey))
            ->active()
            ->exists();
    }

    public function countUsedSeats(string $productKey, ?string $tenantId = null): int
    {
        $tenantId = $tenantId ?: $this->tenantId();

        if (! $this->accessTableExists()) {
            return 0;
        }

        return (int) TenantUserProductAccess::query()
            ->where('tenant_id', $tenantId)
            ->where('product_key', $this->normalizeProductKey($productKey))
            ->active()
            ->distinct('user_id')
            ->count('user_id');
    }

    public function availableSeats(string $productKey, ?string $tenantId = null): ?int
    {
        $tenantId = $tenantId ?: $this->tenantId();
        $seatLimit = $this->entitlements->seatLimit($tenantId, $this->normalizeProductKey($productKey));

        if ($seatLimit === null) {
            return null;
        }

        return max(0, $seatLimit - $this->countUsedSeats($productKey, $tenantId));
    }

    public function assertCanGrantAccess(User|int $user, string $productKey, ?string $tenantId = null): void
    {
        $tenantId = $tenantId ?: $this->tenantId();
        $userId = $this->resolveUserId($user);
        $productKey = $this->normalizeProductKey($productKey);

        if (! $this->accessTableExists()) {
            throw new RuntimeException('Product access table is not available for this tenant.');
        }

        if (! $this->entitlements->isSubscribed($tenantId, $productKey)) {
            throw new RuntimeException("Tenant is not actively subscribed to product [{$productKey}].");
        }

        if ($this->hasAccess($userId, $productKey, $tenantId)) {
            return;
        }

        $availableSeats = $this->availableSeats($productKey, $tenantId);

        if ($availableSeats !== null && $availableSeats <= 0) {
            throw new RuntimeException("No available seats for product [{$productKey}].");
        }
    }

    protected function tenantId(): string
    {
        $tenant = function_exists('tenant') ? tenant() : null;
        $tenantId = (string) ($tenant?->id ?? '');

        if ($tenantId === '') {
            throw new RuntimeException('Tenant context is required for product user access.');
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

    protected function accessTableExists(): bool
    {
        return Schema::hasTable('tenant_user_product_access');
    }
}
