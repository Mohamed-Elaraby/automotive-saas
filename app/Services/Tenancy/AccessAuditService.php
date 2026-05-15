<?php

namespace App\Services\Tenancy;

use App\Models\AccessAuditLog;
use App\Models\ProductRole;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AccessAuditService
{
    public function log(array $data): ?AccessAuditLog
    {
        try {
            if (! Schema::hasTable('access_audit_logs')) {
                return null;
            }

            $request = request();
            $subject = $data['subject'] ?? null;

            return AccessAuditLog::query()->create([
                'product_key' => $data['product_key'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
                'actor_user_id' => $data['actor_user_id'] ?? $this->currentActorId(),
                'target_user_id' => $data['target_user_id'] ?? null,
                'subject_type' => $data['subject_type'] ?? ($subject instanceof Model ? $subject::class : null),
                'subject_id' => $data['subject_id'] ?? ($subject instanceof Model ? $subject->getKey() : null),
                'action' => $data['action'],
                'event_key' => $data['event_key'] ?? $data['action'],
                'old_values' => $this->clean($data['old_values'] ?? null),
                'new_values' => $this->clean($data['new_values'] ?? null),
                'metadata' => $this->clean($data['metadata'] ?? null),
                'ip_address' => $data['ip_address'] ?? ($request instanceof Request ? $request->ip() : null),
                'user_agent' => $data['user_agent'] ?? ($request instanceof Request ? (string) $request->userAgent() : null),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Access audit log write failed.', [
                'action' => $data['action'] ?? null,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function logProductAccessGranted(User $target, string $productKey, ?Model $subject = null, array $metadata = []): ?AccessAuditLog
    {
        return $this->log([
            'product_key' => $productKey,
            'target_user_id' => $target->id,
            'subject' => $subject,
            'action' => 'product_access.granted',
            'event_key' => 'product_access.granted',
            'new_values' => ['status' => 'active'],
            'metadata' => $metadata,
        ]);
    }

    public function logProductAccessRevoked(User $target, string $productKey, ?Model $subject = null, array $metadata = []): ?AccessAuditLog
    {
        return $this->log([
            'product_key' => $productKey,
            'target_user_id' => $target->id,
            'subject' => $subject,
            'action' => 'product_access.revoked',
            'event_key' => 'product_access.revoked',
            'old_values' => ['status' => 'active'],
            'new_values' => ['status' => 'revoked'],
            'metadata' => $metadata,
        ]);
    }

    public function logBranchAccessGranted(User $target, string $productKey, int $branchId, array $metadata = []): ?AccessAuditLog
    {
        return $this->log([
            'product_key' => $productKey,
            'branch_id' => $branchId,
            'target_user_id' => $target->id,
            'action' => 'branch_access.granted',
            'event_key' => 'branch_access.granted',
            'new_values' => ['is_enabled' => true],
            'metadata' => $metadata,
        ]);
    }

    public function logBranchAccessRevoked(User $target, string $productKey, int $branchId, array $metadata = []): ?AccessAuditLog
    {
        return $this->log([
            'product_key' => $productKey,
            'branch_id' => $branchId,
            'target_user_id' => $target->id,
            'action' => 'branch_access.revoked',
            'event_key' => 'branch_access.revoked',
            'old_values' => ['is_enabled' => true],
            'new_values' => ['is_enabled' => false],
            'metadata' => $metadata,
        ]);
    }

    public function logRoleAssigned(User $target, ProductRole $role, array $metadata = []): ?AccessAuditLog
    {
        return $this->log([
            'product_key' => $role->product_key,
            'target_user_id' => $target->id,
            'subject' => $role,
            'action' => 'role.assigned',
            'event_key' => 'role.assigned',
            'new_values' => ['role_id' => $role->id, 'role_name' => $role->name],
            'metadata' => $metadata,
        ]);
    }

    public function logRoleRemoved(User $target, string $productKey, ?int $roleId = null, array $metadata = []): ?AccessAuditLog
    {
        return $this->log([
            'product_key' => $productKey,
            'target_user_id' => $target->id,
            'action' => 'role.removed',
            'event_key' => 'role.removed',
            'old_values' => ['role_id' => $roleId],
            'metadata' => $metadata,
        ]);
    }

    public function logRoleChanged(string $action, ProductRole $role, array $oldValues = [], array $newValues = [], array $metadata = []): ?AccessAuditLog
    {
        return $this->log([
            'product_key' => $role->product_key,
            'subject' => $role,
            'action' => $action,
            'event_key' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => $metadata,
        ]);
    }

    public function logRolePermissionsUpdated(ProductRole $role, array $oldPermissions, array $newPermissions, array $metadata = []): ?AccessAuditLog
    {
        return $this->logRoleChanged('role_permissions.updated', $role, [
            'permissions' => array_values($oldPermissions),
        ], [
            'permissions' => array_values($newPermissions),
        ], $metadata);
    }

    public function logOwnerAccessSynced(User $owner, array $summary): ?AccessAuditLog
    {
        return $this->log([
            'product_key' => 'automotive_service',
            'target_user_id' => $owner->id,
            'subject' => $owner,
            'action' => 'owner_access.synced',
            'event_key' => 'owner_access.synced',
            'new_values' => $summary,
        ]);
    }

    public function logForbiddenAction(User $actor, string $productKey, ?string $permissionKey = null, ?int $branchId = null, array $metadata = []): ?AccessAuditLog
    {
        return $this->log([
            'product_key' => $productKey,
            'branch_id' => $branchId,
            'actor_user_id' => $actor->id,
            'action' => 'forbidden_action.blocked',
            'event_key' => 'forbidden_action.blocked',
            'metadata' => $metadata + ['permission_key' => $permissionKey],
        ]);
    }

    public function recentForUser(User $user, int $limit = 25): Collection
    {
        return AccessAuditLog::query()
            ->with(['actor', 'targetUser', 'branch'])
            ->where('target_user_id', $user->id)
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function recentForActor(User $actor, int $limit = 25): Collection
    {
        return AccessAuditLog::query()
            ->with(['actor', 'targetUser', 'branch'])
            ->where('actor_user_id', $actor->id)
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function recentForProduct(string $productKey, int $limit = 25): Collection
    {
        return AccessAuditLog::query()
            ->with(['actor', 'targetUser', 'branch'])
            ->forProduct($productKey)
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return AccessAuditLog::query()
            ->with(['actor', 'targetUser', 'branch'])
            ->when($filters['actor_user_id'] ?? null, fn ($query, $id) => $query->where('actor_user_id', $id))
            ->when($filters['target_user_id'] ?? null, fn ($query, $id) => $query->where('target_user_id', $id))
            ->when($filters['product_key'] ?? null, fn ($query, $productKey) => $query->where('product_key', $productKey))
            ->when($filters['branch_id'] ?? null, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['event_key'] ?? null, fn ($query, $eventKey) => $query->where('event_key', $eventKey))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function summarize(array $filters = []): array
    {
        $query = AccessAuditLog::query()
            ->when($filters['actor_user_id'] ?? null, fn ($query, $id) => $query->where('actor_user_id', $id))
            ->when($filters['target_user_id'] ?? null, fn ($query, $id) => $query->where('target_user_id', $id))
            ->when($filters['product_key'] ?? null, fn ($query, $productKey) => $query->where('product_key', $productKey))
            ->when($filters['branch_id'] ?? null, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['event_key'] ?? null, fn ($query, $eventKey) => $query->where('event_key', $eventKey))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date));

        return [
            'total' => (clone $query)->count(),
            'forbidden' => (clone $query)->where('event_key', 'forbidden_action.blocked')->count(),
            'role_changes' => (clone $query)->where('event_key', 'like', 'role%')->count(),
            'product_access_changes' => (clone $query)->where('event_key', 'like', 'product_access%')->count(),
        ];
    }

    protected function currentActorId(): ?int
    {
        return auth('automotive_admin')->id();
    }

    protected function clean(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        return collect($value)
            ->except(['password', 'token', 'remember_token', 'api_token', 'secret'])
            ->all();
    }
}
