<?php

namespace App\Services\Automotive\Maintenance;

use App\Models\Maintenance\MaintenanceComplaint;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ComplaintService
{
    public function __construct(
        protected MaintenanceNumberService $numbers,
        protected MaintenanceTimelineService $timeline,
        protected MaintenanceNotificationService $notifications
    ) {
    }

    public function recent(int $limit = 50): Collection
    {
        return MaintenanceComplaint::query()
            ->with(['branch', 'workOrder', 'customer', 'vehicle', 'assignee'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function create(array $data): MaintenanceComplaint
    {
        return DB::transaction(function () use ($data) {
            $complaint = MaintenanceComplaint::query()->create([
                'complaint_number' => $this->numbers->next('maintenance_complaints', 'complaint_number', 'CMP'),
                'branch_id' => $data['branch_id'] ?? null,
                'work_order_id' => $data['work_order_id'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'vehicle_id' => $data['vehicle_id'] ?? null,
                'source' => $data['source'] ?? 'in_branch',
                'status' => 'open',
                'severity' => $data['severity'] ?? 'medium',
                'customer_visible_note' => $data['customer_visible_note'] ?? null,
                'internal_note' => $data['internal_note'] ?? null,
                'assigned_to' => $data['assigned_to'] ?? null,
            ]);

            if ($complaint->workOrder) {
                $this->timeline->recordForWorkOrder($complaint->workOrder, 'complaint_created', 'Complaint created: ' . $complaint->complaint_number, [
                    'created_by' => $data['created_by'] ?? null,
                ]);
            }

            $this->notifications->create('complaint.created', 'Complaint created: ' . $complaint->complaint_number, [
                'branch_id' => $complaint->branch_id,
                'severity' => $complaint->severity === 'urgent' ? 'danger' : 'warning',
                'notifiable' => $complaint,
            ]);

            return $complaint->load(['workOrder', 'customer', 'vehicle', 'assignee']);
        });
    }

    public function resolve(MaintenanceComplaint $complaint, array $data): MaintenanceComplaint
    {
        return DB::transaction(function () use ($complaint, $data) {
            $complaint->forceFill([
                'status' => 'resolved',
                'resolution' => $data['resolution'] ?? null,
                'resolved_at' => now(),
                'resolved_by' => $data['resolved_by'] ?? null,
            ])->save();

            if ($complaint->workOrder) {
                $this->timeline->recordForWorkOrder($complaint->workOrder, 'complaint_resolved', 'Complaint resolved: ' . $complaint->complaint_number, [
                    'created_by' => $data['resolved_by'] ?? null,
                ]);
            }

            return $complaint->fresh(['workOrder', 'customer', 'vehicle', 'assignee']);
        });
    }
}
