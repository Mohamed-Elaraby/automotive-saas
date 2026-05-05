<?php

namespace App\Services\Automotive\Maintenance;

use App\Models\Maintenance\MaintenanceDelivery;
use App\Models\Maintenance\MaintenanceWarranty;
use App\Models\Maintenance\MaintenanceWarrantyClaim;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DeliveryWarrantyService
{
    public function __construct(
        protected MaintenanceNumberService $numbers,
        protected MaintenanceTimelineService $timeline,
        protected MaintenanceNotificationService $notifications
    ) {
    }

    public function deliveries(int $limit = 50): Collection
    {
        return MaintenanceDelivery::query()
            ->with(['branch', 'workOrder', 'customer', 'vehicle', 'deliverer'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function createDelivery(array $data): MaintenanceDelivery
    {
        return DB::transaction(function () use ($data) {
            $workOrder = WorkOrder::query()->with(['customer', 'vehicle'])->findOrFail($data['work_order_id']);

            $delivery = MaintenanceDelivery::query()->create([
                'delivery_number' => $this->numbers->next('maintenance_deliveries', 'delivery_number', 'DEL'),
                'branch_id' => $workOrder->branch_id,
                'work_order_id' => $workOrder->id,
                'customer_id' => $workOrder->customer_id,
                'vehicle_id' => $workOrder->vehicle_id,
                'status' => 'ready',
                'checklist' => $data['checklist'] ?? [],
                'payment_status' => $workOrder->payment_status ?? 'unpaid',
                'customer_signature' => $data['customer_signature'] ?? null,
                'advisor_signature' => $data['advisor_signature'] ?? null,
                'customer_visible_notes' => $data['customer_visible_notes'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
            ]);

            $workOrder->forceFill([
                'status' => 'ready_for_delivery',
                'vehicle_status' => 'ready_for_delivery',
            ])->save();

            $this->timeline->recordForWorkOrder($workOrder, 'delivery_ready', 'Vehicle ready for delivery: ' . $delivery->delivery_number, [
                'created_by' => $data['created_by'] ?? null,
            ]);

            $this->notifications->create('vehicle.ready_for_delivery', 'Vehicle ready for delivery: ' . $workOrder->work_order_number, [
                'branch_id' => $workOrder->branch_id,
                'severity' => 'success',
                'notifiable' => $delivery,
                'payload' => [
                    'work_order_id' => $workOrder->id,
                    'work_order_number' => $workOrder->work_order_number,
                    'status' => 'ready_for_delivery',
                ],
            ]);

            return $delivery->load(['workOrder', 'customer', 'vehicle']);
        });
    }

    public function release(MaintenanceDelivery $delivery, array $data): MaintenanceDelivery
    {
        return DB::transaction(function () use ($delivery, $data) {
            $checklist = array_merge($delivery->checklist ?? [], $data['checklist'] ?? []);

            $delivery->forceFill([
                'status' => 'delivered',
                'checklist' => $checklist,
                'payment_status' => $data['payment_status'] ?? $delivery->payment_status,
                'customer_signature' => $data['customer_signature'] ?? $delivery->customer_signature,
                'advisor_signature' => $data['advisor_signature'] ?? $delivery->advisor_signature,
                'delivered_at' => now(),
                'delivered_by' => $data['delivered_by'] ?? null,
                'customer_visible_notes' => $data['customer_visible_notes'] ?? $delivery->customer_visible_notes,
                'internal_notes' => $data['internal_notes'] ?? $delivery->internal_notes,
            ])->save();

            $delivery->workOrder?->forceFill([
                'status' => 'delivered',
                'vehicle_status' => 'delivered',
                'payment_status' => $delivery->payment_status,
                'closed_at' => now(),
            ])->save();

            if ($delivery->workOrder) {
                $this->timeline->recordForWorkOrder($delivery->workOrder, 'vehicle_delivered', 'Vehicle delivered: ' . $delivery->delivery_number, [
                    'created_by' => $data['delivered_by'] ?? null,
                ]);
            }

            $this->notifications->create('vehicle.delivered', 'Vehicle delivered: ' . $delivery->delivery_number, [
                'branch_id' => $delivery->branch_id,
                'severity' => 'success',
                'notifiable' => $delivery,
                'payload' => [
                    'work_order_id' => $delivery->work_order_id,
                    'delivery_id' => $delivery->id,
                    'status' => 'delivered',
                ],
            ]);

            return $delivery->fresh(['workOrder', 'customer', 'vehicle']);
        });
    }

    public function warranties(int $limit = 50): Collection
    {
        return MaintenanceWarranty::query()
            ->with(['workOrder', 'serviceCatalogItem', 'customer', 'vehicle'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function createWarranty(array $data): MaintenanceWarranty
    {
        return DB::transaction(function () use ($data) {
            $workOrder = WorkOrder::query()->find($data['work_order_id'] ?? null);

            return MaintenanceWarranty::query()->create([
                'warranty_number' => $this->numbers->next('maintenance_warranties', 'warranty_number', 'WAR'),
                'branch_id' => $data['branch_id'] ?? $workOrder?->branch_id,
                'work_order_id' => $workOrder?->id,
                'service_catalog_item_id' => $data['service_catalog_item_id'] ?? null,
                'customer_id' => $data['customer_id'] ?? $workOrder?->customer_id,
                'vehicle_id' => $data['vehicle_id'] ?? $workOrder?->vehicle_id,
                'warranty_type' => $data['warranty_type'] ?? 'labor',
                'start_date' => $data['start_date'] ?? now()->toDateString(),
                'end_date' => $data['end_date'] ?? null,
                'mileage_limit' => $data['mileage_limit'] ?? null,
                'status' => 'active',
                'terms' => $data['terms'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);
        });
    }

    public function claims(int $limit = 50): Collection
    {
        return MaintenanceWarrantyClaim::query()
            ->with(['warranty', 'originalWorkOrder', 'customer', 'vehicle'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function createClaim(array $data): MaintenanceWarrantyClaim
    {
        return MaintenanceWarrantyClaim::query()->create([
            'claim_number' => $this->numbers->next('maintenance_warranty_claims', 'claim_number', 'WCL'),
            'warranty_id' => $data['warranty_id'] ?? null,
            'original_work_order_id' => $data['original_work_order_id'] ?? null,
            'customer_id' => $data['customer_id'] ?? null,
            'vehicle_id' => $data['vehicle_id'] ?? null,
            'status' => 'pending',
            'comeback_reason' => $data['comeback_reason'] ?? null,
            'customer_complaint' => $data['customer_complaint'] ?? null,
            'root_cause' => $data['root_cause'] ?? null,
            'resolution' => $data['resolution'] ?? null,
            'cost_impact' => $data['cost_impact'] ?? 0,
        ]);
    }
}
