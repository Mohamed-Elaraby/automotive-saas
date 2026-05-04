<?php

namespace App\Services\Automotive\Maintenance;

use App\Models\Maintenance\MaintenanceEstimate;
use App\Models\Maintenance\MaintenanceEstimateLine;
use App\Models\Maintenance\MaintenanceServiceCatalogItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EstimateService
{
    public function __construct(protected MaintenanceNumberService $numberService)
    {
    }

    public function recent(int $limit = 25): Collection
    {
        return MaintenanceEstimate::query()
            ->with(['branch', 'customer', 'vehicle', 'lines'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function create(array $data): MaintenanceEstimate
    {
        return DB::transaction(function () use ($data): MaintenanceEstimate {
            $estimate = MaintenanceEstimate::query()->create([
                'estimate_number' => $this->numberService->next('maintenance_estimates', 'estimate_number', 'EST'),
                'branch_id' => $data['branch_id'],
                'customer_id' => $data['customer_id'],
                'vehicle_id' => $data['vehicle_id'],
                'check_in_id' => $data['check_in_id'] ?? null,
                'work_order_id' => $data['work_order_id'] ?? null,
                'status' => 'draft',
                'valid_until' => $data['valid_until'] ?? now()->addDays(7)->toDateString(),
                'expected_delivery_at' => $data['expected_delivery_at'] ?? null,
                'terms' => $data['terms'] ?? null,
                'customer_visible_notes' => $data['customer_visible_notes'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);

            foreach ($data['lines'] ?? [] as $lineData) {
                if (empty($lineData['description']) && empty($lineData['service_catalog_item_id'])) {
                    continue;
                }

                $service = null;
                if (! empty($lineData['service_catalog_item_id'])) {
                    $service = MaintenanceServiceCatalogItem::query()->find($lineData['service_catalog_item_id']);
                }

                $quantity = (float) ($lineData['quantity'] ?? 1);
                $unitPrice = (float) ($lineData['unit_price'] ?? $service?->default_labor_price ?? 0);
                $discount = (float) ($lineData['discount_amount'] ?? 0);
                $tax = (float) ($lineData['tax_amount'] ?? 0);

                MaintenanceEstimateLine::query()->create([
                    'estimate_id' => $estimate->id,
                    'service_catalog_item_id' => $service?->id,
                    'line_type' => $lineData['line_type'] ?? 'labor',
                    'description' => $lineData['description'] ?? $service?->name ?? 'Service',
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $discount,
                    'tax_amount' => $tax,
                    'total_price' => round(($quantity * $unitPrice) - $discount + $tax, 2),
                    'notes' => $lineData['notes'] ?? null,
                ]);
            }

            $this->recalculate($estimate);

            return $estimate->load(['branch', 'customer', 'vehicle', 'lines']);
        });
    }

    public function recalculate(MaintenanceEstimate $estimate): MaintenanceEstimate
    {
        $lines = $estimate->lines()->get();

        $subtotal = (float) $lines->sum(fn (MaintenanceEstimateLine $line) => (float) $line->quantity * (float) $line->unit_price);
        $discount = (float) $lines->sum('discount_amount');
        $tax = (float) $lines->sum('tax_amount');

        $estimate->forceFill([
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discount, 2),
            'tax_total' => round($tax, 2),
            'grand_total' => round($subtotal - $discount + $tax, 2),
        ])->save();

        return $estimate->refresh();
    }
}
