<?php

namespace App\Services\Automotive\Maintenance;

use App\Models\Maintenance\MaintenanceQcRecord;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class QualityControlService
{
    public function __construct(
        protected MaintenanceNumberService $numbers,
        protected MaintenanceTimelineService $timeline,
        protected MaintenanceNotificationService $notifications
    ) {
    }

    public function recent(int $limit = 50): Collection
    {
        return MaintenanceQcRecord::query()
            ->with(['branch', 'workOrder.customer', 'workOrder.vehicle', 'vehicle', 'inspector', 'items'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function create(array $data): MaintenanceQcRecord
    {
        return DB::transaction(function () use ($data) {
            $workOrder = WorkOrder::query()->with('vehicle')->findOrFail($data['work_order_id']);

            $qc = MaintenanceQcRecord::query()->create([
                'qc_number' => $this->numbers->next('maintenance_qc_records', 'qc_number', 'QC'),
                'branch_id' => $workOrder->branch_id,
                'work_order_id' => $workOrder->id,
                'vehicle_id' => $workOrder->vehicle_id,
                'status' => 'in_progress',
                'qc_inspector_id' => $data['qc_inspector_id'] ?? null,
                'started_at' => now(),
                'created_by' => $data['created_by'] ?? null,
            ]);

            foreach ($this->defaultChecklist() as $label) {
                $qc->items()->create(['label' => $label]);
            }

            $workOrder->forceFill([
                'status' => 'ready_for_qc',
                'vehicle_status' => 'ready_for_qc',
            ])->save();

            $this->timeline->recordForWorkOrder($workOrder, 'qc_started', 'QC started: ' . $qc->qc_number, [
                'created_by' => $data['created_by'] ?? null,
            ]);

            $this->notifications->create('qc.ready', 'QC ready: ' . $workOrder->work_order_number, [
                'branch_id' => $workOrder->branch_id,
                'notifiable' => $qc,
                'payload' => [
                    'work_order_id' => $workOrder->id,
                    'work_order_number' => $workOrder->work_order_number,
                    'qc_id' => $qc->id,
                    'status' => 'ready_for_qc',
                ],
            ]);

            return $qc->load(['items', 'workOrder.customer', 'workOrder.vehicle']);
        });
    }

    public function complete(MaintenanceQcRecord $qc, array $data): MaintenanceQcRecord
    {
        return DB::transaction(function () use ($qc, $data) {
            foreach ($data['items'] ?? [] as $itemId => $itemData) {
                $qc->items()->whereKey($itemId)->update([
                    'passed' => (bool) ($itemData['passed'] ?? false),
                    'note' => $itemData['note'] ?? null,
                ]);
            }

            $result = $data['result'];
            $qc->forceFill([
                'status' => 'completed',
                'result' => $result,
                'completed_at' => now(),
                'final_notes' => $data['final_notes'] ?? null,
                'rework_reason' => $data['rework_reason'] ?? null,
            ])->save();

            $workOrderStatus = $result === 'passed' ? 'ready_for_delivery' : 'qc_failed';
            $qc->workOrder?->forceFill([
                'status' => $workOrderStatus,
                'vehicle_status' => $workOrderStatus,
            ])->save();

            $event = $result === 'passed' ? 'qc_passed' : 'qc_failed';
            if ($qc->workOrder) {
                $this->timeline->recordForWorkOrder($qc->workOrder, $event, 'QC ' . str_replace('_', ' ', $result) . ': ' . $qc->qc_number, [
                    'created_by' => $data['completed_by'] ?? null,
                ]);

                $this->notifications->create($result === 'passed' ? 'qc.passed' : 'qc.failed', 'QC ' . str_replace('_', ' ', $result) . ': ' . $qc->workOrder->work_order_number, [
                    'branch_id' => $qc->workOrder->branch_id,
                    'severity' => $result === 'passed' ? 'success' : 'warning',
                    'notifiable' => $qc,
                    'payload' => [
                        'work_order_id' => $qc->workOrder->id,
                        'work_order_number' => $qc->workOrder->work_order_number,
                        'qc_id' => $qc->id,
                        'status' => $workOrderStatus,
                    ],
                ]);

                $this->notifications->create('work_order.status.changed', 'Work order status changed: ' . $qc->workOrder->work_order_number, [
                    'branch_id' => $qc->workOrder->branch_id,
                    'notifiable' => $qc->workOrder,
                    'payload' => [
                        'work_order_id' => $qc->workOrder->id,
                        'work_order_number' => $qc->workOrder->work_order_number,
                        'status' => $workOrderStatus,
                    ],
                ]);
            }

            return $qc->fresh(['items', 'workOrder.customer', 'workOrder.vehicle', 'inspector']);
        });
    }

    protected function defaultChecklist(): array
    {
        return [
            'Job completed',
            'No warning lights',
            'Test drive completed',
            'Fluids checked',
            'Parts installed correctly',
            'Vehicle cleaned',
            'Customer complaint resolved',
            'Photos attached',
        ];
    }
}
