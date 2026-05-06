<?php

namespace App\Http\Controllers\Automotive\Customer;

use App\Http\Controllers\Controller;
use App\Services\Automotive\Maintenance\ApprovalWorkflowService;
use App\Services\Automotive\Maintenance\ComplaintService;
use App\Services\Automotive\Maintenance\MaintenanceCustomerPortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaintenanceCustomerPortalController extends Controller
{
    public function __construct(
        protected ApprovalWorkflowService $approvals,
        protected ComplaintService $complaints,
        protected MaintenanceCustomerPortalService $portal
    ) {
    }

    public function tracking(string $token): View
    {
        $workOrder = $this->portal->workOrderForToken($token);

        return view('automotive.customer.maintenance.tracking', [
            'workOrder' => $workOrder,
            'timeline' => $this->portal->publicTimeline($workOrder),
            'estimates' => $this->portal->publicEstimates($workOrder),
            'invoices' => $this->portal->publicInvoices($workOrder),
            'serviceHistory' => $this->portal->serviceHistory($workOrder),
            'trackingToken' => $token,
        ]);
    }

    public function trackingJson(string $token): JsonResponse
    {
        return response()->json($this->portal->trackingPayload($token));
    }

    public function estimate(string $token): View
    {
        $estimate = \App\Models\Maintenance\MaintenanceEstimate::query()
            ->with(['branch', 'customer', 'vehicle', 'workOrder', 'lines.serviceCatalogItem'])
            ->where('approval_token', $token)
            ->firstOrFail();

        $this->approvals->markViewedFromPortal($estimate);
        $estimate->refresh()->load(['branch', 'customer', 'vehicle', 'workOrder', 'lines.serviceCatalogItem']);

        return view('automotive.customer.maintenance.estimate', [
            'estimate' => $estimate,
            'approvalToken' => $token,
        ]);
    }

    public function estimateJson(string $token): JsonResponse
    {
        return response()->json($this->portal->estimatePayload($token));
    }

    public function estimateDecision(Request $request, string $token): RedirectResponse
    {
        $estimate = \App\Models\Maintenance\MaintenanceEstimate::query()
            ->with(['lines', 'workOrder'])
            ->where('approval_token', $token)
            ->firstOrFail();

        abort_unless(in_array($estimate->status, ['sent', 'viewed'], true), 409);

        $validated = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'approved_line_ids' => ['nullable', 'array'],
            'approved_line_ids.*' => ['integer', 'exists:maintenance_estimate_lines,id'],
            'terms_accepted' => ['nullable', 'boolean'],
            'rejection_reason' => ['nullable', 'in:price_too_high,not_needed_now,repair_outside,needs_time,no_parts_available,not_convinced,other'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validated['decision'] === 'approve' && empty($validated['terms_accepted'])) {
            return back()->withErrors(['terms_accepted' => __('maintenance.customer_portal.terms_required')])->withInput();
        }

        $this->approvals->customerDecision($estimate, $validated + [
            'ip_address' => $request->ip(),
            'device_summary' => (string) $request->userAgent(),
        ]);

        return redirect()
            ->route('automotive.customer.maintenance.estimate', $token)
            ->with('success', __('maintenance.customer_portal.decision_recorded'));
    }

    public function submitComplaint(Request $request, string $token): RedirectResponse
    {
        $workOrder = $this->portal->workOrderForToken($token);

        $validated = $request->validate([
            'severity' => ['required', 'in:low,medium,high,urgent'],
            'customer_visible_note' => ['required', 'string', 'max:5000'],
        ]);

        $this->complaints->create($validated + [
            'branch_id' => $workOrder->branch_id,
            'work_order_id' => $workOrder->id,
            'customer_id' => $workOrder->customer_id,
            'vehicle_id' => $workOrder->vehicle_id,
            'source' => 'portal',
        ]);

        return back()->with('success', __('maintenance.customer_portal.complaint_submitted'));
    }

    public function submitFeedback(Request $request, string $token): RedirectResponse
    {
        $workOrder = $this->portal->workOrderForToken($token);

        $validated = $request->validate([
            'feedback_type' => ['required', 'in:delivery,service,follow_up,general'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'customer_visible_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->portal->submitFeedback($workOrder, $validated + [
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        return back()->with('success', __('maintenance.customer_portal.feedback_submitted'));
    }
}
