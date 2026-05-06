<?php

namespace App\Services\Automotive\Maintenance;

use App\Models\Maintenance\MaintenanceApprovalRecord;
use App\Models\Maintenance\MaintenanceEstimate;
use App\Models\Maintenance\MaintenanceLostSale;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApprovalWorkflowService
{
    public function __construct(
        protected MaintenanceTimelineService $timeline,
        protected MaintenanceNotificationService $notifications,
        protected MaintenanceAuditService $audit
    ) {
    }

    public function pending(int $limit = 50): Collection
    {
        return MaintenanceEstimate::query()
            ->with(['branch', 'customer', 'vehicle', 'workOrder', 'lines'])
            ->whereIn('status', ['draft', 'sent', 'viewed'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function send(MaintenanceEstimate $estimate, array $data): MaintenanceEstimate
    {
        return DB::transaction(function () use ($estimate, $data) {
            $estimate->forceFill([
                'status' => 'sent',
                'approval_method' => $data['approval_method'] ?? 'manual',
                'approval_token' => $estimate->approval_token ?: Str::random(48),
            ])->save();

            if ($estimate->workOrder) {
                $estimate->workOrder->forceFill(['status' => 'waiting_customer_approval'])->save();
                $this->timeline->recordForWorkOrder($estimate->workOrder, 'estimate_sent', 'Estimate sent: ' . $estimate->estimate_number, [
                    'created_by' => $data['created_by'] ?? null,
                ]);
            }

            $this->notifications->create('estimate.sent', 'Estimate sent: ' . $estimate->estimate_number, [
                'branch_id' => $estimate->branch_id,
                'channel' => 'branch',
                'notifiable' => $estimate,
                'payload' => ['estimate_id' => $estimate->id],
            ]);

            return $estimate->fresh(['lines', 'workOrder']);
        });
    }

    public function approve(MaintenanceEstimate $estimate, array $data): MaintenanceApprovalRecord
    {
        return DB::transaction(function () use ($estimate, $data) {
            $lineIds = collect($data['approved_line_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values();
            $lines = $estimate->lines()->get();
            $approvedLines = $lineIds->isEmpty() ? $lines : $lines->whereIn('id', $lineIds);
            $rejectedLines = $lineIds->isEmpty() ? collect() : $lines->whereNotIn('id', $lineIds);
            $approvedAmount = (float) $approvedLines->sum('total_price');

            foreach ($approvedLines as $line) {
                $line->forceFill(['approval_status' => 'approved'])->save();
            }

            foreach ($rejectedLines as $line) {
                $line->forceFill(['approval_status' => 'rejected'])->save();
                MaintenanceLostSale::query()->create([
                    'branch_id' => $estimate->branch_id,
                    'estimate_id' => $estimate->id,
                    'estimate_line_id' => $line->id,
                    'customer_id' => $estimate->customer_id,
                    'vehicle_id' => $estimate->vehicle_id,
                    'item_description' => $line->description,
                    'reason' => $data['rejection_reason'] ?? 'other',
                    'amount' => $line->total_price,
                    'follow_up_date' => $data['follow_up_date'] ?? null,
                    'notes' => $data['reason'] ?? null,
                    'advisor_id' => $data['approved_by'] ?? null,
                ]);
            }

            $status = $rejectedLines->isNotEmpty() && $approvedLines->isNotEmpty()
                ? 'partially_approved'
                : ($approvedLines->isNotEmpty() ? 'approved' : 'rejected');

            $estimate->forceFill([
                'status' => $status,
                'approved_amount' => $approvedAmount,
                'approved_at' => now(),
                'approved_by' => $data['approved_by'] ?? null,
                'approval_method' => $data['method'] ?? 'manual',
            ])->save();

            if ($estimate->workOrder && in_array($status, ['approved', 'partially_approved'], true)) {
                $estimate->workOrder->forceFill([
                    'status' => 'approved',
                    'vehicle_status' => 'work_authorized',
                ])->save();

                $this->timeline->recordForWorkOrder($estimate->workOrder, 'estimate_approved', 'Estimate approved: ' . $estimate->estimate_number, [
                    'created_by' => $data['approved_by'] ?? null,
                ]);
            }

            $record = MaintenanceApprovalRecord::query()->create([
                'branch_id' => $estimate->branch_id,
                'estimate_id' => $estimate->id,
                'work_order_id' => $estimate->work_order_id,
                'customer_id' => $estimate->customer_id,
                'vehicle_id' => $estimate->vehicle_id,
                'approval_type' => 'estimate',
                'status' => $status,
                'method' => $data['method'] ?? 'manual',
                'approved_amount' => $approvedAmount,
                'approved_items' => $approvedLines->pluck('id')->values()->all(),
                'rejected_items' => $rejectedLines->pluck('id')->values()->all(),
                'reason' => $data['reason'] ?? null,
                'terms_snapshot' => $estimate->terms,
                'terms_accepted' => (bool) ($data['terms_accepted'] ?? false),
                'ip_address' => $data['ip_address'] ?? null,
                'device_summary' => $data['device_summary'] ?? null,
                'approved_by' => $data['approved_by'] ?? null,
                'approved_at' => now(),
            ]);

            $this->notifications->create('estimate.' . $status, 'Estimate ' . str_replace('_', ' ', $status) . ': ' . $estimate->estimate_number, [
                'branch_id' => $estimate->branch_id,
                'severity' => $status === 'rejected' ? 'warning' : 'success',
                'notifiable' => $estimate,
                'payload' => ['estimate_id' => $estimate->id, 'approval_id' => $record->id],
            ]);

            $this->audit->record('estimate.manually_approved', 'approvals', [
                'branch_id' => $estimate->branch_id,
                'user_id' => $data['approved_by'] ?? null,
                'auditable' => $record,
                'new_values' => [
                    'estimate_id' => $estimate->id,
                    'status' => $status,
                    'approved_amount' => $approvedAmount,
                    'method' => $record->method,
                ],
            ]);

            return $record->load(['estimate.lines', 'workOrder', 'customer', 'vehicle']);
        });
    }

    public function markViewedFromPortal(MaintenanceEstimate $estimate): MaintenanceEstimate
    {
        if ($estimate->status !== 'sent') {
            return $estimate;
        }

        $estimate->forceFill(['status' => 'viewed'])->save();

        if ($estimate->workOrder) {
            $this->timeline->recordForWorkOrder($estimate->workOrder, 'estimate_viewed', 'Estimate viewed by customer: ' . $estimate->estimate_number, [
                'customer_visible_note' => 'Estimate viewed by customer.',
            ]);
        }

        return $estimate->refresh();
    }

    public function customerDecision(MaintenanceEstimate $estimate, array $data): MaintenanceApprovalRecord
    {
        return DB::transaction(function () use ($estimate, $data) {
            $lines = $estimate->lines()->get();
            $lineIds = collect($data['approved_line_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values();
            $approvedLines = ($data['decision'] ?? 'approve') === 'reject'
                ? collect()
                : ($lineIds->isEmpty() ? $lines : $lines->whereIn('id', $lineIds));
            $rejectedLines = $lines->whereNotIn('id', $approvedLines->pluck('id'));
            $approvedAmount = (float) $approvedLines->sum('total_price');

            foreach ($approvedLines as $line) {
                $line->forceFill(['approval_status' => 'approved'])->save();
            }

            foreach ($rejectedLines as $line) {
                $line->forceFill(['approval_status' => 'rejected'])->save();
                MaintenanceLostSale::query()->create([
                    'branch_id' => $estimate->branch_id,
                    'estimate_id' => $estimate->id,
                    'estimate_line_id' => $line->id,
                    'customer_id' => $estimate->customer_id,
                    'vehicle_id' => $estimate->vehicle_id,
                    'item_description' => $line->description,
                    'reason' => $data['rejection_reason'] ?? 'other',
                    'amount' => $line->total_price,
                    'follow_up_date' => $data['follow_up_date'] ?? null,
                    'notes' => $data['reason'] ?? null,
                    'advisor_id' => null,
                ]);
            }

            $status = $rejectedLines->isNotEmpty() && $approvedLines->isNotEmpty()
                ? 'partially_approved'
                : ($approvedLines->isNotEmpty() ? 'approved' : 'rejected');

            $estimate->forceFill([
                'status' => $status,
                'approved_amount' => $approvedAmount,
                'approved_at' => now(),
                'approved_by' => null,
                'approval_method' => 'portal',
            ])->save();

            if ($estimate->workOrder) {
                if (in_array($status, ['approved', 'partially_approved'], true)) {
                    $estimate->workOrder->forceFill([
                        'status' => 'approved',
                        'vehicle_status' => 'work_authorized',
                    ])->save();
                }

                $this->timeline->recordForWorkOrder($estimate->workOrder, 'estimate_' . $status, 'Estimate ' . str_replace('_', ' ', $status) . ' by customer: ' . $estimate->estimate_number, [
                    'customer_visible_note' => 'Estimate decision received.',
                    'payload' => ['estimate_id' => $estimate->id, 'status' => $status],
                ]);
            }

            $record = MaintenanceApprovalRecord::query()->create([
                'branch_id' => $estimate->branch_id,
                'estimate_id' => $estimate->id,
                'work_order_id' => $estimate->work_order_id,
                'customer_id' => $estimate->customer_id,
                'vehicle_id' => $estimate->vehicle_id,
                'approval_type' => 'estimate',
                'status' => $status,
                'method' => 'portal',
                'approved_amount' => $approvedAmount,
                'approved_items' => $approvedLines->pluck('id')->values()->all(),
                'rejected_items' => $rejectedLines->pluck('id')->values()->all(),
                'reason' => $data['reason'] ?? null,
                'terms_snapshot' => $estimate->terms,
                'terms_accepted' => (bool) ($data['terms_accepted'] ?? false),
                'ip_address' => $data['ip_address'] ?? null,
                'device_summary' => $data['device_summary'] ?? null,
                'approved_by' => null,
                'approved_at' => now(),
            ]);

            $this->notifications->create('estimate.' . $status, 'Customer ' . str_replace('_', ' ', $status) . ': ' . $estimate->estimate_number, [
                'branch_id' => $estimate->branch_id,
                'severity' => $status === 'rejected' ? 'warning' : 'success',
                'notifiable' => $estimate,
                'payload' => ['estimate_id' => $estimate->id, 'approval_id' => $record->id, 'source' => 'portal'],
            ]);

            $this->audit->record('estimate.customer_decision', 'approvals', [
                'branch_id' => $estimate->branch_id,
                'auditable' => $record,
                'new_values' => [
                    'estimate_id' => $estimate->id,
                    'status' => $status,
                    'approved_amount' => $approvedAmount,
                    'method' => 'portal',
                ],
            ]);

            return $record->load(['estimate.lines', 'workOrder', 'customer', 'vehicle']);
        });
    }

    public function approvals(int $limit = 50): Collection
    {
        return MaintenanceApprovalRecord::query()
            ->with(['estimate', 'workOrder', 'customer', 'vehicle', 'approver'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function lostSales(int $limit = 50): Collection
    {
        return MaintenanceLostSale::query()
            ->with(['estimate', 'estimateLine', 'customer', 'vehicle', 'advisor'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
