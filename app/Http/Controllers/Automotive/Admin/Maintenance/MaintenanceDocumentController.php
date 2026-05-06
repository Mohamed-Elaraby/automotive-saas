<?php

namespace App\Http\Controllers\Automotive\Admin\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\MaintenanceApprovalRecord;
use App\Models\Maintenance\MaintenanceDelivery;
use App\Models\Maintenance\MaintenanceEstimate;
use App\Models\Maintenance\MaintenanceWarranty;
use App\Models\Maintenance\VehicleCheckIn;
use App\Models\WorkOrder;
use App\Services\Automotive\Maintenance\MaintenanceDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaintenanceDocumentController extends Controller
{
    public function __construct(protected MaintenanceDocumentService $documents)
    {
    }

    public function index(): View
    {
        return view('automotive.admin.maintenance.documents.index', [
            'documents' => $this->documents->recent(),
            'checkIns' => VehicleCheckIn::query()->with(['customer', 'vehicle'])->latest('id')->limit(50)->get(),
            'workOrders' => WorkOrder::query()->with(['customer', 'vehicle'])->latest('id')->limit(50)->get(),
            'estimates' => MaintenanceEstimate::query()->with(['customer', 'vehicle'])->latest('id')->limit(50)->get(),
            'approvalRecords' => MaintenanceApprovalRecord::query()->with(['estimate', 'customer', 'vehicle'])->latest('id')->limit(50)->get(),
            'deliveries' => MaintenanceDelivery::query()->with(['customer', 'vehicle'])->latest('id')->limit(50)->get(),
            'warranties' => MaintenanceWarranty::query()->with(['customer', 'vehicle'])->latest('id')->limit(50)->get(),
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'document_type' => ['required', 'in:maintenance_check_in,maintenance_work_order,maintenance_estimate,maintenance_approval_certificate,maintenance_delivery_report,maintenance_warranty_certificate'],
            'entity_id' => ['required', 'integer'],
            'language' => ['required', 'in:en,ar'],
        ]);

        $options = [
            'language' => $validated['language'],
            'direction' => $validated['language'] === 'ar' ? 'rtl' : 'ltr',
            'generated_by' => auth('automotive_admin')->id(),
        ];

        $document = match ($validated['document_type']) {
            'maintenance_check_in' => $this->documents->generateCheckIn(VehicleCheckIn::query()->findOrFail($validated['entity_id']), $options),
            'maintenance_work_order' => $this->documents->generateWorkOrder(WorkOrder::query()->findOrFail($validated['entity_id']), $options),
            'maintenance_estimate' => $this->documents->generateEstimate(MaintenanceEstimate::query()->findOrFail($validated['entity_id']), $options),
            'maintenance_approval_certificate' => $this->documents->generateApprovalCertificate(MaintenanceApprovalRecord::query()->findOrFail($validated['entity_id']), $options),
            'maintenance_delivery_report' => $this->documents->generateDelivery(MaintenanceDelivery::query()->findOrFail($validated['entity_id']), $options),
            'maintenance_warranty_certificate' => $this->documents->generateWarranty(MaintenanceWarranty::query()->findOrFail($validated['entity_id']), $options),
        };

        return redirect()
            ->route('automotive.admin.maintenance.documents.index')
            ->with('success', __('maintenance.messages.document_generated') . ' ' . $document->document_number);
    }

    public function generateCheckIn(Request $request, VehicleCheckIn $checkIn): RedirectResponse
    {
        $validated = $request->validate([
            'language' => ['required', 'in:en,ar'],
        ]);

        $document = $this->documents->generateCheckIn($checkIn, [
            'language' => $validated['language'],
            'direction' => $validated['language'] === 'ar' ? 'rtl' : 'ltr',
            'generated_by' => auth('automotive_admin')->id(),
        ]);

        return redirect()
            ->route('automotive.admin.maintenance.check-ins.show', $checkIn)
            ->with('success', __('maintenance.messages.document_generated') . ' ' . $document->document_number);
    }

    public function generateEstimate(Request $request, MaintenanceEstimate $estimate): RedirectResponse
    {
        $validated = $request->validate([
            'language' => ['required', 'in:en,ar'],
        ]);

        $document = $this->documents->generateEstimate($estimate, [
            'language' => $validated['language'],
            'direction' => $validated['language'] === 'ar' ? 'rtl' : 'ltr',
            'generated_by' => auth('automotive_admin')->id(),
        ]);

        return redirect()
            ->route('automotive.admin.maintenance.estimates.show', $estimate)
            ->with('success', __('maintenance.messages.document_generated') . ' ' . $document->document_number);
    }

    public function generateEstimateApproval(Request $request, MaintenanceEstimate $estimate): RedirectResponse
    {
        $validated = $request->validate([
            'language' => ['required', 'in:en,ar'],
        ]);

        $approval = $estimate->approvals()->latest('id')->firstOrFail();

        $document = $this->documents->generateApprovalCertificate($approval, [
            'language' => $validated['language'],
            'direction' => $validated['language'] === 'ar' ? 'rtl' : 'ltr',
            'generated_by' => auth('automotive_admin')->id(),
        ]);

        return redirect()
            ->route('automotive.admin.maintenance.estimates.show', $estimate)
            ->with('success', __('maintenance.messages.document_generated') . ' ' . $document->document_number);
    }
}
