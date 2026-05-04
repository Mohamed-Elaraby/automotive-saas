<?php

namespace App\Http\Controllers\Automotive\Admin\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Maintenance\MaintenanceInvoice;
use App\Models\Maintenance\MaintenancePartsRequest;
use App\Models\Maintenance\MaintenanceWorkOrderJob;
use App\Models\StockItem;
use App\Models\WorkOrder;
use App\Services\Automotive\Maintenance\MaintenanceIntegrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaintenanceIntegrationController extends Controller
{
    public function __construct(protected MaintenanceIntegrationService $integrations)
    {
    }

    public function index(): View
    {
        return view('automotive.admin.maintenance.integrations.index', [
            'dashboard' => $this->integrations->dashboard(),
            'partsRequests' => $this->integrations->recentPartsRequests(),
            'handoffs' => $this->integrations->recentHandoffs(),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'workOrders' => WorkOrder::query()->with(['customer', 'vehicle', 'branch'])->latest('id')->limit(150)->get(),
            'jobs' => MaintenanceWorkOrderJob::query()->with('workOrder')->latest('id')->limit(150)->get(),
            'stockItems' => StockItem::query()->where('is_active', true)->orderBy('name')->limit(200)->get(),
            'invoices' => MaintenanceInvoice::query()->with(['customer', 'vehicle', 'workOrder'])->latest('id')->limit(100)->get(),
        ]);
    }

    public function storePartsRequest(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'work_order_id' => ['required_without:job_id', 'nullable', 'integer', 'exists:work_orders,id'],
            'job_id' => ['nullable', 'integer', 'exists:maintenance_work_order_jobs,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'part_name' => ['required', 'string', 'max:255'],
            'part_number' => ['nullable', 'string', 'max:255'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'needed_by' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'internal_notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $this->integrations->createPartsRequest($validated + [
            'requested_by' => auth('automotive_admin')->id(),
        ]);

        return back()->with('success', __('maintenance.messages.parts_request_created'));
    }

    public function approvePartsRequest(MaintenancePartsRequest $partsRequest): RedirectResponse
    {
        $this->integrations->approvePartsRequest($partsRequest, auth('automotive_admin')->id());

        return back()->with('success', __('maintenance.messages.parts_request_approved'));
    }

    public function issuePartsRequest(MaintenancePartsRequest $partsRequest): RedirectResponse
    {
        $this->integrations->issuePartsRequest($partsRequest, auth('automotive_admin')->id());

        return back()->with('success', __('maintenance.messages.parts_request_issued'));
    }

    public function syncInvoice(MaintenanceInvoice $invoice): RedirectResponse
    {
        $event = $this->integrations->postInvoiceToAccounting($invoice, auth('automotive_admin')->id());

        return back()->with(
            'success',
            $event
                ? __('maintenance.messages.invoice_synced_to_accounting')
                : __('maintenance.messages.invoice_handoff_skipped')
        );
    }
}
