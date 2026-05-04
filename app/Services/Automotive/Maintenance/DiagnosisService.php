<?php

namespace App\Services\Automotive\Maintenance;

use App\Models\Maintenance\MaintenanceDiagnosisRecord;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DiagnosisService
{
    public function __construct(
        protected MaintenanceNumberService $numbers,
        protected MaintenanceTimelineService $timeline
    ) {
    }

    public function recent(int $limit = 50): Collection
    {
        return MaintenanceDiagnosisRecord::query()
            ->with(['branch', 'workOrder', 'inspection', 'vehicle', 'customer', 'technician'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function create(array $data): MaintenanceDiagnosisRecord
    {
        return DB::transaction(function () use ($data) {
            $workOrder = filled($data['work_order_id'] ?? null)
                ? WorkOrder::query()->with(['customer', 'vehicle'])->find($data['work_order_id'])
                : null;

            $diagnosis = MaintenanceDiagnosisRecord::query()->create([
                'diagnosis_number' => $this->numbers->next('maintenance_diagnosis_records', 'diagnosis_number', 'DIA'),
                'branch_id' => $data['branch_id'] ?? $workOrder?->branch_id,
                'work_order_id' => $workOrder?->id,
                'inspection_id' => $data['inspection_id'] ?? null,
                'vehicle_id' => $data['vehicle_id'] ?? $workOrder?->vehicle_id,
                'customer_id' => $data['customer_id'] ?? $workOrder?->customer_id,
                'complaint' => $data['complaint'] ?? null,
                'symptoms' => $data['symptoms'] ?? null,
                'scanner_report' => $data['scanner_report'] ?? null,
                'fault_codes' => collect(explode(',', (string) ($data['fault_codes'] ?? '')))->map(fn ($code) => trim($code))->filter()->values()->all(),
                'root_cause' => $data['root_cause'] ?? null,
                'recommended_repair' => $data['recommended_repair'] ?? null,
                'estimated_labor_hours' => $data['estimated_labor_hours'] ?? null,
                'estimated_minutes' => $data['estimated_minutes'] ?? null,
                'priority' => $data['priority'] ?? 'normal',
                'technician_notes' => $data['technician_notes'] ?? null,
                'diagnosed_by' => $data['diagnosed_by'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);

            if ($workOrder) {
                $this->timeline->recordForWorkOrder($workOrder, 'diagnosis_recorded', 'Diagnosis recorded: ' . $diagnosis->diagnosis_number, [
                    'created_by' => $data['created_by'] ?? null,
                ]);
            }

            return $diagnosis->load(['workOrder.customer', 'workOrder.vehicle', 'inspection', 'technician']);
        });
    }
}
