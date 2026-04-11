<?php

namespace App\Services\Automotive;

use App\Models\Branch;
use App\Models\StockMovement;
use App\Models\WorkOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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

    public function getWorkOrderById(int $id): ?WorkOrder
    {
        return WorkOrder::query()
            ->with(['branch', 'creator'])
            ->find($id);
    }

    public function getWorkOrderConsumptions(WorkOrder $workOrder): Collection
    {
        return StockMovement::query()
            ->join('products', 'products.id', '=', 'stock_movements.product_id')
            ->join('branches', 'branches.id', '=', 'stock_movements.branch_id')
            ->leftJoin('users', 'users.id', '=', 'stock_movements.created_by')
            ->where('stock_movements.reference_type', WorkOrder::class)
            ->where('stock_movements.reference_id', $workOrder->id)
            ->select([
                'stock_movements.id',
                'stock_movements.quantity',
                'stock_movements.notes',
                'stock_movements.movement_date',
                'products.name as product_name',
                'products.sku as product_sku',
                'branches.name as branch_name',
                'users.name as creator_name',
            ])
            ->latest('stock_movements.id')
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

    public function updateStatus(WorkOrder $workOrder, string $status): WorkOrder
    {
        if (! in_array($status, ['open', 'in_progress', 'completed'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Invalid work order status.',
            ]);
        }

        $workOrder->forceFill([
            'status' => $status,
            'closed_at' => $status === 'completed' ? now() : null,
        ])->save();

        return $workOrder->refresh();
    }

    public function touchInProgress(WorkOrder $workOrder): void
    {
        if ($workOrder->status === 'open') {
            $workOrder->forceFill(['status' => 'in_progress'])->save();
        }
    }

    protected function generateWorkOrderNumber(): string
    {
        return 'WO-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(4));
    }
}
