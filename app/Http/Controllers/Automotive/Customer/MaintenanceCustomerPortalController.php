<?php

namespace App\Http\Controllers\Automotive\Customer;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\MaintenanceEstimate;
use App\Models\Maintenance\MaintenanceTimelineEntry;
use App\Models\WorkOrder;
use App\Services\Automotive\Maintenance\ApprovalWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaintenanceCustomerPortalController extends Controller
{
    public function __construct(protected ApprovalWorkflowService $approvals)
    {
    }

    public function tracking(string $token): View
    {
        $workOrder = WorkOrder::query()
            ->with(['branch', 'customer', 'vehicle', 'checkIns', 'deliveries', 'warranties'])
            ->where('customer_tracking_token', $token)
            ->firstOrFail();

        $timeline = MaintenanceTimelineEntry::query()
            ->where('work_order_id', $workOrder->id)
            ->where(function ($query) {
                $query->whereNotNull('customer_visible_note')
                    ->orWhereIn('event_type', [
                        'vehicle.checked_in',
                        'estimate_sent',
                        'estimate_viewed',
                        'estimate_approved',
                        'estimate_partially_approved',
                        'estimate_rejected',
                        'job.completed',
                        'qc.passed',
                        'vehicle.delivered',
                    ]);
            })
            ->oldest('id')
            ->get();

        $estimates = MaintenanceEstimate::query()
            ->with(['lines'])
            ->where('work_order_id', $workOrder->id)
            ->whereIn('status', ['sent', 'viewed', 'approved', 'partially_approved', 'rejected', 'expired'])
            ->latest('id')
            ->get();

        return view('automotive.customer.maintenance.tracking', [
            'workOrder' => $workOrder,
            'timeline' => $timeline,
            'estimates' => $estimates,
            'trackingToken' => $token,
        ]);
    }

    public function estimate(string $token): View
    {
        $estimate = MaintenanceEstimate::query()
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

    public function estimateDecision(Request $request, string $token): RedirectResponse
    {
        $estimate = MaintenanceEstimate::query()
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
}
