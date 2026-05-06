<?php

namespace App\Services\Automotive\Maintenance;

use App\Models\Maintenance\MaintenanceCustomerFeedback;
use App\Models\Maintenance\MaintenanceEstimate;
use App\Models\Maintenance\MaintenanceInvoice;
use App\Models\Maintenance\MaintenanceTimelineEntry;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class MaintenanceCustomerPortalService
{
    public function __construct(
        protected MaintenanceNumberService $numbers,
        protected MaintenanceTimelineService $timeline,
        protected MaintenanceNotificationService $notifications,
        protected MaintenanceAuditService $audit
    ) {
    }

    public function workOrderForToken(string $token): WorkOrder
    {
        return WorkOrder::query()
            ->with(['branch', 'customer', 'vehicle', 'checkIns', 'deliveries', 'warranties.serviceCatalogItem', 'customerFeedback'])
            ->where('customer_tracking_token', $token)
            ->firstOrFail();
    }

    public function publicTimeline(WorkOrder $workOrder): Collection
    {
        return MaintenanceTimelineEntry::query()
            ->where('work_order_id', $workOrder->id)
            ->where(function ($query) {
                $query->whereNotNull('customer_visible_note')
                    ->orWhereIn('event_type', [
                        'vehicle.checked_in',
                        'estimate_sent',
                        'estimate_viewed',
                        'estimate_approved',
                        'estimate_partially_approved',
                        'estimate_rejected',
                        'invoice.created',
                        'payment.received',
                        'job.completed',
                        'qc.passed',
                        'vehicle.delivered',
                    ]);
            })
            ->oldest('id')
            ->get();
    }

    public function publicEstimates(WorkOrder $workOrder): Collection
    {
        return MaintenanceEstimate::query()
            ->with('lines')
            ->where('work_order_id', $workOrder->id)
            ->whereIn('status', ['sent', 'viewed', 'approved', 'partially_approved', 'rejected', 'expired'])
            ->latest('id')
            ->get();
    }

    public function publicInvoices(WorkOrder $workOrder): Collection
    {
        return MaintenanceInvoice::query()
            ->with('receipts')
            ->where('work_order_id', $workOrder->id)
            ->latest('id')
            ->get();
    }

    public function serviceHistory(WorkOrder $workOrder): Collection
    {
        return WorkOrder::query()
            ->where('vehicle_id', $workOrder->vehicle_id)
            ->whereNotNull('vehicle_id')
            ->whereIn('status', ['delivered', 'closed'])
            ->latest('id')
            ->limit(10)
            ->get();
    }

    public function trackingPayload(string $token): array
    {
        $workOrder = $this->workOrderForToken($token);
        $timeline = $this->publicTimeline($workOrder);
        $estimates = $this->publicEstimates($workOrder);
        $invoices = $this->publicInvoices($workOrder);
        $history = $this->serviceHistory($workOrder);

        return [
            'work_order' => [
                'number' => $workOrder->work_order_number,
                'status' => $workOrder->status,
                'vehicle_status' => $workOrder->vehicle_status,
                'payment_status' => $workOrder->payment_status,
                'expected_delivery_at' => optional($workOrder->expected_delivery_at)->toISOString(),
                'customer_note' => $workOrder->customer_visible_notes,
            ],
            'customer' => [
                'name' => $workOrder->customer?->name,
            ],
            'vehicle' => [
                'plate_number' => $workOrder->vehicle?->plate_number,
                'make' => $workOrder->vehicle?->make,
                'model' => $workOrder->vehicle?->model,
                'year' => $workOrder->vehicle?->year,
                'vin' => $workOrder->vehicle?->vin,
            ],
            'timeline' => $timeline->map(fn (MaintenanceTimelineEntry $entry): array => [
                'event_type' => $entry->event_type,
                'title' => $entry->customer_visible_note ?: $entry->title,
                'created_at' => optional($entry->created_at)->toISOString(),
            ])->values(),
            'estimates' => $estimates->map(fn (MaintenanceEstimate $estimate): array => [
                'estimate_number' => $estimate->estimate_number,
                'status' => $estimate->status,
                'total' => (float) $estimate->grand_total,
                'review_url' => in_array($estimate->status, ['sent', 'viewed'], true) && $estimate->approval_token
                    ? route('automotive.customer.maintenance.estimate', $estimate->approval_token)
                    : null,
            ])->values(),
            'invoices' => $invoices->map(fn (MaintenanceInvoice $invoice): array => [
                'invoice_number' => $invoice->invoice_number,
                'status' => $invoice->status,
                'payment_status' => $invoice->payment_status,
                'total' => (float) $invoice->grand_total,
                'paid_amount' => (float) $invoice->paid_amount,
            ])->values(),
            'warranties' => $workOrder->warranties->map(fn ($warranty): array => [
                'warranty_number' => $warranty->warranty_number,
                'type' => $warranty->warranty_type,
                'status' => $warranty->status,
                'start_date' => optional($warranty->start_date)->toDateString(),
                'end_date' => optional($warranty->end_date)->toDateString(),
                'mileage_limit' => $warranty->mileage_limit,
            ])->values(),
            'deliveries' => $workOrder->deliveries->map(fn ($delivery): array => [
                'delivery_number' => $delivery->delivery_number,
                'status' => $delivery->status,
                'delivered_at' => optional($delivery->delivered_at)->toISOString(),
                'customer_note' => $delivery->customer_visible_notes,
            ])->values(),
            'service_history' => $history->map(fn (WorkOrder $historyOrder): array => [
                'work_order_number' => $historyOrder->work_order_number,
                'status' => $historyOrder->status,
                'closed_at' => optional($historyOrder->closed_at)->toDateString(),
            ])->values(),
        ];
    }

    public function estimatePayload(string $token): array
    {
        $estimate = MaintenanceEstimate::query()
            ->with(['customer', 'vehicle', 'workOrder', 'lines.serviceCatalogItem'])
            ->where('approval_token', $token)
            ->firstOrFail();

        return [
            'estimate_number' => $estimate->estimate_number,
            'status' => $estimate->status,
            'customer' => ['name' => $estimate->customer?->name],
            'vehicle' => [
                'plate_number' => $estimate->vehicle?->plate_number,
                'make' => $estimate->vehicle?->make,
                'model' => $estimate->vehicle?->model,
            ],
            'subtotal' => (float) $estimate->subtotal,
            'discount_total' => (float) $estimate->discount_total,
            'tax_total' => (float) $estimate->tax_total,
            'grand_total' => (float) $estimate->grand_total,
            'terms' => $estimate->terms,
            'customer_note' => $estimate->customer_visible_notes,
            'lines' => $estimate->lines->map(fn ($line): array => [
                'id' => $line->id,
                'description' => $line->description,
                'quantity' => (float) $line->quantity,
                'total_price' => (float) $line->total_price,
                'approval_status' => $line->approval_status,
            ])->values(),
        ];
    }

    public function submitFeedback(WorkOrder $workOrder, array $data): MaintenanceCustomerFeedback
    {
        return DB::transaction(function () use ($workOrder, $data): MaintenanceCustomerFeedback {
            $feedback = MaintenanceCustomerFeedback::query()->create([
                'feedback_number' => $this->numbers->next('maintenance_customer_feedback', 'feedback_number', 'FDB'),
                'branch_id' => $workOrder->branch_id,
                'work_order_id' => $workOrder->id,
                'customer_id' => $workOrder->customer_id,
                'vehicle_id' => $workOrder->vehicle_id,
                'feedback_type' => $data['feedback_type'] ?? 'delivery',
                'rating' => $data['rating'] ?? null,
                'status' => 'submitted',
                'customer_visible_note' => $data['customer_visible_note'] ?? null,
                'submitted_at' => now(),
                'ip_address' => $data['ip_address'] ?? null,
                'user_agent' => $data['user_agent'] ?? null,
            ]);

            $this->timeline->recordForWorkOrder($workOrder, 'feedback.submitted', 'Customer feedback submitted: ' . $feedback->feedback_number, [
                'customer_visible_note' => __('maintenance.customer_portal.feedback_received'),
                'payload' => ['feedback_id' => $feedback->id, 'rating' => $feedback->rating],
            ]);

            $this->notifications->create('feedback.submitted', 'Customer feedback submitted: ' . $feedback->feedback_number, [
                'branch_id' => $workOrder->branch_id,
                'severity' => ((int) ($feedback->rating ?? 5)) <= 2 ? 'warning' : 'info',
                'notifiable' => $feedback,
                'payload' => ['rating' => $feedback->rating],
            ]);

            $this->audit->record('feedback.submitted', 'customer_portal', [
                'branch_id' => $workOrder->branch_id,
                'auditable' => $feedback,
                'new_values' => [
                    'feedback_number' => $feedback->feedback_number,
                    'rating' => $feedback->rating,
                    'feedback_type' => $feedback->feedback_type,
                ],
                'ip_address' => $data['ip_address'] ?? null,
                'user_agent' => $data['user_agent'] ?? null,
            ]);

            return $feedback;
        });
    }
}
