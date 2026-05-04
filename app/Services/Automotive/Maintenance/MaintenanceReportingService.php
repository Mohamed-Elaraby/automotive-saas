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
use App\Models\WorkOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MaintenanceReportingService
{
    public function dashboard(): array
    {
        return [
            'revenue' => (float) MaintenanceInvoice::query()->sum('grand_total'),
            'open_work_orders' => WorkOrder::query()->whereNotIn('status', ['delivered', 'closed', 'cancelled'])->count(),
            'vehicles_in_workshop' => WorkOrder::query()->whereNotIn('vehicle_status', ['delivered', 'closed'])->whereNotNull('vehicle_id')->distinct('vehicle_id')->count('vehicle_id'),
            'pending_approvals' => MaintenanceEstimate::query()->whereIn('status', ['draft', 'sent', 'viewed'])->count(),
            'pending_payments' => MaintenanceInvoice::query()->whereIn('payment_status', ['unpaid', 'partially_paid'])->count(),
            'qc_failures' => MaintenanceQcRecord::query()->whereIn('result', ['failed', 'rework_required'])->count(),
            'complaints' => MaintenanceComplaint::query()->where('status', 'open')->count(),
            'warranty_claims' => MaintenanceWarrantyClaim::query()->where('status', 'pending')->count(),
        ];
    }

    public function technicianProductivity(): Collection
    {
        return MaintenanceWorkOrderJob::query()
            ->select('assigned_technician_id')
            ->selectRaw('COUNT(*) as jobs_count')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count")
            ->selectRaw('AVG(NULLIF(actual_minutes, 0)) as average_minutes')
            ->selectRaw("SUM(CASE WHEN qc_status in ('failed','rework_required') THEN 1 ELSE 0 END) as rework_count")
            ->with('technician')
            ->whereNotNull('assigned_technician_id')
            ->groupBy('assigned_technician_id')
            ->orderByDesc('completed_count')
            ->limit(50)
            ->get();
    }

    public function advisorPerformance(): Collection
    {
        return WorkOrder::query()
            ->select('service_advisor_id')
            ->selectRaw('COUNT(*) as work_orders_count')
            ->selectRaw("SUM(CASE WHEN status in ('delivered','closed') THEN 1 ELSE 0 END) as closed_count")
            ->with('serviceAdvisor')
            ->whereNotNull('service_advisor_id')
            ->groupBy('service_advisor_id')
            ->orderByDesc('work_orders_count')
            ->limit(50)
            ->get();
    }

    public function branchPerformance(): Collection
    {
        return WorkOrder::query()
            ->select('branch_id')
            ->selectRaw('COUNT(*) as work_orders_count')
            ->selectRaw("SUM(CASE WHEN status in ('delivered','closed') THEN 1 ELSE 0 END) as delivered_count")
            ->with('branch')
            ->groupBy('branch_id')
            ->orderByDesc('work_orders_count')
            ->limit(50)
            ->get();
    }

    public function topServices(): Collection
    {
        return MaintenanceWorkOrderJob::query()
            ->select('service_catalog_item_id')
            ->selectRaw('COUNT(*) as jobs_count')
            ->with('serviceCatalogItem')
            ->whereNotNull('service_catalog_item_id')
            ->groupBy('service_catalog_item_id')
            ->orderByDesc('jobs_count')
            ->limit(20)
            ->get();
    }

    public function financialSummary(): array
    {
        return [
            'invoice_total' => (float) MaintenanceInvoice::query()->sum('grand_total'),
            'paid_total' => (float) MaintenanceInvoice::query()->sum('paid_amount'),
            'discount_total' => (float) MaintenanceInvoice::query()->sum('discount_total'),
            'tax_total' => (float) MaintenanceInvoice::query()->sum('tax_total'),
            'lost_sales_total' => (float) MaintenanceLostSale::query()->sum('amount'),
        ];
    }

    public function exportRows(string $report): array
    {
        return match ($report) {
            'technician-productivity' => [
                ['Technician', 'Jobs', 'Completed', 'Average Minutes', 'Rework'],
                ...$this->technicianProductivity()->map(fn ($row) => [
                    $row->technician?->name,
                    $row->jobs_count,
                    $row->completed_count,
                    round((float) $row->average_minutes, 2),
                    $row->rework_count,
                ])->all(),
            ],
            'branch-performance' => [
                ['Branch', 'Work Orders', 'Delivered'],
                ...$this->branchPerformance()->map(fn ($row) => [
                    $row->branch?->name,
                    $row->work_orders_count,
                    $row->delivered_count,
                ])->all(),
            ],
            default => [
                ['Metric', 'Value'],
                ...collect($this->financialSummary())->map(fn ($value, $key) => [$key, $value])->values()->all(),
            ],
        };
    }
}
