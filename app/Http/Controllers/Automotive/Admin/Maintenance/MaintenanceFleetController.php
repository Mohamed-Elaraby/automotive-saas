<?php

namespace App\Http\Controllers\Automotive\Admin\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\MaintenanceFleetAccount;
use App\Services\Automotive\Maintenance\MaintenanceFleetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;

class MaintenanceFleetController extends Controller
{
    public function __construct(protected MaintenanceFleetService $fleet)
    {
    }

    public function index(): View
    {
        return view('automotive.admin.maintenance.fleet.index', [
            'accounts' => $this->fleet->accounts(),
            'customers' => $this->fleet->candidates(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'status' => ['required', 'in:active,on_hold,suspended,expired'],
            'contract_type' => ['required', 'in:standard,credit,government,monthly_billing,custom'],
            'contract_starts_on' => ['nullable', 'date'],
            'contract_ends_on' => ['nullable', 'date', 'after_or_equal:contract_starts_on'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'monthly_billing_enabled' => ['nullable', 'boolean'],
            'approval_required' => ['nullable', 'boolean'],
            'approval_limit' => ['nullable', 'numeric', 'min:0'],
            'billing_cycle_day' => ['nullable', 'string', 'max:10'],
            'default_mileage_interval' => ['nullable', 'integer', 'min:0'],
            'default_months_interval' => ['nullable', 'integer', 'min:0'],
            'preventive_notes' => ['nullable', 'string', 'max:2000'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $fleet = $this->fleet->createOrUpdate($validated + [
            'created_by' => auth('automotive_admin')->id(),
        ]);

        return redirect()
            ->route('automotive.admin.maintenance.fleet.show', $fleet)
            ->with('success', __('maintenance.messages.fleet_saved'));
    }

    public function show(MaintenanceFleetAccount $fleet): View
    {
        return view('automotive.admin.maintenance.fleet.show', $this->fleet->profile($fleet));
    }

    public function export(?MaintenanceFleetAccount $fleet = null)
    {
        $rows = $this->fleet->reportRows($fleet);
        $filename = $fleet ? $fleet->fleet_number . '-fleet-report.csv' : 'fleet-report.csv';

        return Response::streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
