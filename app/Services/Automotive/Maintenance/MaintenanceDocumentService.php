<?php

namespace App\Services\Automotive\Maintenance;

use App\Models\Core\Documents\GeneratedDocument;
use App\Models\Maintenance\MaintenanceApprovalRecord;
use App\Models\Maintenance\MaintenanceDelivery;
use App\Models\Maintenance\MaintenanceEstimate;
use App\Models\Maintenance\MaintenanceWarranty;
use App\Models\Maintenance\VehicleCheckIn;
use App\Models\WorkOrder;
use App\Services\Core\Documents\DocumentGenerationService;
use Illuminate\Database\Eloquent\Collection;

class MaintenanceDocumentService
{
    public function __construct(protected DocumentGenerationService $documents)
    {
    }

    public function recent(int $limit = 50): Collection
    {
        return GeneratedDocument::query()
            ->with(['branch', 'generator'])
            ->where('product_code', 'maintenance')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function generateCheckIn(VehicleCheckIn $checkIn, array $options): GeneratedDocument
    {
        $checkIn->load(['branch', 'customer', 'vehicle', 'workOrder', 'serviceAdvisor', 'attachments', 'conditionMaps.items']);

        return $this->documents->generate('maintenance_check_in', [
            'check_in' => $checkIn->toArray(),
            'branch' => $checkIn->branch?->toArray() ?? [],
            'customer' => $checkIn->customer?->toArray() ?? [],
            'vehicle' => $checkIn->vehicle?->toArray() ?? [],
            'work_order' => $checkIn->workOrder?->toArray() ?? [],
            'attachments' => $checkIn->attachments->toArray(),
            'condition_maps' => $checkIn->conditionMaps->toArray(),
        ], $options + [
            'documentable' => $checkIn,
            'branch_id' => $checkIn->branch_id,
        ]);
    }

    public function generateWorkOrder(WorkOrder $workOrder, array $options): GeneratedDocument
    {
        $workOrder->load(['branch', 'customer', 'vehicle', 'serviceAdvisor', 'lines', 'maintenanceJobs.technician', 'inspections.items', 'diagnosisRecords', 'qcRecords.items', 'timelineEntries']);

        return $this->documents->generate('maintenance_work_order', [
            'work_order' => $workOrder->toArray(),
            'branch' => $workOrder->branch?->toArray() ?? [],
            'customer' => $workOrder->customer?->toArray() ?? [],
            'vehicle' => $workOrder->vehicle?->toArray() ?? [],
            'jobs' => $workOrder->maintenanceJobs->toArray(),
            'inspections' => $workOrder->inspections->toArray(),
            'diagnosis' => $workOrder->diagnosisRecords->toArray(),
            'qc_records' => $workOrder->qcRecords->toArray(),
            'timeline' => $workOrder->timelineEntries->toArray(),
        ], $options + [
            'documentable' => $workOrder,
            'branch_id' => $workOrder->branch_id,
        ]);
    }

    public function generateEstimate(MaintenanceEstimate $estimate, array $options): GeneratedDocument
    {
        $estimate->load(['branch', 'customer', 'vehicle', 'workOrder', 'lines.serviceCatalogItem', 'approvals']);

        return $this->documents->generate('maintenance_estimate', [
            'estimate' => $estimate->toArray(),
            'branch' => $estimate->branch?->toArray() ?? [],
            'customer' => $estimate->customer?->toArray() ?? [],
            'vehicle' => $estimate->vehicle?->toArray() ?? [],
            'lines' => $estimate->lines->toArray(),
            'approvals' => $estimate->approvals->toArray(),
        ], $options + [
            'documentable' => $estimate,
            'branch_id' => $estimate->branch_id,
        ]);
    }

    public function generateApprovalCertificate(MaintenanceApprovalRecord $approval, array $options): GeneratedDocument
    {
        $approval->load(['branch', 'estimate.lines.serviceCatalogItem', 'workOrder', 'customer', 'vehicle']);

        return $this->documents->generate('maintenance_approval_certificate', [
            'approval' => $approval->toArray(),
            'estimate' => $approval->estimate?->toArray() ?? [],
            'branch' => $approval->branch?->toArray() ?? [],
            'customer' => $approval->customer?->toArray() ?? [],
            'vehicle' => $approval->vehicle?->toArray() ?? [],
            'work_order' => $approval->workOrder?->toArray() ?? [],
            'lines' => $approval->estimate?->lines?->toArray() ?? [],
        ], $options + [
            'documentable' => $approval,
            'branch_id' => $approval->branch_id,
        ]);
    }

    public function generateDelivery(MaintenanceDelivery $delivery, array $options): GeneratedDocument
    {
        $delivery->load(['branch', 'workOrder', 'customer', 'vehicle', 'deliverer']);

        return $this->documents->generate('maintenance_delivery_report', [
            'delivery' => $delivery->toArray(),
            'branch' => $delivery->branch?->toArray() ?? [],
            'customer' => $delivery->customer?->toArray() ?? [],
            'vehicle' => $delivery->vehicle?->toArray() ?? [],
            'work_order' => $delivery->workOrder?->toArray() ?? [],
        ], $options + [
            'documentable' => $delivery,
            'branch_id' => $delivery->branch_id,
        ]);
    }

    public function generateWarranty(MaintenanceWarranty $warranty, array $options): GeneratedDocument
    {
        $warranty->load(['branch', 'workOrder', 'serviceCatalogItem', 'customer', 'vehicle']);

        return $this->documents->generate('maintenance_warranty_certificate', [
            'warranty' => $warranty->toArray(),
            'branch' => $warranty->branch?->toArray() ?? [],
            'customer' => $warranty->customer?->toArray() ?? [],
            'vehicle' => $warranty->vehicle?->toArray() ?? [],
            'work_order' => $warranty->workOrder?->toArray() ?? [],
            'service' => $warranty->serviceCatalogItem?->toArray() ?? [],
        ], $options + [
            'documentable' => $warranty,
            'branch_id' => $warranty->branch_id,
        ]);
    }
}
