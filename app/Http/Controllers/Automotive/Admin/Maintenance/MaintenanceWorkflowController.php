<?php

namespace App\Http\Controllers\Automotive\Admin\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Maintenance\MaintenanceInspection;
use App\Models\Maintenance\MaintenanceInspectionTemplate;
use App\Models\Maintenance\MaintenanceQcRecord;
use App\Models\Maintenance\MaintenanceServiceCatalogItem;
use App\Models\Maintenance\MaintenanceWorkOrderJob;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Services\Automotive\Maintenance\DiagnosisService;
use App\Services\Automotive\Maintenance\InspectionWorkflowService;
use App\Services\Automotive\Maintenance\QualityControlService;
use App\Services\Automotive\Maintenance\TechnicianJobService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaintenanceWorkflowController extends Controller
{
    public function __construct(
        protected InspectionWorkflowService $inspections,
        protected TechnicianJobService $jobs,
        protected DiagnosisService $diagnosis,
        protected QualityControlService $qc
    ) {
    }

    public function board(): View
    {
        return view('automotive.admin.maintenance.board', [
            'columns' => $this->jobs->boardData(),
        ]);
    }

    public function inspectionTemplatesIndex(): View
    {
        return view('automotive.admin.maintenance.inspection-templates.index', [
            'templates' => $this->inspections->templates(),
        ]);
    }

    public function inspectionTemplatesStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'inspection_type' => ['required', 'in:initial,diagnostic,pre_repair,final,qc,delivery'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:5000'],
            'items' => ['nullable', 'array'],
            'items.*.section' => ['nullable', 'string', 'max:255'],
            'items.*.label' => ['nullable', 'string', 'max:255'],
            'items.*.default_result' => ['nullable', 'in:good,needs_attention,urgent,not_checked,not_applicable'],
            'items.*.requires_photo' => ['nullable', 'boolean'],
        ]);

        $this->inspections->createTemplate($validated);

        return back()->with('success', __('maintenance.messages.inspection_template_created'));
    }

    public function inspectionsIndex(): View
    {
        return view('automotive.admin.maintenance.inspections.index', $this->workflowContext() + [
            'inspections' => $this->inspections->recentInspections(),
            'templates' => MaintenanceInspectionTemplate::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function inspectionsStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'work_order_id' => ['nullable', 'integer', 'exists:work_orders,id'],
            'check_in_id' => ['nullable', 'integer', 'exists:vehicle_check_ins,id'],
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'template_id' => ['nullable', 'integer', 'exists:maintenance_inspection_templates,id'],
            'inspection_type' => ['required', 'in:initial,diagnostic,pre_repair,final,qc,delivery'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'customer_visible_notes' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $inspection = $this->inspections->createInspection($validated + [
            'created_by' => auth('automotive_admin')->id(),
            'started_by' => auth('automotive_admin')->id(),
        ]);

        return redirect()
            ->route('automotive.admin.maintenance.inspections.show', $inspection)
            ->with('success', __('maintenance.messages.inspection_created'));
    }

    public function inspectionsShow(MaintenanceInspection $inspection): View
    {
        return view('automotive.admin.maintenance.inspections.show', [
            'inspection' => $inspection->load(['branch', 'workOrder', 'vehicle', 'customer', 'assignee', 'items.photo']),
        ]);
    }

    public function inspectionItemsUpdate(Request $request, MaintenanceInspection $inspection): RedirectResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.result' => ['required', 'in:good,needs_attention,urgent,not_checked,not_applicable'],
            'items.*.note' => ['nullable', 'string', 'max:2000'],
            'items.*.recommendation' => ['nullable', 'string', 'max:2000'],
            'items.*.estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.customer_approval_status' => ['nullable', 'in:pending,approved,rejected,not_required'],
        ]);

        $this->inspections->updateItems($inspection, $validated['items']);

        return back()->with('success', __('maintenance.messages.inspection_items_updated'));
    }

    public function inspectionsComplete(Request $request, MaintenanceInspection $inspection): RedirectResponse
    {
        $validated = $request->validate([
            'summary' => ['nullable', 'string', 'max:5000'],
            'customer_visible_notes' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->inspections->complete($inspection, $validated + [
            'completed_by' => auth('automotive_admin')->id(),
        ]);

        return back()->with('success', __('maintenance.messages.inspection_completed'));
    }

    public function jobsIndex(): View
    {
        return view('automotive.admin.maintenance.jobs.index', $this->workflowContext() + [
            'jobs' => $this->jobs->recentJobs(),
            'serviceItems' => MaintenanceServiceCatalogItem::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function jobsStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'work_order_id' => ['required', 'integer', 'exists:work_orders,id'],
            'service_catalog_item_id' => ['nullable', 'integer', 'exists:maintenance_service_catalog_items,id'],
            'assigned_technician_id' => ['nullable', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'estimated_minutes' => ['nullable', 'integer', 'min:0'],
            'customer_visible_notes' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $job = $this->jobs->create($validated + [
            'created_by' => auth('automotive_admin')->id(),
        ]);

        return redirect()
            ->route('automotive.admin.maintenance.jobs.show', $job)
            ->with('success', __('maintenance.messages.job_created'));
    }

    public function jobsShow(MaintenanceWorkOrderJob $job): View
    {
        return view('automotive.admin.maintenance.jobs.show', [
            'job' => $job->load(['workOrder.customer', 'workOrder.vehicle', 'workOrder.branch', 'technician', 'serviceCatalogItem', 'timeLogs.technician', 'attachments']),
        ]);
    }

    public function jobsStart(MaintenanceWorkOrderJob $job): RedirectResponse
    {
        $this->jobs->start($job, auth('automotive_admin')->id());

        return back()->with('success', __('maintenance.messages.job_started'));
    }

    public function jobsPause(Request $request, MaintenanceWorkOrderJob $job): RedirectResponse
    {
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        $this->jobs->pause($job, auth('automotive_admin')->id(), $validated['note'] ?? null);

        return back()->with('success', __('maintenance.messages.job_paused'));
    }

    public function jobsResume(MaintenanceWorkOrderJob $job): RedirectResponse
    {
        $this->jobs->resume($job, auth('automotive_admin')->id());

        return back()->with('success', __('maintenance.messages.job_resumed'));
    }

    public function jobsComplete(Request $request, MaintenanceWorkOrderJob $job): RedirectResponse
    {
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        $this->jobs->complete($job, auth('automotive_admin')->id(), $validated['note'] ?? null);

        return back()->with('success', __('maintenance.messages.job_completed'));
    }

    public function jobsBlocker(Request $request, MaintenanceWorkOrderJob $job): RedirectResponse
    {
        $validated = $request->validate(['note' => ['required', 'string', 'max:2000']]);

        $this->jobs->blocker($job, auth('automotive_admin')->id(), $validated['note']);

        return back()->with('success', __('maintenance.messages.job_blocked'));
    }

    public function diagnosisIndex(): View
    {
        return view('automotive.admin.maintenance.diagnosis.index', $this->workflowContext() + [
            'diagnosisRecords' => $this->diagnosis->recent(),
            'inspections' => MaintenanceInspection::query()->latest('id')->limit(100)->get(),
        ]);
    }

    public function diagnosisStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'work_order_id' => ['nullable', 'integer', 'exists:work_orders,id'],
            'inspection_id' => ['nullable', 'integer', 'exists:maintenance_inspections,id'],
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'complaint' => ['nullable', 'string', 'max:5000'],
            'symptoms' => ['nullable', 'string', 'max:5000'],
            'scanner_report' => ['nullable', 'string', 'max:5000'],
            'fault_codes' => ['nullable', 'string', 'max:1000'],
            'root_cause' => ['nullable', 'string', 'max:5000'],
            'recommended_repair' => ['nullable', 'string', 'max:5000'],
            'estimated_labor_hours' => ['nullable', 'numeric', 'min:0'],
            'estimated_minutes' => ['nullable', 'integer', 'min:0'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'technician_notes' => ['nullable', 'string', 'max:5000'],
            'diagnosed_by' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $this->diagnosis->create($validated + [
            'created_by' => auth('automotive_admin')->id(),
        ]);

        return back()->with('success', __('maintenance.messages.diagnosis_created'));
    }

    public function qcIndex(): View
    {
        return view('automotive.admin.maintenance.qc.index', $this->workflowContext() + [
            'qcRecords' => $this->qc->recent(),
        ]);
    }

    public function qcStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'work_order_id' => ['required', 'integer', 'exists:work_orders,id'],
            'qc_inspector_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $this->qc->create($validated + [
            'created_by' => auth('automotive_admin')->id(),
        ]);

        return back()->with('success', __('maintenance.messages.qc_created'));
    }

    public function qcComplete(Request $request, MaintenanceQcRecord $qcRecord): RedirectResponse
    {
        $validated = $request->validate([
            'result' => ['required', 'in:passed,failed,rework_required'],
            'items' => ['nullable', 'array'],
            'items.*.passed' => ['nullable', 'boolean'],
            'items.*.note' => ['nullable', 'string', 'max:2000'],
            'final_notes' => ['nullable', 'string', 'max:5000'],
            'rework_reason' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->qc->complete($qcRecord, $validated + [
            'completed_by' => auth('automotive_admin')->id(),
        ]);

        return back()->with('success', __('maintenance.messages.qc_completed'));
    }

    protected function workflowContext(): array
    {
        return [
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'users' => User::query()->orderBy('name')->get(),
            'workOrders' => WorkOrder::query()->with(['branch', 'customer', 'vehicle'])->latest('id')->limit(150)->get(),
            'vehicles' => Vehicle::query()->with('customer')->latest('id')->limit(150)->get(),
        ];
    }
}
