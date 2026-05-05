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
        protected MaintenanceTimelineService $timeline,
        protected MaintenanceNotificationService $notifications
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

    public function boardSnapshot(): array
    {
        return collect($this->boardData())
            ->map(fn ($orders) => $orders->map(fn (WorkOrder $workOrder): array => [
                'id' => $workOrder->id,
                'number' => $workOrder->work_order_number,
                'status' => $workOrder->status,
                'priority' => $workOrder->priority ?? 'normal',
                'payment_status' => $workOrder->payment_status ?? 'unpaid',
                'plate_number' => $workOrder->vehicle?->plate_number,
                'vehicle' => trim(($workOrder->vehicle?->make ?? '') . ' ' . ($workOrder->vehicle?->model ?? '')),
                'customer' => $workOrder->customer?->name,
                'branch' => $workOrder->branch?->name,
                'technicians' => $workOrder->maintenanceJobs->pluck('technician.name')->filter()->unique()->values()->all(),
            ])->values())
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

            $this->notifications->create('job.assigned', 'Job assigned: ' . $job->job_number, [
                'branch_id' => $workOrder->branch_id,
                'user_id' => $job->assigned_technician_id,
                'channel' => $job->assigned_technician_id ? 'user' : 'branch',
                'notifiable' => $job,
                'payload' => [
                    'work_order_id' => $workOrder->id,
                    'work_order_number' => $workOrder->work_order_number,
                    'job_id' => $job->id,
                    'job_number' => $job->job_number,
                ],
            ]);

            $this->notifyBoardUpdated($workOrder, 'approved');

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
            $this->notifications->create('job.paused', 'Job paused: ' . $job->job_number, [
                'branch_id' => $job->workOrder?->branch_id,
                'user_id' => $job->assigned_technician_id,
                'notifiable' => $job,
                'payload' => [
                    'work_order_id' => $job->workOrder?->id,
                    'work_order_number' => $job->workOrder?->work_order_number,
                    'job_id' => $job->id,
                    'job_number' => $job->job_number,
                ],
            ]);

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

                $this->notifyBoardUpdated($workOrder, 'ready_for_qc');
            }

            $this->recordTimeline($job, 'job_completed', $userId);
            $this->notifications->create('job.completed', 'Job completed: ' . $job->job_number, [
                'branch_id' => $workOrder?->branch_id,
                'user_id' => $job->assigned_technician_id,
                'notifiable' => $job,
                'payload' => [
                    'work_order_id' => $workOrder?->id,
                    'work_order_number' => $workOrder?->work_order_number,
                    'job_id' => $job->id,
                    'job_number' => $job->job_number,
                ],
            ]);

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
            if ($job->workOrder) {
                $this->notifyBoardUpdated($job->workOrder, 'waiting_parts');
            }

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
            $eventType = match ($event) {
                'job_started' => 'job.started',
                'job_resumed' => 'job.resumed',
                default => 'job.paused',
            };

            $this->notifications->create($eventType, str_replace('_', ' ', ucfirst($event)) . ': ' . $job->job_number, [
                'branch_id' => $job->workOrder?->branch_id,
                'user_id' => $job->assigned_technician_id,
                'notifiable' => $job,
                'payload' => [
                    'work_order_id' => $job->workOrder?->id,
                    'work_order_number' => $job->workOrder?->work_order_number,
                    'job_id' => $job->id,
                    'job_number' => $job->job_number,
                ],
            ]);
            if ($job->workOrder) {
                $this->notifyBoardUpdated($job->workOrder, 'in_progress');
            }

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

    protected function notifyBoardUpdated(WorkOrder $workOrder, string $status): void
    {
        $this->notifications->create('work_order.status.changed', 'Work order status changed: ' . $workOrder->work_order_number, [
            'branch_id' => $workOrder->branch_id,
            'notifiable' => $workOrder,
            'payload' => [
                'work_order_id' => $workOrder->id,
                'work_order_number' => $workOrder->work_order_number,
                'status' => $status,
            ],
        ]);
    }
}
