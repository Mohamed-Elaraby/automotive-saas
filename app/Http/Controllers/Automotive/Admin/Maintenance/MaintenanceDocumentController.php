<?php

namespace App\Http\Controllers\Automotive\Admin\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\MaintenanceApprovalRecord;
use App\Models\Maintenance\MaintenanceDelivery;
use App\Models\Maintenance\MaintenanceEstimate;
use App\Models\Maintenance\MaintenanceInvoice;
use App\Models\Maintenance\MaintenanceReceipt;
use App\Models\Maintenance\MaintenanceWarranty;
use App\Models\Maintenance\VehicleCheckIn;
use App\Models\WorkOrder;
use App\Services\Automotive\Maintenance\MaintenanceDocumentService;
use App\Services\Tenancy\BranchScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class MaintenanceDocumentController extends Controller
{
    public function __construct(
        protected MaintenanceDocumentService $documents,
        protected BranchScopeService $branchScope
    )
    {
    }

    public function index(): View
    {
        $user = auth('automotive_admin')->user();

        return view('automotive.admin.maintenance.documents.index', [
            'documents' => $this->documents->recent(50, $user),
            'checkIns' => VehicleCheckIn::query()->with(['customer', 'vehicle'])->visibleToUser($user, 'automotive_service')->latest('id')->limit(50)->get(),
            'workOrders' => WorkOrder::query()->with(['customer', 'vehicle'])->visibleToUser($user, 'automotive_service')->latest('id')->limit(50)->get(),
            'estimates' => MaintenanceEstimate::query()->with(['customer', 'vehicle'])->visibleToUser($user, 'automotive_service')->latest('id')->limit(50)->get(),
            'approvalRecords' => MaintenanceApprovalRecord::query()->with(['estimate', 'customer', 'vehicle'])->visibleToUser($user, 'automotive_service')->latest('id')->limit(50)->get(),
            'invoices' => MaintenanceInvoice::query()->with(['customer', 'vehicle'])->visibleToUser($user, 'automotive_service')->latest('id')->limit(50)->get(),
            'receipts' => MaintenanceReceipt::query()->with(['invoice', 'customer', 'vehicle'])->visibleToUser($user, 'automotive_service')->latest('id')->limit(50)->get(),
            'deliveries' => MaintenanceDelivery::query()->with(['customer', 'vehicle'])->visibleToUser($user, 'automotive_service')->latest('id')->limit(50)->get(),
            'warranties' => MaintenanceWarranty::query()->with(['customer', 'vehicle'])->visibleToUser($user, 'automotive_service')->latest('id')->limit(50)->get(),
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'document_type' => ['required', 'in:maintenance_check_in,maintenance_work_order,maintenance_estimate,maintenance_approval_certificate,maintenance_invoice,maintenance_receipt,maintenance_delivery_report,maintenance_warranty_certificate'],
            'entity_id' => ['required', 'integer'],
            'language' => ['required', 'in:en,ar'],
        ]);

        $options = [
            'language' => $validated['language'],
            'direction' => $validated['language'] === 'ar' ? 'rtl' : 'ltr',
            'generated_by' => auth('automotive_admin')->id(),
        ];

        [$documentable, $generator] = match ($validated['document_type']) {
            'maintenance_check_in' => [VehicleCheckIn::query()->findOrFail($validated['entity_id']), 'generateCheckIn'],
            'maintenance_work_order' => [WorkOrder::query()->findOrFail($validated['entity_id']), 'generateWorkOrder'],
            'maintenance_estimate' => [MaintenanceEstimate::query()->findOrFail($validated['entity_id']), 'generateEstimate'],
            'maintenance_approval_certificate' => [MaintenanceApprovalRecord::query()->findOrFail($validated['entity_id']), 'generateApprovalCertificate'],
            'maintenance_invoice' => [MaintenanceInvoice::query()->findOrFail($validated['entity_id']), 'generateInvoice'],
            'maintenance_receipt' => [MaintenanceReceipt::query()->findOrFail($validated['entity_id']), 'generateReceipt'],
            'maintenance_delivery_report' => [MaintenanceDelivery::query()->findOrFail($validated['entity_id']), 'generateDelivery'],
            'maintenance_warranty_certificate' => [MaintenanceWarranty::query()->findOrFail($validated['entity_id']), 'generateWarranty'],
        };

        $this->assertDocumentableBranch($documentable);

        $document = $this->documents->{$generator}($documentable, $options);

        return redirect()
            ->route('automotive.admin.maintenance.documents.index')
            ->with('success', __('maintenance.messages.document_generated') . ' ' . $document->document_number);
    }

    public function generateCheckIn(Request $request, VehicleCheckIn $checkIn): RedirectResponse
    {
        $this->assertDocumentableBranch($checkIn);

        $validated = $request->validate([
            'language' => ['required', 'in:en,ar'],
        ]);

        try {
            $document = $this->documents->generateCheckIn($checkIn, [
                'language' => $validated['language'],
                'direction' => $validated['language'] === 'ar' ? 'rtl' : 'ltr',
                'generated_by' => auth('automotive_admin')->id(),
            ]);
        } catch (Throwable $exception) {
            Log::error('Maintenance check-in PDF generation failed.', [
                'check_in_id' => $checkIn->id,
                'check_in_number' => $checkIn->check_in_number,
                'language' => $validated['language'],
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return back()->withErrors([
                'document' => __('maintenance.messages.document_generation_failed'),
            ]);
        }

        return redirect()
            ->route('automotive.admin.maintenance.check-ins.show', $checkIn)
            ->with('success', __('maintenance.messages.document_generated') . ' ' . $document->document_number);
    }

    public function generateEstimate(Request $request, MaintenanceEstimate $estimate): RedirectResponse
    {
        $this->assertDocumentableBranch($estimate);

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
        $this->assertDocumentableBranch($estimate);

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

    public function generateInvoice(Request $request, MaintenanceInvoice $invoice): RedirectResponse
    {
        $this->assertDocumentableBranch($invoice);

        $validated = $request->validate([
            'language' => ['required', 'in:en,ar'],
        ]);

        $document = $this->documents->generateInvoice($invoice, [
            'language' => $validated['language'],
            'direction' => $validated['language'] === 'ar' ? 'rtl' : 'ltr',
            'generated_by' => auth('automotive_admin')->id(),
        ]);

        return redirect()
            ->route('automotive.admin.maintenance.integrations.index')
            ->with('success', __('maintenance.messages.document_generated') . ' ' . $document->document_number);
    }

    public function generateReceipt(Request $request, MaintenanceReceipt $receipt): RedirectResponse
    {
        $this->assertDocumentableBranch($receipt);

        $validated = $request->validate([
            'language' => ['required', 'in:en,ar'],
        ]);

        $document = $this->documents->generateReceipt($receipt, [
            'language' => $validated['language'],
            'direction' => $validated['language'] === 'ar' ? 'rtl' : 'ltr',
            'generated_by' => auth('automotive_admin')->id(),
        ]);

        return redirect()
            ->route('automotive.admin.maintenance.integrations.index')
            ->with('success', __('maintenance.messages.document_generated') . ' ' . $document->document_number);
    }

    protected function assertDocumentableBranch(object $documentable): void
    {
        $branchId = $documentable->branch_id ?? $documentable->workOrder?->branch_id ?? null;

        if ($branchId) {
            $this->branchScope->assertCanAccessBranch(auth('automotive_admin')->user(), 'automotive_service', (int) $branchId);
        }
    }
}
