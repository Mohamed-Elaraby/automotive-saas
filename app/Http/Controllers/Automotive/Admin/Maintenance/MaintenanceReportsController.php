<?php

namespace App\Http\Controllers\Automotive\Admin\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Maintenance\MaintenanceServiceCatalogItem;
use App\Services\Automotive\Maintenance\MaintenanceAdvancedOperationsService;
use App\Services\Automotive\Maintenance\MaintenanceReportingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MaintenanceReportsController extends Controller
{
    public function __construct(
        protected MaintenanceReportingService $reports,
        protected MaintenanceAdvancedOperationsService $advanced
    ) {
    }

    public function index(): View
    {
        return view('automotive.admin.maintenance.reports.index', [
            'dashboard' => $this->reports->dashboard(),
            'technicians' => $this->reports->technicianProductivity(),
            'advisors' => $this->reports->advisorPerformance(),
            'branches' => $this->reports->branchPerformance(),
            'topServices' => $this->reports->topServices(),
            'financial' => $this->reports->financialSummary(),
        ]);
    }

    public function advanced(): View
    {
        return view('automotive.admin.maintenance.advanced.index', [
            'slaPolicies' => $this->advanced->seedDefaultSlaPolicies() ?: \App\Models\Maintenance\MaintenanceSlaPolicy::query()->with('branch')->latest('id')->get(),
            'delayAlerts' => $this->advanced->delayAlerts(),
            'preventiveRules' => $this->advanced->preventiveRules(),
            'preventiveReminders' => $this->advanced->preventiveReminders(),
            'healthScores' => $this->advanced->healthScores(),
            'recommendations' => $this->advanced->recommendations(),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'serviceItems' => MaintenanceServiceCatalogItem::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function createPreventiveRule(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'service_catalog_item_id' => ['nullable', 'integer', 'exists:maintenance_service_catalog_items,id'],
            'name' => ['required', 'string', 'max:255'],
            'vehicle_make' => ['nullable', 'string', 'max:255'],
            'vehicle_model' => ['nullable', 'string', 'max:255'],
            'mileage_interval' => ['nullable', 'integer', 'min:1'],
            'months_interval' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->advanced->createPreventiveRule($validated);

        return back()->with('success', __('maintenance.messages.preventive_rule_created'));
    }

    public function refreshAdvanced(): RedirectResponse
    {
        $this->advanced->evaluateDelays();
        $this->advanced->generatePreventiveReminders();
        $this->advanced->calculateVehicleHealthScores();
        $this->advanced->generateServiceRecommendations();

        return back()->with('success', __('maintenance.messages.advanced_refreshed'));
    }

    public function export(string $report): StreamedResponse
    {
        $rows = $this->reports->exportRows($report);
        $filename = 'maintenance-' . $report . '-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
