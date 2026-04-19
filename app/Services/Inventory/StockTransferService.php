<?php

namespace App\Services\Inventory;

use App\Models\Inventory;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockTransferService
{
    public function postTransfer(StockTransfer $transfer): void
    {
        if ($transfer->status !== 'draft') {
            throw ValidationException::withMessages([
                'transfer' => 'Only draft transfers can be posted.',
            ]);
        }

        if ($transfer->from_branch_id === $transfer->to_branch_id) {
            throw ValidationException::withMessages([
                'transfer' => 'Source and destination branches must be different.',
            ]);
        }

        $transfer->load('items.product');

        DB::transaction(function () use ($transfer) {
            foreach ($transfer->items as $item) {
                $fromInventory = Inventory::query()->firstOrCreate(
                    [
                        'branch_id' => $transfer->from_branch_id,
                        'product_id' => $item->product_id,
                    ],
                    [
                        'quantity' => 0,
                    ]
                );

                if ((float) $fromInventory->quantity < (float) $item->quantity) {
                    throw ValidationException::withMessages([
                        'stock' => "Insufficient stock for product [{$item->product->name}] in source branch.",
                    ]);
                }

                $toInventory = Inventory::query()->firstOrCreate(
                    [
                        'branch_id' => $transfer->to_branch_id,
                        'product_id' => $item->product_id,
                    ],
                    [
                        'quantity' => 0,
                    ]
                );

                $fromInventory->decrement('quantity', $item->quantity);
                $toInventory->increment('quantity', $item->quantity);

                StockMovement::query()->create([
                    'branch_id' => $transfer->from_branch_id,
                    'product_id' => $item->product_id,
                    'type' => 'transfer_out',
                    'quantity' => $item->quantity,
                    'reference_type' => StockTransfer::class,
                    'reference_id' => $transfer->id,
                    'notes' => $transfer->notes,
                    'created_by' => $transfer->created_by,
                    'movement_date' => now(),
                ]);

                StockMovement::query()->create([
                    'branch_id' => $transfer->to_branch_id,
                    'product_id' => $item->product_id,
                    'type' => 'transfer_in',
                    'quantity' => $item->quantity,
                    'reference_type' => StockTransfer::class,
                    'reference_id' => $transfer->id,
                    'notes' => $transfer->notes,
                    'created_by' => $transfer->created_by,
                    'movement_date' => now(),
                ]);
            }

            $transfer->update([
                'status' => 'posted',
                'transfer_date' => now(),
            ]);
        });
    }
}
