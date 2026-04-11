<?php

namespace App\Services\Automotive;

use App\Models\Inventory;
use App\Models\StockMovement;
use App\Services\Tenancy\TenantWorkspaceProductService;
use App\Services\Tenancy\WorkspaceProductFamilyResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkshopPartsIntegrationService
{
    public function __construct(
        protected TenantWorkspaceProductService $tenantWorkspaceProductService,
        protected WorkspaceProductFamilyResolver $workspaceProductFamilyResolver
    ) {
    }

    public function hasConnectedPartsWorkspace(string $tenantId): bool
    {
        $workspaceProducts = $this->tenantWorkspaceProductService->getWorkspaceProducts($tenantId);

        return $workspaceProducts->contains(function (array $workspaceProduct) {
            return $this->workspaceProductFamilyResolver->resolveFromWorkspaceProduct($workspaceProduct) === 'parts_inventory'
                && ! empty($workspaceProduct['is_accessible']);
        });
    }

    public function getAvailableStockSnapshot(int $limit = 12): Collection
    {
        return Inventory::query()
            ->join('products', 'products.id', '=', 'inventories.product_id')
            ->join('branches', 'branches.id', '=', 'inventories.branch_id')
            ->where('inventories.quantity', '>', 0)
            ->select([
                'inventories.id',
                'inventories.branch_id',
                'inventories.product_id',
                'inventories.quantity',
                'products.name as product_name',
                'products.sku as product_sku',
                'products.unit as product_unit',
                'branches.name as branch_name',
                'branches.code as branch_code',
            ])
            ->orderByDesc('inventories.quantity')
            ->limit($limit)
            ->get();
    }

    public function getRecentWorkshopConsumptions(int $limit = 8): Collection
    {
        return StockMovement::query()
            ->join('products', 'products.id', '=', 'stock_movements.product_id')
            ->join('branches', 'branches.id', '=', 'stock_movements.branch_id')
            ->leftJoin('users', 'users.id', '=', 'stock_movements.created_by')
            ->where('stock_movements.type', 'adjustment_out')
            ->where('stock_movements.reference_type', 'workshop_operation')
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
            ->limit($limit)
            ->get();
    }

    public function consumePart(array $data): StockMovement
    {
        return DB::transaction(function () use ($data) {
            /** @var Inventory|null $inventory */
            $inventory = Inventory::query()
                ->lockForUpdate()
                ->where('branch_id', $data['branch_id'])
                ->where('product_id', $data['product_id'])
                ->first();

            if (! $inventory || (float) $inventory->quantity <= 0) {
                throw ValidationException::withMessages([
                    'product_id' => 'This stock item is not available in the selected branch.',
                ]);
            }

            $requestedQuantity = (float) $data['quantity'];

            if ((float) $inventory->quantity < $requestedQuantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Requested workshop quantity exceeds available stock.',
                ]);
            }

            $inventory->decrement('quantity', $requestedQuantity);

            return StockMovement::query()->create([
                'branch_id' => $data['branch_id'],
                'product_id' => $data['product_id'],
                'type' => 'adjustment_out',
                'quantity' => $requestedQuantity,
                'reference_type' => 'workshop_operation',
                'reference_id' => null,
                'notes' => $data['notes'] ?? 'Consumed by workshop operations',
                'created_by' => $data['created_by'] ?? null,
                'movement_date' => now(),
            ]);
        });
    }
}
