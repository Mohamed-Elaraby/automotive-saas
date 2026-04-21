<?php

namespace App\Services\Automotive;

use App\Models\AccountingEvent;
use App\Models\WorkOrder;
use App\Services\Tenancy\TenantWorkspaceProductService;
use App\Services\Tenancy\WorkspaceIntegrationHandoffService;
use App\Services\Tenancy\WorkspaceManifestService;
use App\Services\Tenancy\WorkspaceProductFamilyResolver;
use Illuminate\Support\Facades\DB;

class WorkOrderAccountingHandoffService
{
    public function __construct(
        protected TenantWorkspaceProductService $tenantWorkspaceProductService,
        protected WorkspaceManifestService $workspaceManifestService,
        protected WorkspaceProductFamilyResolver $workspaceProductFamilyResolver,
        protected WorkshopWorkOrderService $workshopWorkOrderService,
        protected WorkspaceIntegrationHandoffService $workspaceIntegrationHandoffService
    ) {
    }

    public function shouldPostForTenant(string $tenantId): bool
    {
        $workspaceProducts = $this->tenantWorkspaceProductService->getWorkspaceProducts($tenantId);

        return $this->workspaceManifestService->hasAccessibleFamily($workspaceProducts, 'accounting');
    }

    public function postCompletedWorkOrder(WorkOrder $workOrder, ?int $createdBy = null): ?AccountingEvent
    {
        $summary = $this->workshopWorkOrderService->summarize($workOrder);
        $handoff = $this->workspaceIntegrationHandoffService->start([
            'integration_key' => 'automotive-accounting',
            'event_name' => 'work_order.completed',
            'source_product' => 'automotive_service',
            'target_product' => 'accounting',
            'source_type' => WorkOrder::class,
            'source_id' => $workOrder->id,
            'payload' => [
                'work_order_number' => $workOrder->work_order_number,
                'labor_amount' => $summary['labor_subtotal'],
                'parts_amount' => $summary['parts_subtotal'],
                'total_amount' => $summary['grand_total'],
            ],
        ], $createdBy);

        if (! $this->shouldPostForTenant((string) tenant()->id)) {
            $this->workspaceIntegrationHandoffService->markSkipped(
                $handoff,
                'Accounting product is not active for this tenant workspace.'
            );

            return null;
        }

        try {
            return DB::transaction(function () use ($workOrder, $summary, $createdBy, $handoff): AccountingEvent {
                $event = AccountingEvent::query()->updateOrCreate(
                    [
                        'reference_type' => WorkOrder::class,
                        'reference_id' => $workOrder->id,
                        'event_type' => 'work_order_completed',
                    ],
                    [
                        'status' => 'posted',
                        'event_date' => $workOrder->closed_at ?: now(),
                        'currency' => 'USD',
                        'labor_amount' => $summary['labor_subtotal'],
                        'parts_amount' => $summary['parts_subtotal'],
                        'total_amount' => $summary['grand_total'],
                        'payload' => [
                            'work_order_number' => $workOrder->work_order_number,
                            'title' => $workOrder->title,
                            'customer_name' => $workOrder->customer?->name,
                            'vehicle' => $workOrder->vehicle
                                ? trim(($workOrder->vehicle->make ?? '') . ' ' . ($workOrder->vehicle->model ?? ''))
                                : null,
                            'lines_count' => $summary['lines_count'],
                        ],
                        'created_by' => $createdBy,
                    ]
                );

                $this->workspaceIntegrationHandoffService->markPosted($handoff, $event, [
                    'accounting_event_id' => $event->id,
                ]);

                return $event;
            });
        } catch (\Throwable $exception) {
            $this->workspaceIntegrationHandoffService->markFailed($handoff, $exception->getMessage());

            throw $exception;
        }
    }
}
