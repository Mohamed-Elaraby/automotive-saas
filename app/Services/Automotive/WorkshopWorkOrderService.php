<?php

namespace App\Services\Automotive;

use App\Models\Branch;
use App\Models\WorkOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class WorkshopWorkOrderService
{
    public function getActiveBranches(): Collection
    {
        return Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function getRecentWorkOrders(int $limit = 8): Collection
    {
        return WorkOrder::query()
            ->with(['branch', 'creator'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function getOpenWorkOrders(): Collection
    {
        return WorkOrder::query()
            ->with('branch')
            ->whereIn('status', ['open', 'in_progress'])
            ->latest('id')
            ->get();
    }

    public function createWorkOrder(array $data): WorkOrder
    {
        return WorkOrder::query()->create([
            'branch_id' => $data['branch_id'],
            'work_order_number' => $this->generateWorkOrderNumber(),
            'title' => $data['title'],
            'status' => 'open',
            'opened_at' => now(),
            'closed_at' => null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);
    }

    protected function generateWorkOrderNumber(): string
    {
        return 'WO-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(4));
    }
}
