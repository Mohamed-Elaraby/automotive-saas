<?php

namespace App\Services\Inventory;

use App\Models\Inventory;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryAdjustmentService
{
    public function createMovement(array $data): StockMovement
    {
        return DB::transaction(function () use ($data) {
            $inventory = Inventory::query()->firstOrCreate(
                [
                    'branch_id' => $data['branch_id'],
                    'product_id' => $data['product_id'],
                ],
                [
                    'quantity' => 0,
                ]
            );

            $currentQty = (float) $inventory->quantity;
            $changeQty = (float) $data['quantity'];
            $type = $data['type'];

            if ($type === 'opening' || $type === 'adjustment_in') {
                $inventory->increment('quantity', $changeQty);
            } elseif ($type === 'adjustment_out') {
                if ($currentQty < $changeQty) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Adjustment out quantity exceeds available stock.',
                    ]);
                }

                $inventory->decrement('quantity', $changeQty);
            } else {
                throw ValidationException::withMessages([
                    'type' => 'Invalid movement type.',
                ]);
            }

            return StockMovement::query()->create([
                'branch_id' => $data['branch_id'],
                'product_id' => $data['product_id'],
                'type' => $type,
                'quantity' => $changeQty,
                'reference_type' => null,
                'reference_id' => null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['created_by'] ?? null,
                'movement_date' => now(),
            ]);
        });
    }
}
