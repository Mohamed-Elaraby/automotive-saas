<?php

namespace App\Services\Automotive;

use App\Models\AccountingEvent;
use App\Models\WorkOrder;
use App\Services\Tenancy\TenantWorkspaceProductService;
use App\Services\Tenancy\WorkspaceProductFamilyResolver;

class WorkOrderAccountingHandoffService
{
    public function __construct(
        protected TenantWorkspaceProductService $tenantWorkspaceProductService,
        protected WorkspaceProductFamilyResolver $workspaceProductFamilyResolver,
        protected WorkshopWorkOrderService $workshopWorkOrderService
    ) {
    }

    public function shouldPostForTenant(string $tenantId): bool
    {
        $workspaceProducts = $this->tenantWorkspaceProductService->getWorkspaceProducts($tenantId);

        return $workspaceProducts->contains(function (array $workspaceProduct): bool {
            return $this->workspaceProductFamilyResolver->resolveFromWorkspaceProduct($workspaceProduct) === 'accounting'
                && ! empty($workspaceProduct['is_accessible']);
        });
    }

    public function postCompletedWorkOrder(WorkOrder $workOrder, ?int $createdBy = null): AccountingEvent
    {
        $summary = $this->workshopWorkOrderService->summarize($workOrder);

        return AccountingEvent::query()->updateOrCreate(
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
    }
}
