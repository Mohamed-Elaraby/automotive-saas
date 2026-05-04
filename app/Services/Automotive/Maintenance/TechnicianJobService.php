<?php

namespace App\Services\Automotive\Maintenance;

use App\Models\Maintenance\MaintenanceWorkOrderJob;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TechnicianJobService
{
    public function __construct(
        protected MaintenanceNumberService $numbers,
        protected MaintenanceTimelineService $timeline
    ) {
    }

    public function recentJobs(int $limit = 100): Collection
    {
        return MaintenanceWorkOrderJob::query()
            ->with(['workOrder.customer', 'workOrder.vehicle', 'workOrder.branch', 'technician', 'serviceCatalogItem'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function boardData(): array
    {
        $columns = [
            'waiting' => ['open', 'waiting_inspection'],
            'inspection' => ['under_inspection'],
            'waiting_approval' => ['waiting_customer_approval'],
            'approved' => ['approved'],
            'in_progress' => ['in_progress'],
            'waiting_parts' => ['waiting_parts'],
            'ready_for_qc' => ['ready_for_qc'],
            'qc_failed' => ['qc_failed'],
            'ready_for_delivery' => ['ready_for_delivery'],
            'delivered' => ['delivered'],
        ];

        $workOrders = WorkOrder::query()
            ->with(['branch', 'customer', 'vehicle', 'maintenanceJobs.technician'])
            ->whereIn('status', collect($columns)->flatten()->all())
            ->latest('id')
            ->limit(200)
            ->get();

        return collect($columns)
            ->mapWithKeys(fn (array $statuses, string $column) => [
                $column => $workOrders->whereIn('status', $statuses)->values(),
            ])
            ->all();
    }

    public function create(array $data): MaintenanceWorkOrderJob
    {
        return DB::transaction(function () use ($data) {
            $workOrder = WorkOrder::query()->findOrFail($data['work_order_id']);

            $job = MaintenanceWorkOrderJob::query()->create([
                'job_number' => $this->numbers->next('maintenance_work_order_jobs', 'job_number', 'JOB'),
                'work_order_id' => $workOrder->id,
                'service_catalog_item_id' => $data['service_catalog_item_id'] ?? null,
                'assigned_technician_id' => $data['assigned_technician_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => filled($data['assigned_technician_id'] ?? null) ? 'assigned' : 'pending',
                'priority' => $data['priority'] ?? 'normal',
                'estimated_minutes' => $data['estimated_minutes'] ?? 0,
                'assigned_at' => filled($data['assigned_technician_id'] ?? null) ? now() : null,
                'customer_visible_notes' => $data['customer_visible_notes'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);

            $workOrder->forceFill([
                'status' => 'approved',
                'vehicle_status' => 'work_authorized',
            ])->save();

            $this->timeline->recordForWorkOrder($workOrder, 'job_created', 'Technician job created: ' . $job->job_number, [
                'created_by' => $data['created_by'] ?? null,
            ]);

            return $job->load(['workOrder.customer', 'workOrder.vehicle', 'technician', 'serviceCatalogItem']);
        });
    }

    public function start(MaintenanceWorkOrderJob $job, ?int $userId): MaintenanceWorkOrderJob
    {
        return $this->transition($job, 'started', [
            'started_at' => $job->started_at ?? now(),
            'paused_at' => null,
        ], $userId, 'job_started');
    }

    public function pause(MaintenanceWorkOrderJob $job, ?int $userId, ?string $note = null): MaintenanceWorkOrderJob
    {
        return DB::transaction(function () use ($job, $userId, $note) {
            $this->closeOpenLog($job, 'pause');

            $job->forceFill([
                'status' => 'paused',
                'paused_at' => now(),
            ])->save();

            $job->timeLogs()->create([
                'technician_id' => $userId,
                'action' => 'pause',
                'note' => $note,
            ]);

            $this->recordTimeline($job, 'job_paused', $userId);

            return $job->fresh(['workOrder.customer', 'workOrder.vehicle', 'technician', 'timeLogs']);
        });
    }

    public function resume(MaintenanceWorkOrderJob $job, ?int $userId): MaintenanceWorkOrderJob
    {
        return $this->transition($job, 'started', ['paused_at' => null], $userId, 'job_resumed');
    }

    public function complete(MaintenanceWorkOrderJob $job, ?int $userId, ?string $note = null): MaintenanceWorkOrderJob
    {
        return DB::transaction(function () use ($job, $userId, $note) {
            $this->closeOpenLog($job, 'complete');

            $job->forceFill([
                'status' => 'completed',
                'completed_at' => now(),
                'qc_status' => 'pending',
                'internal_notes' => $note ?: $job->internal_notes,
            ])->save();

            $job->timeLogs()->create([
                'technician_id' => $userId,
                'action' => 'complete',
                'note' => $note,
            ]);

            $workOrder = $job->workOrder()->with('maintenanceJobs')->first();
            if ($workOrder && $workOrder->maintenanceJobs->every(fn ($item) => in_array($item->status, ['completed', 'cancelled'], true))) {
                $workOrder->forceFill([
                    'status' => 'ready_for_qc',
                    'vehicle_status' => 'ready_for_qc',
                ])->save();
            }

            $this->recordTimeline($job, 'job_completed', $userId);

            return $job->fresh(['workOrder.customer', 'workOrder.vehicle', 'technician', 'timeLogs']);
        });
    }

    public function blocker(MaintenanceWorkOrderJob $job, ?int $userId, string $note): MaintenanceWorkOrderJob
    {
        return DB::transaction(function () use ($job, $userId, $note) {
            $job->forceFill([
                'status' => 'waiting_parts',
                'blocker_note' => $note,
            ])->save();

            $job->workOrder?->forceFill([
                'status' => 'waiting_parts',
                'vehicle_status' => 'waiting_parts',
            ])->save();

            $this->recordTimeline($job, 'job_blocked', $userId, $note);

            return $job->fresh(['workOrder.customer', 'workOrder.vehicle', 'technician']);
        });
    }

    protected function transition(MaintenanceWorkOrderJob $job, string $status, array $attributes, ?int $userId, string $event): MaintenanceWorkOrderJob
    {
        return DB::transaction(function () use ($job, $status, $attributes, $userId, $event) {
            $job->forceFill($attributes + ['status' => $status])->save();

            $job->workOrder?->forceFill([
                'status' => 'in_progress',
                'vehicle_status' => 'in_progress',
            ])->save();

            $job->timeLogs()->create([
                'technician_id' => $userId,
                'action' => $status === 'started' ? 'start' : $status,
                'started_at' => now(),
            ]);

            $this->recordTimeline($job, $event, $userId);

            return $job->fresh(['workOrder.customer', 'workOrder.vehicle', 'technician', 'timeLogs']);
        });
    }

    protected function closeOpenLog(MaintenanceWorkOrderJob $job, string $action): void
    {
        $openLog = $job->timeLogs()
            ->where('action', 'start')
            ->whereNull('ended_at')
            ->latest('id')
            ->first();

        if (! $openLog) {
            return;
        }

        $endedAt = now();
        $duration = max(0, $openLog->started_at?->diffInMinutes($endedAt) ?? 0);

        $openLog->forceFill([
            'ended_at' => $endedAt,
            'duration_minutes' => $duration,
            'action' => $action === 'complete' ? 'work_session' : 'paused_session',
        ])->save();

        $job->forceFill([
            'actual_minutes' => ($job->actual_minutes ?? 0) + $duration,
        ])->save();
    }

    protected function recordTimeline(MaintenanceWorkOrderJob $job, string $event, ?int $userId, ?string $note = null): void
    {
        if (! $job->workOrder) {
            return;
        }

        $this->timeline->recordForWorkOrder($job->workOrder, $event, trim($job->job_number . ' - ' . $job->title . ($note ? ': ' . $note : '')), [
            'created_by' => $userId,
        ]);
    }
}
