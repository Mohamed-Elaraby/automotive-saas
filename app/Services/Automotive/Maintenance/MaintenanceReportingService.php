<?php

namespace App\Services\Automotive\Maintenance;

use App\Models\Maintenance\MaintenanceComplaint;
use App\Models\Maintenance\MaintenanceDelivery;
use App\Models\Maintenance\MaintenanceEstimate;
use App\Models\Maintenance\MaintenanceInvoice;
use App\Models\Maintenance\MaintenanceLostSale;
use App\Models\Maintenance\MaintenanceQcRecord;
use App\Models\Maintenance\MaintenanceWarrantyClaim;
use App\Models\Maintenance\MaintenanceWorkOrderJob;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Tenancy\BranchScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MaintenanceReportingService
{
    public function __construct(
        protected BranchScopeService $branchScope
    ) {
    }

    public function dashboard(?User $user = null): array
    {
        $invoiceQuery = MaintenanceInvoice::query();
        $workOrderQuery = WorkOrder::query();
        $vehicleWorkshopQuery = WorkOrder::query();
        $estimateQuery = MaintenanceEstimate::query();
        $paymentQuery = MaintenanceInvoice::query();
        $qcQuery = MaintenanceQcRecord::query();
        $complaintQuery = MaintenanceComplaint::query();
        $warrantyClaimQuery = MaintenanceWarrantyClaim::query();

        if ($user) {
            foreach ([$invoiceQuery, $workOrderQuery, $vehicleWorkshopQuery, $estimateQuery, $paymentQuery, $qcQuery, $complaintQuery] as $query) {
                $this->branchScope->applyAllowedBranches($query, $user, 'automotive_service');
            }

            $warrantyClaimQuery->whereHas('originalWorkOrder', function (Builder $workOrderQuery) use ($user): void {
                $this->branchScope->applyAllowedBranches($workOrderQuery, $user, 'automotive_service', 'work_orders.branch_id');
            });
        }

        return [
            'revenue' => (float) $invoiceQuery->sum('grand_total'),
            'open_work_orders' => $workOrderQuery->whereNotIn('status', ['delivered', 'closed', 'cancelled'])->count(),
            'vehicles_in_workshop' => $vehicleWorkshopQuery->whereNotIn('vehicle_status', ['delivered', 'closed'])->whereNotNull('vehicle_id')->distinct('vehicle_id')->count('vehicle_id'),
            'pending_approvals' => $estimateQuery->whereIn('status', ['draft', 'sent', 'viewed'])->count(),
            'pending_payments' => $paymentQuery->whereIn('payment_status', ['unpaid', 'partially_paid'])->count(),
            'qc_failures' => $qcQuery->whereIn('result', ['failed', 'rework_required'])->count(),
            'complaints' => $complaintQuery->where('status', 'open')->count(),
            'warranty_claims' => $warrantyClaimQuery->where('status', 'pending')->count(),
        ];
    }

    public function technicianProductivity(?User $user = null): Collection
    {
        return MaintenanceWorkOrderJob::query()
            ->select('assigned_technician_id')
            ->selectRaw('COUNT(*) as jobs_count')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count")
            ->selectRaw('AVG(NULLIF(actual_minutes, 0)) as average_minutes')
            ->selectRaw("SUM(CASE WHEN qc_status in ('failed','rework_required') THEN 1 ELSE 0 END) as rework_count")
            ->with('technician')
            ->whereHas('workOrder', function (Builder $workOrderQuery) use ($user): void {
                if ($user) {
                    $this->branchScope->applyAllowedBranches($workOrderQuery, $user, 'automotive_service', 'work_orders.branch_id');
                }
            })
            ->whereNotNull('assigned_technician_id')
            ->groupBy('assigned_technician_id')
            ->orderByDesc('completed_count')
            ->limit(50)
            ->get();
    }

    public function advisorPerformance(?User $user = null): Collection
    {
        $query = WorkOrder::query()
            ->select('service_advisor_id')
            ->selectRaw('COUNT(*) as work_orders_count')
            ->selectRaw("SUM(CASE WHEN status in ('delivered','closed') THEN 1 ELSE 0 END) as closed_count")
            ->with('serviceAdvisor')
            ->whereNotNull('service_advisor_id')
            ->groupBy('service_advisor_id')
            ->orderByDesc('work_orders_count');

        if ($user) {
            $this->branchScope->applyAllowedBranches($query, $user, 'automotive_service');
        }

        return $query->limit(50)->get();
    }

    public function branchPerformance(?User $user = null): Collection
    {
        $query = WorkOrder::query()
            ->select('branch_id')
            ->selectRaw('COUNT(*) as work_orders_count')
            ->selectRaw("SUM(CASE WHEN status in ('delivered','closed') THEN 1 ELSE 0 END) as delivered_count")
            ->with('branch')
            ->groupBy('branch_id')
            ->orderByDesc('work_orders_count');

        if ($user) {
            $this->branchScope->applyAllowedBranches($query, $user, 'automotive_service');
        }

        return $query->limit(50)->get();
    }

    public function topServices(?User $user = null): Collection
    {
        return MaintenanceWorkOrderJob::query()
            ->select('service_catalog_item_id')
            ->selectRaw('COUNT(*) as jobs_count')
            ->with('serviceCatalogItem')
            ->whereHas('workOrder', function (Builder $workOrderQuery) use ($user): void {
                if ($user) {
                    $this->branchScope->applyAllowedBranches($workOrderQuery, $user, 'automotive_service', 'work_orders.branch_id');
                }
            })
            ->whereNotNull('service_catalog_item_id')
            ->groupBy('service_catalog_item_id')
            ->orderByDesc('jobs_count')
            ->limit(20)
            ->get();
    }

    public function financialSummary(?User $user = null): array
    {
        $invoiceQuery = MaintenanceInvoice::query();
        $paidQuery = MaintenanceInvoice::query();
        $discountQuery = MaintenanceInvoice::query();
        $taxQuery = MaintenanceInvoice::query();
        $lostSalesQuery = MaintenanceLostSale::query();

        if ($user) {
            foreach ([$invoiceQuery, $paidQuery, $discountQuery, $taxQuery, $lostSalesQuery] as $query) {
                $this->branchScope->applyAllowedBranches($query, $user, 'automotive_service');
            }
        }

        return [
            'invoice_total' => (float) $invoiceQuery->sum('grand_total'),
            'paid_total' => (float) $paidQuery->sum('paid_amount'),
            'discount_total' => (float) $discountQuery->sum('discount_total'),
            'tax_total' => (float) $taxQuery->sum('tax_total'),
            'lost_sales_total' => (float) $lostSalesQuery->sum('amount'),
        ];
    }

    public function exportRows(string $report, ?User $user = null): array
    {
        return match ($report) {
            'technician-productivity' => [
                ['Technician', 'Jobs', 'Completed', 'Average Minutes', 'Rework'],
                ...$this->technicianProductivity($user)->map(fn ($row) => [
                    $row->technician?->name,
                    $row->jobs_count,
                    $row->completed_count,
                    round((float) $row->average_minutes, 2),
                    $row->rework_count,
                ])->all(),
            ],
            'branch-performance' => [
                ['Branch', 'Work Orders', 'Delivered'],
                ...$this->branchPerformance($user)->map(fn ($row) => [
                    $row->branch?->name,
                    $row->work_orders_count,
                    $row->delivered_count,
                ])->all(),
            ],
            default => [
                ['Metric', 'Value'],
                ...collect($this->financialSummary($user))->map(fn ($value, $key) => [$key, $value])->values()->all(),
            ],
        };
    }
}
