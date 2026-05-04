<?php

namespace App\Services\Automotive\Maintenance;

use App\Models\AccountingEvent;
use App\Models\Maintenance\MaintenanceInvoice;
use App\Models\Maintenance\MaintenancePartsRequest;
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

class MaintenanceIntegrationService
{
    public function __construct(
        protected MaintenanceNumberService $numbers,
        protected MaintenanceTimelineService $timeline,
        protected MaintenanceNotificationService $notifications,
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
