<?php

namespace App\Services\Automotive\Maintenance;

use App\Models\AccountingEvent;
use App\Models\Maintenance\MaintenanceEstimate;
use App\Models\Maintenance\MaintenanceInvoice;
use App\Models\Maintenance\MaintenancePartsRequest;
use App\Models\Maintenance\MaintenanceReceipt;
use App\Models\Maintenance\MaintenanceWorkOrderJob;
use App\Models\StockMovement;
use App\Models\WorkOrder;
use App\Models\WorkspaceIntegrationHandoff;
use App\Services\Automotive\WorkshopPartsIntegrationService;
use App\Services\Tenancy\TenantWorkspaceProductService;
use App\Services\Tenancy\WorkspaceIntegrationHandoffService;
use App\Services\Tenancy\WorkspaceManifestService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaintenanceIntegrationService
{
    public function __construct(
        protected MaintenanceNumberService $numbers,
        protected MaintenanceTimelineService $timeline,
        protected MaintenanceNotificationService $notifications,
        protected MaintenanceAuditService $audit,
        protected WorkspaceIntegrationHandoffService $handoffs,
        protected TenantWorkspaceProductService $tenantWorkspaceProducts,
        protected WorkspaceManifestService $workspaceManifest,
        protected WorkshopPartsIntegrationService $workshopParts
    ) {
    }

    public function dashboard(): array
    {
        return [
            'parts_active' => $this->hasWorkspaceFamily('parts_inventory'),
            'accounting_active' => $this->hasWorkspaceFamily('accounting'),
            'open_parts_requests' => MaintenancePartsRequest::query()
                ->whereNotIn('status', ['issued', 'cancelled'])
                ->count(),
            'pending_handoffs' => WorkspaceIntegrationHandoff::query()
                ->whereIn('integration_key', ['automotive-parts', 'automotive-accounting'])
                ->whereIn('status', ['pending', 'failed'])
                ->count(),
        ];
    }

    public function recentInvoices(int $limit = 100): Collection
    {
        return MaintenanceInvoice::query()
            ->with(['customer', 'vehicle', 'workOrder', 'estimate', 'receipts'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function recentReceipts(int $limit = 100): Collection
    {
        return MaintenanceReceipt::query()
            ->with(['invoice', 'customer', 'vehicle', 'workOrder'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function recentPartsRequests(int $limit = 50): Collection
    {
        return MaintenancePartsRequest::query()
            ->with(['branch', 'workOrder.customer', 'workOrder.vehicle', 'job', 'product', 'requester', 'handoff'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function recentHandoffs(int $limit = 50): Collection
    {
        return WorkspaceIntegrationHandoff::query()
            ->whereIn('integration_key', ['automotive-parts', 'automotive-accounting'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function createPartsRequest(array $data): MaintenancePartsRequest
    {
        return DB::transaction(function () use ($data): MaintenancePartsRequest {
            $job = null;
            if (! empty($data['job_id'])) {
                $job = MaintenanceWorkOrderJob::query()->with('workOrder')->find($data['job_id']);
            }

            $workOrder = WorkOrder::query()->find($data['work_order_id'] ?? $job?->work_order_id);
            $quantity = (float) ($data['quantity'] ?? 1);
            $unitPrice = (float) ($data['unit_price'] ?? 0);

            $request = MaintenancePartsRequest::query()->create([
                'request_number' => $this->numbers->next('maintenance_parts_requests', 'request_number', 'PRQ'),
                'branch_id' => $data['branch_id'] ?? $workOrder?->branch_id,
                'work_order_id' => $workOrder?->id,
                'job_id' => $job?->id,
                'vehicle_id' => $workOrder?->vehicle_id,
                'customer_id' => $workOrder?->customer_id,
                'product_id' => $data['product_id'] ?? null,
                'status' => 'requested',
                'source' => filled($data['product_id'] ?? null) ? 'inventory' : 'manual',
                'part_name' => $data['part_name'],
                'part_number' => $data['part_number'] ?? null,
                'supplier_name' => $data['supplier_name'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => round($quantity * $unitPrice, 2),
                'needed_by' => $data['needed_by'] ?? null,
                'notes' => $data['notes'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'requested_by' => $data['requested_by'] ?? null,
            ]);

            $this->createPartsHandoff($request);

            if ($workOrder) {
                $workOrder->forceFill([
                    'status' => 'waiting_parts',
                    'vehicle_status' => 'waiting_parts',
                ])->save();

                $this->timeline->recordForWorkOrder($workOrder, 'parts_requested', $request->request_number . ' - ' . $request->part_name, [
                    'created_by' => $data['requested_by'] ?? null,
                ]);
            }

            $this->notifications->create('parts.requested', 'Parts requested', [
                'branch_id' => $request->branch_id,
                'user_id' => null,
                'message' => $request->request_number . ' - ' . $request->part_name,
                'notifiable_type' => MaintenancePartsRequest::class,
                'notifiable_id' => $request->id,
                'payload' => ['status' => $request->status],
            ]);

            return $request->fresh(['workOrder.customer', 'workOrder.vehicle', 'job', 'handoff']);
        });
    }

    public function approvePartsRequest(MaintenancePartsRequest $request, ?int $userId): MaintenancePartsRequest
    {
        $request->forceFill([
            'status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
        ])->save();

        $this->recordPartsTimeline($request, 'parts_approved', $userId);

        return $request->fresh(['handoff', 'workOrder', 'job']);
    }

    public function issuePartsRequest(MaintenancePartsRequest $request, ?int $userId): MaintenancePartsRequest
    {
        return DB::transaction(function () use ($request, $userId): MaintenancePartsRequest {
            $stockMovement = null;

            if ($this->hasWorkspaceFamily('parts_inventory') && $request->product_id && $request->branch_id && $request->work_order_id) {
                $stockMovement = $this->workshopParts->consumePart([
                    'branch_id' => $request->branch_id,
                    'product_id' => $request->product_id,
                    'quantity' => $request->quantity,
                    'work_order_id' => $request->work_order_id,
                    'notes' => 'Issued for maintenance parts request ' . $request->request_number,
                    'created_by' => $userId,
                ]);
            }

            $request->forceFill([
                'status' => 'issued',
                'stock_movement_id' => $stockMovement?->id ?? $request->stock_movement_id,
                'fulfilled_at' => now(),
            ])->save();

            if ($request->handoff && $request->handoff->status !== 'skipped') {
                $this->handoffs->markPosted($request->handoff, $stockMovement, [
                    'issued_at' => now()->toISOString(),
                    'stock_movement_id' => $stockMovement?->id,
                ]);
            }

            $this->recordPartsTimeline($request, 'parts_issued', $userId);

            return $request->fresh(['handoff', 'stockMovement', 'workOrder', 'job']);
        });
    }

    public function createInvoice(array $data): MaintenanceInvoice
    {
        return DB::transaction(function () use ($data): MaintenanceInvoice {
            $estimate = null;
            if (! empty($data['estimate_id'])) {
                $estimate = MaintenanceEstimate::query()->with(['lines', 'workOrder'])->findOrFail($data['estimate_id']);
            }

            $workOrder = null;
            if (! empty($data['work_order_id'])) {
                $workOrder = WorkOrder::query()->with(['lines', 'customer', 'vehicle'])->findOrFail($data['work_order_id']);
            } elseif ($estimate?->workOrder) {
                $workOrder = $estimate->workOrder;
            }

            if (! $estimate && ! $workOrder) {
                throw ValidationException::withMessages([
                    'work_order_id' => __('maintenance.messages.invoice_source_required'),
                ]);
            }

            $existing = $this->findExistingInvoice($estimate, $workOrder);
            if ($existing) {
                return $existing->fresh(['customer', 'vehicle', 'workOrder', 'estimate', 'receipts']);
            }

            $totals = $this->invoiceTotals($estimate, $workOrder);
            $branchId = $data['branch_id'] ?? $estimate?->branch_id ?? $workOrder?->branch_id;
            $customerId = $estimate?->customer_id ?? $workOrder?->customer_id;
            $vehicleId = $estimate?->vehicle_id ?? $workOrder?->vehicle_id;

            $invoice = MaintenanceInvoice::query()->create([
                'invoice_number' => $this->numbers->next('maintenance_invoices', 'invoice_number', 'INV'),
                'branch_id' => $branchId,
                'customer_id' => $customerId,
                'vehicle_id' => $vehicleId,
                'work_order_id' => $workOrder?->id,
                'estimate_id' => $estimate?->id,
                'status' => 'issued',
                'payment_status' => 'unpaid',
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount_total'],
                'tax_total' => $totals['tax_total'],
                'grand_total' => $totals['grand_total'],
                'paid_amount' => 0,
                'issued_at' => $data['issued_at'] ?? now(),
                'created_by' => $data['created_by'] ?? null,
            ]);

            if ($workOrder) {
                $workOrder->forceFill(['payment_status' => 'unpaid'])->save();

                $this->timeline->recordForWorkOrder($workOrder, 'invoice.created', $invoice->invoice_number, [
                    'created_by' => $data['created_by'] ?? null,
                    'customer_visible_note' => __('maintenance.messages.invoice_created'),
                    'payload' => [
                        'invoice_id' => $invoice->id,
                        'grand_total' => $invoice->grand_total,
                    ],
                ]);
            }

            $this->notifications->create('invoice.created', 'Invoice created', [
                'branch_id' => $invoice->branch_id,
                'message' => $invoice->invoice_number . ' - ' . number_format((float) $invoice->grand_total, 2),
                'notifiable_type' => MaintenanceInvoice::class,
                'notifiable_id' => $invoice->id,
                'payload' => [
                    'invoice_number' => $invoice->invoice_number,
                    'payment_status' => $invoice->payment_status,
                ],
            ]);

            $this->audit->record('invoice.created', 'billing', [
                'branch_id' => $invoice->branch_id,
                'user_id' => $data['created_by'] ?? null,
                'auditable' => $invoice,
                'new_values' => [
                    'invoice_number' => $invoice->invoice_number,
                    'grand_total' => $invoice->grand_total,
                    'payment_status' => $invoice->payment_status,
                ],
            ]);

            try {
                $this->postInvoiceToAccounting($invoice, $data['created_by'] ?? null);
            } catch (\Throwable) {
                // Optional accounting handoff failures must not block maintenance invoicing.
            }

            return $invoice->fresh(['customer', 'vehicle', 'workOrder', 'estimate', 'receipts']);
        });
    }

    public function recordReceipt(MaintenanceInvoice $invoice, array $data): MaintenanceReceipt
    {
        return DB::transaction(function () use ($invoice, $data): MaintenanceReceipt {
            $invoice->loadMissing(['workOrder']);
            $amount = round((float) $data['amount'], 2);

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => __('maintenance.messages.receipt_amount_required'),
                ]);
            }

            if ($invoice->payment_status === 'cancelled') {
                throw ValidationException::withMessages([
                    'amount' => __('maintenance.messages.cancelled_invoice_payment_blocked'),
                ]);
            }

            $receipt = MaintenanceReceipt::query()->create([
                'receipt_number' => $this->numbers->next('maintenance_receipts', 'receipt_number', 'REC'),
                'branch_id' => $invoice->branch_id,
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'vehicle_id' => $invoice->vehicle_id,
                'work_order_id' => $invoice->work_order_id,
                'payment_method' => $data['payment_method'] ?? 'cash',
                'amount' => $amount,
                'currency' => $data['currency'] ?? 'USD',
                'reference_number' => $data['reference_number'] ?? null,
                'received_at' => $data['received_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);

            $oldPaymentStatus = $invoice->payment_status;
            $oldPaidAmount = $invoice->paid_amount;
            $paidAmount = round(((float) $invoice->paid_amount) + $amount, 2);
            $grandTotal = (float) $invoice->grand_total;
            $paymentStatus = $paidAmount <= 0
                ? 'unpaid'
                : ($paidAmount + 0.00001 >= $grandTotal ? 'paid' : 'partially_paid');

            $invoice->forceFill([
                'paid_amount' => min($paidAmount, $grandTotal),
                'payment_status' => $paymentStatus,
                'paid_at' => $paymentStatus === 'paid' ? ($data['received_at'] ?? now()) : $invoice->paid_at,
            ])->save();

            if ($invoice->workOrder) {
                $invoice->workOrder->forceFill(['payment_status' => $paymentStatus])->save();

                $this->timeline->recordForWorkOrder($invoice->workOrder, 'payment.received', $receipt->receipt_number, [
                    'created_by' => $data['created_by'] ?? null,
                    'customer_visible_note' => __('maintenance.messages.payment_received'),
                    'payload' => [
                        'invoice_id' => $invoice->id,
                        'receipt_id' => $receipt->id,
                        'amount' => $receipt->amount,
                        'payment_status' => $paymentStatus,
                    ],
                ]);
            }

            $this->notifications->create($paymentStatus === 'paid' ? 'invoice.paid' : 'payment.received', 'Payment received', [
                'branch_id' => $receipt->branch_id,
                'message' => $receipt->receipt_number . ' - ' . number_format((float) $receipt->amount, 2),
                'notifiable_type' => MaintenanceReceipt::class,
                'notifiable_id' => $receipt->id,
                'payload' => [
                    'receipt_number' => $receipt->receipt_number,
                    'invoice_number' => $invoice->invoice_number,
                    'payment_status' => $paymentStatus,
                ],
            ]);

            $this->audit->record('payment.received', 'billing', [
                'branch_id' => $receipt->branch_id,
                'user_id' => $data['created_by'] ?? null,
                'auditable' => $receipt,
                'old_values' => [
                    'invoice_payment_status' => $oldPaymentStatus,
                    'invoice_paid_amount' => $oldPaidAmount,
                ],
                'new_values' => [
                    'receipt_number' => $receipt->receipt_number,
                    'amount' => $receipt->amount,
                    'invoice_payment_status' => $invoice->payment_status,
                    'invoice_paid_amount' => $invoice->paid_amount,
                ],
            ]);

            try {
                $this->postPaymentToAccounting($receipt, $data['created_by'] ?? null);
            } catch (\Throwable) {
                // Optional accounting handoff failures must not block operational receipt capture.
            }

            return $receipt->fresh(['invoice', 'customer', 'vehicle', 'workOrder']);
        });
    }

    public function postInvoiceToAccounting(MaintenanceInvoice $invoice, ?int $userId): ?AccountingEvent
    {
        $handoff = $this->handoffs->start([
            'integration_key' => 'automotive-accounting',
            'event_name' => 'invoice.created',
            'source_product' => 'automotive_service',
            'target_product' => 'accounting',
            'source_type' => MaintenanceInvoice::class,
            'source_id' => $invoice->id,
            'payload' => $this->invoicePayload($invoice),
        ], $userId);

        if (! $this->hasWorkspaceFamily('accounting')) {
            $this->handoffs->markSkipped($handoff, 'Accounting product is not active for this tenant workspace.');

            return null;
        }

        try {
            return DB::transaction(function () use ($invoice, $userId, $handoff): AccountingEvent {
                $event = AccountingEvent::query()->updateOrCreate(
                    [
                        'reference_type' => MaintenanceInvoice::class,
                        'reference_id' => $invoice->id,
                        'event_type' => 'maintenance_invoice_created',
                    ],
                    [
                        'status' => 'posted',
                        'event_date' => $invoice->issued_at ?: now(),
                        'currency' => 'USD',
                        'labor_amount' => $invoice->subtotal,
                        'parts_amount' => 0,
                        'total_amount' => $invoice->grand_total,
                        'payload' => $this->invoicePayload($invoice),
                        'created_by' => $userId,
                    ]
                );

                $this->handoffs->markPosted($handoff, $event, [
                    'accounting_event_id' => $event->id,
                ]);

                return $event;
            });
        } catch (\Throwable $exception) {
            $this->handoffs->markFailed($handoff, $exception->getMessage());

            throw $exception;
        }
    }

    public function postPaymentToAccounting(MaintenanceReceipt $receipt, ?int $userId): ?AccountingEvent
    {
        $receipt->loadMissing(['invoice', 'branch', 'customer', 'vehicle', 'workOrder']);

        $handoff = $this->handoffs->start([
            'integration_key' => 'automotive-accounting',
            'event_name' => 'payment.received',
            'source_product' => 'automotive_service',
            'target_product' => 'accounting',
            'source_type' => MaintenanceReceipt::class,
            'source_id' => $receipt->id,
            'payload' => $this->receiptPayload($receipt),
        ], $userId);

        if (! $this->hasWorkspaceFamily('accounting')) {
            $this->handoffs->markSkipped($handoff, 'Accounting product is not active for this tenant workspace.');

            return null;
        }

        try {
            return DB::transaction(function () use ($receipt, $userId, $handoff): AccountingEvent {
                $event = AccountingEvent::query()->updateOrCreate(
                    [
                        'reference_type' => MaintenanceReceipt::class,
                        'reference_id' => $receipt->id,
                        'event_type' => 'maintenance_payment_received',
                    ],
                    [
                        'status' => 'posted',
                        'event_date' => $receipt->received_at ?: now(),
                        'currency' => $receipt->currency,
                        'labor_amount' => 0,
                        'parts_amount' => 0,
                        'total_amount' => $receipt->amount,
                        'payload' => $this->receiptPayload($receipt),
                        'created_by' => $userId,
                    ]
                );

                $this->handoffs->markPosted($handoff, $event, [
                    'accounting_event_id' => $event->id,
                ]);

                return $event;
            });
        } catch (\Throwable $exception) {
            $this->handoffs->markFailed($handoff, $exception->getMessage());

            throw $exception;
        }
    }

    protected function createPartsHandoff(MaintenancePartsRequest $request): void
    {
        $handoff = $this->handoffs->start([
            'integration_key' => 'automotive-parts',
            'event_name' => 'parts.requested',
            'source_product' => 'automotive_service',
            'target_product' => 'parts_inventory',
            'source_type' => MaintenancePartsRequest::class,
            'source_id' => $request->id,
            'payload' => [
                'request_number' => $request->request_number,
                'work_order_id' => $request->work_order_id,
                'job_id' => $request->job_id,
                'product_id' => $request->product_id,
                'part_name' => $request->part_name,
                'quantity' => $request->quantity,
                'source' => $request->source,
            ],
        ], $request->requested_by);

        if (! $this->hasWorkspaceFamily('parts_inventory')) {
            $this->handoffs->markSkipped($handoff, 'Spare parts product is not active for this tenant workspace.');
        }

        $request->forceFill(['handoff_id' => $handoff->id])->save();
    }

    protected function invoicePayload(MaintenanceInvoice $invoice): array
    {
        $invoice->loadMissing(['branch', 'customer', 'vehicle', 'workOrder']);

        return [
            'invoice_number' => $invoice->invoice_number,
            'branch_id' => $invoice->branch_id,
            'branch_name' => $invoice->branch?->name,
            'customer_name' => $invoice->customer?->name,
            'vehicle' => $invoice->vehicle ? trim(($invoice->vehicle->make ?? '') . ' ' . ($invoice->vehicle->model ?? '')) : null,
            'work_order_number' => $invoice->workOrder?->work_order_number,
            'subtotal' => $invoice->subtotal,
            'discount_total' => $invoice->discount_total,
            'tax_total' => $invoice->tax_total,
            'grand_total' => $invoice->grand_total,
            'paid_amount' => $invoice->paid_amount,
            'payment_status' => $invoice->payment_status,
        ];
    }

    protected function receiptPayload(MaintenanceReceipt $receipt): array
    {
        return [
            'receipt_number' => $receipt->receipt_number,
            'invoice_number' => $receipt->invoice?->invoice_number,
            'branch_id' => $receipt->branch_id,
            'branch_name' => $receipt->branch?->name,
            'customer_name' => $receipt->customer?->name,
            'vehicle' => $receipt->vehicle ? trim(($receipt->vehicle->make ?? '') . ' ' . ($receipt->vehicle->model ?? '')) : null,
            'work_order_number' => $receipt->workOrder?->work_order_number,
            'payment_method' => $receipt->payment_method,
            'amount' => $receipt->amount,
            'currency' => $receipt->currency,
            'reference_number' => $receipt->reference_number,
            'received_at' => optional($receipt->received_at)->toISOString(),
        ];
    }

    protected function findExistingInvoice(?MaintenanceEstimate $estimate, ?WorkOrder $workOrder): ?MaintenanceInvoice
    {
        if ($estimate) {
            $invoice = MaintenanceInvoice::query()
                ->where('estimate_id', $estimate->id)
                ->where('status', '!=', 'cancelled')
                ->first();

            if ($invoice) {
                return $invoice;
            }
        }

        if (! $estimate && $workOrder) {
            return MaintenanceInvoice::query()
                ->where('work_order_id', $workOrder->id)
                ->whereNull('estimate_id')
                ->where('status', '!=', 'cancelled')
                ->first();
        }

        return null;
    }

    protected function invoiceTotals(?MaintenanceEstimate $estimate, ?WorkOrder $workOrder): array
    {
        if ($estimate) {
            return [
                'subtotal' => (float) $estimate->subtotal,
                'discount_total' => (float) $estimate->discount_total,
                'tax_total' => (float) $estimate->tax_total,
                'grand_total' => (float) $estimate->grand_total,
            ];
        }

        $subtotal = (float) ($workOrder?->lines?->sum('total_price') ?? 0);

        return [
            'subtotal' => round($subtotal, 2),
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => round($subtotal, 2),
        ];
    }

    protected function hasWorkspaceFamily(string $family): bool
    {
        $tenant = function_exists('tenant') ? tenant() : null;
        $tenantId = (string) ($tenant?->id ?? '');

        if ($tenantId === '') {
            return false;
        }

        $workspaceProducts = $this->tenantWorkspaceProducts->getWorkspaceProducts($tenantId);

        return $this->workspaceManifest->hasAccessibleFamily($workspaceProducts, $family);
    }

    protected function recordPartsTimeline(MaintenancePartsRequest $request, string $event, ?int $userId): void
    {
        if (! $request->workOrder) {
            return;
        }

        $this->timeline->recordForWorkOrder($request->workOrder, $event, $request->request_number . ' - ' . $request->part_name, [
            'created_by' => $userId,
        ]);
    }
}
