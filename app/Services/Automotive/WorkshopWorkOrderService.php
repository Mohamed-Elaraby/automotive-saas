<?php

namespace App\Services\Automotive;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\StockMovement;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Models\WorkOrderLine;
use Illuminate\Support\Facades\DB;
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
            ->with(['branch', 'creator', 'customer', 'vehicle'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function getOpenWorkOrders(): Collection
    {
        return WorkOrder::query()
            ->with(['branch', 'customer', 'vehicle'])
            ->whereIn('status', ['open', 'in_progress'])
            ->latest('id')
            ->get();
    }

    public function getCustomers(): Collection
    {
        return Customer::query()
            ->orderBy('name')
            ->get();
    }

    public function getVehicles(): Collection
    {
        return Vehicle::query()
            ->with('customer')
            ->orderByDesc('id')
            ->get();
    }

    public function getWorkOrderById(int $id): ?WorkOrder
    {
        return WorkOrder::query()
            ->with(['branch', 'creator', 'customer', 'vehicle'])
            ->find($id);
    }

    public function getWorkOrderLines(WorkOrder $workOrder): Collection
    {
        return WorkOrderLine::query()
            ->leftJoin('products', 'products.id', '=', 'work_order_lines.product_id')
            ->leftJoin('users', 'users.id', '=', 'work_order_lines.created_by')
            ->where('work_order_id', $workOrder->id)
            ->select([
                'work_order_lines.*',
                'products.sku as product_sku',
                'products.name as product_name',
                'users.name as creator_name',
            ])
            ->latest('id')
            ->get();
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
            'customer_id' => $data['customer_id'] ?? null,
            'vehicle_id' => $data['vehicle_id'] ?? null,
            'work_order_number' => $this->generateWorkOrderNumber(),
            'title' => $data['title'],
            'status' => 'open',
            'opened_at' => now(),
            'closed_at' => null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);
    }

    public function createCustomer(array $data): Customer
    {
        return Customer::query()->create([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
        ]);
    }

    public function createVehicle(array $data): Vehicle
    {
        return Vehicle::query()->create([
            'customer_id' => $data['customer_id'],
            'make' => $data['make'],
            'model' => $data['model'],
            'year' => $data['year'] ?? null,
            'plate_number' => $data['plate_number'] ?? null,
            'vin' => $data['vin'] ?? null,
            'notes' => $data['notes'] ?? null,
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

    public function addLaborLine(WorkOrder $workOrder, array $data): WorkOrderLine
    {
        $quantity = (float) $data['quantity'];
        $unitPrice = (float) $data['unit_price'];

        return WorkOrderLine::query()->create([
            'work_order_id' => $workOrder->id,
            'line_type' => 'labor',
            'product_id' => null,
            'stock_movement_id' => null,
            'description' => $data['description'],
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => round($quantity * $unitPrice, 2),
            'notes' => $data['notes'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);
    }

    public function addPartLineFromMovement(WorkOrder $workOrder, StockMovement $movement): WorkOrderLine
    {
        $product = DB::table('products')
            ->where('id', $movement->product_id)
            ->first();
        $quantity = (float) $movement->quantity;
        $unitPrice = (float) ($product->sale_price ?? 0);

        return WorkOrderLine::query()->updateOrCreate(
            [
                'work_order_id' => $workOrder->id,
                'stock_movement_id' => $movement->id,
            ],
            [
                'line_type' => 'part',
                'product_id' => $movement->product_id,
                'description' => $product->name ?? 'Spare Part',
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => round($quantity * $unitPrice, 2),
                'notes' => $movement->notes,
                'created_by' => $movement->created_by,
            ]
        );
    }

    public function summarize(WorkOrder $workOrder): array
    {
        $lines = WorkOrderLine::query()
            ->where('work_order_id', $workOrder->id)
            ->get();

        $laborSubtotal = (float) $lines->where('line_type', 'labor')->sum('total_price');
        $partsSubtotal = (float) $lines->where('line_type', 'part')->sum('total_price');

        return [
            'labor_subtotal' => round($laborSubtotal, 2),
            'parts_subtotal' => round($partsSubtotal, 2),
            'grand_total' => round($laborSubtotal + $partsSubtotal, 2),
            'lines_count' => $lines->count(),
        ];
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
