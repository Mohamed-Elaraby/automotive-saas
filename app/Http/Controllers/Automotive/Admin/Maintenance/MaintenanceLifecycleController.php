<?php

namespace App\Http\Controllers\Automotive\Admin\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Maintenance\MaintenanceComplaint;
use App\Models\Maintenance\MaintenanceDelivery;
use App\Models\Maintenance\MaintenanceEstimate;
use App\Models\Maintenance\MaintenanceServiceCatalogItem;
use App\Models\Maintenance\MaintenanceWarranty;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Services\Automotive\Maintenance\ApprovalWorkflowService;
use App\Services\Automotive\Maintenance\ComplaintService;
use App\Services\Automotive\Maintenance\DeliveryWarrantyService;
use App\Services\Automotive\Maintenance\MaintenanceNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MaintenanceLifecycleController extends Controller
{
    public function __construct(
        protected ApprovalWorkflowService $approvals,
        protected DeliveryWarrantyService $deliveryWarranty,
        protected ComplaintService $complaints,
        protected MaintenanceNotificationService $notifications
    ) {
    }

    public function approvalsIndex(): View
    {
        return view('automotive.admin.maintenance.approvals.index', [
            'pendingEstimates' => $this->approvals->pending(),
            'approvalRecords' => $this->approvals->approvals(),
            'lostSales' => $this->approvals->lostSales(),
        ]);
    }

    public function approvalsSend(Request $request, MaintenanceEstimate $estimate): RedirectResponse
    {
        $validated = $request->validate([
            'approval_method' => ['nullable', 'in:manual,in_branch_signature,whatsapp,email,portal,otp'],
        ]);

        $this->approvals->send($estimate, $validated + [
            'created_by' => auth('automotive_admin')->id(),
        ]);

        return back()->with('success', __('maintenance.messages.estimate_sent'));
    }

    public function approvalsApprove(Request $request, MaintenanceEstimate $estimate): RedirectResponse
    {
        $validated = $request->validate([
            'approved_line_ids' => ['nullable', 'array'],
            'approved_line_ids.*' => ['integer', 'exists:maintenance_estimate_lines,id'],
            'method' => ['required', 'in:manual,in_branch_signature,whatsapp,email,portal,otp'],
            'terms_accepted' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'rejection_reason' => ['nullable', 'in:price_too_high,not_needed_now,repair_outside,needs_time,no_parts_available,not_convinced,other'],
            'follow_up_date' => ['nullable', 'date'],
        ]);

        $this->approvals->approve($estimate, $validated + [
            'approved_by' => auth('automotive_admin')->id(),
            'ip_address' => $request->ip(),
            'device_summary' => (string) $request->userAgent(),
        ]);

        return back()->with('success', __('maintenance.messages.estimate_approved'));
    }

    public function deliveriesIndex(): View
    {
        return view('automotive.admin.maintenance.deliveries.index', $this->context() + [
            'deliveries' => $this->deliveryWarranty->deliveries(),
        ]);
    }

    public function deliveriesStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'work_order_id' => ['required', 'integer', 'exists:work_orders,id'],
            'checklist' => ['nullable', 'array'],
            'customer_signature' => ['nullable', 'string'],
            'advisor_signature' => ['nullable', 'string'],
            'customer_visible_notes' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->deliveryWarranty->createDelivery($validated + [
            'created_by' => auth('automotive_admin')->id(),
        ]);

        return back()->with('success', __('maintenance.messages.delivery_created'));
    }

    public function deliveriesRelease(Request $request, MaintenanceDelivery $delivery): RedirectResponse
    {
        $validated = $request->validate([
            'checklist' => ['nullable', 'array'],
            'payment_status' => ['required', 'in:unpaid,partially_paid,paid,refunded,cancelled'],
            'customer_signature' => ['nullable', 'string'],
            'advisor_signature' => ['nullable', 'string'],
            'customer_visible_notes' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->deliveryWarranty->release($delivery, $validated + [
            'delivered_by' => auth('automotive_admin')->id(),
        ]);

        return back()->with('success', __('maintenance.messages.vehicle_delivered'));
    }

    public function warrantiesIndex(): View
    {
        return view('automotive.admin.maintenance.warranties.index', $this->context() + [
            'warranties' => $this->deliveryWarranty->warranties(),
            'claims' => $this->deliveryWarranty->claims(),
            'serviceItems' => MaintenanceServiceCatalogItem::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function warrantiesStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'work_order_id' => ['nullable', 'integer', 'exists:work_orders,id'],
            'service_catalog_item_id' => ['nullable', 'integer', 'exists:maintenance_service_catalog_items,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'warranty_type' => ['required', 'in:labor,parts,service_package,no_warranty'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'mileage_limit' => ['nullable', 'integer', 'min:0'],
            'terms' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->deliveryWarranty->createWarranty($validated + [
            'created_by' => auth('automotive_admin')->id(),
        ]);

        return back()->with('success', __('maintenance.messages.warranty_created'));
    }

    public function warrantyClaimsStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'warranty_id' => ['nullable', 'integer', 'exists:maintenance_warranties,id'],
            'original_work_order_id' => ['nullable', 'integer', 'exists:work_orders,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'comeback_reason' => ['nullable', 'string', 'max:120'],
            'customer_complaint' => ['nullable', 'string', 'max:5000'],
            'root_cause' => ['nullable', 'string', 'max:5000'],
            'resolution' => ['nullable', 'string', 'max:5000'],
            'cost_impact' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->deliveryWarranty->createClaim($validated);

        return back()->with('success', __('maintenance.messages.warranty_claim_created'));
    }

    public function complaintsIndex(): View
    {
        return view('automotive.admin.maintenance.complaints.index', $this->context() + [
            'complaints' => $this->complaints->recent(),
        ]);
    }

    public function complaintsStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'work_order_id' => ['nullable', 'integer', 'exists:work_orders,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'source' => ['required', 'in:in_branch,phone,whatsapp,email,portal,follow_up'],
            'severity' => ['required', 'in:low,medium,high,urgent'],
            'customer_visible_note' => ['nullable', 'string', 'max:5000'],
            'internal_note' => ['nullable', 'string', 'max:5000'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $this->complaints->create($validated + [
            'created_by' => auth('automotive_admin')->id(),
        ]);

        return back()->with('success', __('maintenance.messages.complaint_created'));
    }

    public function complaintsResolve(Request $request, MaintenanceComplaint $complaint): RedirectResponse
    {
        $validated = $request->validate([
            'resolution' => ['required', 'string', 'max:5000'],
        ]);

        $this->complaints->resolve($complaint, $validated + [
            'resolved_by' => auth('automotive_admin')->id(),
        ]);

        return back()->with('success', __('maintenance.messages.complaint_resolved'));
    }

    public function notificationsIndex(): View
    {
        return view('automotive.admin.maintenance.notifications.index', [
            'notifications' => $this->notifications->unread(100),
        ]);
    }

    public function notificationsStream(Request $request): StreamedResponse
    {
        $lastId = (int) ($request->header('Last-Event-ID') ?: $request->query('last_id', 0));

        return Response::stream(function () use ($lastId) {
            $notifications = $this->notifications->streamSince($lastId);

            foreach ($notifications as $notification) {
                echo 'id: ' . $notification->id . "\n";
                echo 'event: ' . $notification->event_type . "\n";
                echo 'data: ' . json_encode([
                    'id' => $notification->id,
                    'event_type' => $notification->event_type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'severity' => $notification->severity,
                    'branch' => $notification->branch?->name,
                    'created_at' => optional($notification->created_at)->format('Y-m-d H:i:s'),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
            }

            @ob_flush();
            @flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    protected function context(): array
    {
        return [
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'customers' => Customer::query()->orderBy('name')->limit(200)->get(),
            'vehicles' => Vehicle::query()->with('customer')->latest('id')->limit(200)->get(),
            'users' => User::query()->orderBy('name')->get(),
            'workOrders' => WorkOrder::query()->with(['branch', 'customer', 'vehicle'])->latest('id')->limit(200)->get(),
        ];
    }
}
