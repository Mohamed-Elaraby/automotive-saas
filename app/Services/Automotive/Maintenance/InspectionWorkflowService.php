<?php

namespace App\Services\Automotive\Maintenance;

use App\Models\Maintenance\MaintenanceInspection;
use App\Models\Maintenance\MaintenanceInspectionTemplate;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class InspectionWorkflowService
{
    public function __construct(
        protected MaintenanceNumberService $numbers,
        protected MaintenanceTimelineService $timeline,
        protected MaintenanceNotificationService $notifications
    ) {
    }

    public function templates(): Collection
    {
        return MaintenanceInspectionTemplate::query()
            ->with('items')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    public function createTemplate(array $data): MaintenanceInspectionTemplate
    {
        return DB::transaction(function () use ($data) {
            $template = MaintenanceInspectionTemplate::query()->create([
                'template_number' => $this->numbers->next('maintenance_inspection_templates', 'template_number', 'ITP'),
                'name' => $data['name'],
                'inspection_type' => $data['inspection_type'] ?? 'initial',
                'is_default' => (bool) ($data['is_default'] ?? false),
                'is_active' => (bool) ($data['is_active'] ?? true),
                'description' => $data['description'] ?? null,
            ]);

            foreach ($data['items'] ?? [] as $index => $item) {
                if (blank($item['label'] ?? null)) {
                    continue;
                }

                $template->items()->create([
                    'section' => $item['section'] ?? null,
                    'label' => $item['label'],
                    'default_result' => $item['default_result'] ?? 'not_checked',
                    'requires_photo' => (bool) ($item['requires_photo'] ?? false),
                    'sort_order' => $index + 1,
                ]);
            }

            return $template->load('items');
        });
    }

    public function recentInspections(int $limit = 50): Collection
    {
        return MaintenanceInspection::query()
            ->with(['branch', 'workOrder', 'vehicle', 'customer', 'assignee'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function createInspection(array $data): MaintenanceInspection
    {
        return DB::transaction(function () use ($data) {
            $workOrder = filled($data['work_order_id'] ?? null)
                ? WorkOrder::query()->with(['customer', 'vehicle'])->find($data['work_order_id'])
                : null;

            $template = filled($data['template_id'] ?? null)
                ? MaintenanceInspectionTemplate::query()->with('items')->find($data['template_id'])
                : null;

            $inspection = MaintenanceInspection::query()->create([
                'inspection_number' => $this->numbers->next('maintenance_inspections', 'inspection_number', 'INS'),
                'branch_id' => $data['branch_id'] ?? $workOrder?->branch_id,
                'work_order_id' => $workOrder?->id,
                'check_in_id' => $data['check_in_id'] ?? null,
                'vehicle_id' => $data['vehicle_id'] ?? $workOrder?->vehicle_id,
                'customer_id' => $data['customer_id'] ?? $workOrder?->customer_id,
                'template_id' => $template?->id,
                'inspection_type' => $data['inspection_type'] ?? $template?->inspection_type ?? 'initial',
                'status' => 'under_inspection',
                'assigned_to' => $data['assigned_to'] ?? null,
                'started_by' => $data['started_by'] ?? null,
                'started_at' => now(),
                'summary' => $data['summary'] ?? null,
                'customer_visible_notes' => $data['customer_visible_notes'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);

            foreach ($template?->items ?? collect() as $templateItem) {
                $inspection->items()->create([
                    'template_item_id' => $templateItem->id,
                    'section' => $templateItem->section,
                    'label' => $templateItem->label,
                    'result' => $templateItem->default_result,
                ]);
            }

            if ($workOrder) {
                $workOrder->forceFill([
                    'status' => 'under_inspection',
                    'vehicle_status' => 'under_inspection',
                ])->save();

                $this->timeline->recordForWorkOrder($workOrder, 'inspection_started', 'Inspection started: ' . $inspection->inspection_number, [
                    'created_by' => $data['created_by'] ?? null,
                ]);

                $this->notifications->create('work_order.status.changed', 'Work order status changed: ' . $workOrder->work_order_number, [
                    'branch_id' => $workOrder->branch_id,
                    'notifiable' => $workOrder,
                    'payload' => [
                        'work_order_id' => $workOrder->id,
                        'work_order_number' => $workOrder->work_order_number,
                        'status' => 'under_inspection',
                    ],
                ]);
            }

            return $inspection->load(['items', 'workOrder', 'vehicle', 'customer']);
        });
    }

    public function updateItems(MaintenanceInspection $inspection, array $items): MaintenanceInspection
    {
        return DB::transaction(function () use ($inspection, $items) {
            foreach ($items as $itemId => $itemData) {
                $inspection->items()->whereKey($itemId)->update([
                    'result' => $itemData['result'] ?? 'not_checked',
                    'note' => $itemData['note'] ?? null,
                    'recommendation' => $itemData['recommendation'] ?? null,
                    'estimated_cost' => $itemData['estimated_cost'] ?? null,
                    'customer_approval_status' => $itemData['customer_approval_status'] ?? 'pending',
                ]);
            }

            return $inspection->fresh(['items', 'workOrder', 'vehicle', 'customer']);
        });
    }

    public function complete(MaintenanceInspection $inspection, array $data): MaintenanceInspection
    {
        return DB::transaction(function () use ($inspection, $data) {
            $inspection->forceFill([
                'status' => 'completed',
                'completed_by' => $data['completed_by'] ?? null,
                'completed_at' => now(),
                'summary' => $data['summary'] ?? $inspection->summary,
                'customer_visible_notes' => $data['customer_visible_notes'] ?? $inspection->customer_visible_notes,
                'internal_notes' => $data['internal_notes'] ?? $inspection->internal_notes,
            ])->save();

            if ($inspection->workOrder) {
                $inspection->workOrder->forceFill([
                    'status' => 'waiting_customer_approval',
                    'vehicle_status' => 'inspection_completed',
                ])->save();

                $this->timeline->recordForWorkOrder($inspection->workOrder, 'inspection_completed', 'Inspection completed: ' . $inspection->inspection_number, [
                    'created_by' => $data['completed_by'] ?? null,
                ]);

                $this->notifications->create('work_order.status.changed', 'Work order status changed: ' . $inspection->workOrder->work_order_number, [
                    'branch_id' => $inspection->workOrder->branch_id,
                    'notifiable' => $inspection->workOrder,
                    'payload' => [
                        'work_order_id' => $inspection->workOrder->id,
                        'work_order_number' => $inspection->workOrder->work_order_number,
                        'status' => 'waiting_customer_approval',
                    ],
                ]);
            }

            return $inspection->fresh(['items', 'workOrder', 'vehicle', 'customer']);
        });
    }
}
