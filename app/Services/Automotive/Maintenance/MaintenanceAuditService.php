<?php

namespace App\Services\Automotive\Maintenance;

use App\Models\Maintenance\MaintenanceAuditEntry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class MaintenanceAuditService
{
    public function record(string $action, string $module, array $data = []): MaintenanceAuditEntry
    {
        $auditable = $data['auditable'] ?? null;

        return MaintenanceAuditEntry::query()->create([
            'branch_id' => $data['branch_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'action' => $action,
            'module_code' => $module,
            'auditable_type' => $auditable instanceof Model ? $auditable::class : ($data['auditable_type'] ?? null),
            'auditable_id' => $auditable instanceof Model ? $auditable->getKey() : ($data['auditable_id'] ?? null),
            'old_values' => $data['old_values'] ?? null,
            'new_values' => $data['new_values'] ?? null,
            'ip_address' => $data['ip_address'] ?? request()?->ip(),
            'user_agent' => $data['user_agent'] ?? (string) request()?->userAgent(),
            'created_at' => now(),
        ]);
    }

    public function recent(int $limit = 100): Collection
    {
        return MaintenanceAuditEntry::query()
            ->with(['branch', 'user'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
