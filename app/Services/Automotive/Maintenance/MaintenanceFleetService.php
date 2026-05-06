<?php

namespace App\Services\Automotive\Maintenance;

use App\Models\Customer;
use App\Models\Maintenance\MaintenanceFleetAccount;
use App\Models\Maintenance\MaintenanceInvoice;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class MaintenanceFleetService
{
    public function __construct(
        protected MaintenanceNumberService $numbers,
        protected MaintenanceAuditService $audit
    ) {
    }

    public function accounts(int $limit = 100): Collection
    {
        return MaintenanceFleetAccount::query()
            ->with('customer')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function candidates(): Collection
    {
        return Customer::query()
            ->with('fleetAccount')
            ->whereIn('customer_type', ['fleet', 'company', 'government'])
            ->orderBy('name')
            ->limit(200)
            ->get();
    }

    public function createOrUpdate(array $data): MaintenanceFleetAccount
    {
        return DB::transaction(function () use ($data): MaintenanceFleetAccount {
            $customer = Customer::query()->findOrFail($data['customer_id']);

            $fleet = MaintenanceFleetAccount::query()->updateOrCreate(
                ['customer_id' => $customer->id],
                [
                    'fleet_number' => $customer->fleetAccount?->fleet_number ?: $this->numbers->next('maintenance_fleet_accounts', 'fleet_number', 'FLT'),
                    'status' => $data['status'] ?? 'active',
                    'contract_type' => $data['contract_type'] ?? 'standard',
                    'contract_starts_on' => $data['contract_starts_on'] ?? null,
                    'contract_ends_on' => $data['contract_ends_on'] ?? null,
                    'credit_limit' => $data['credit_limit'] ?? null,
                    'monthly_billing_enabled' => (bool) ($data['monthly_billing_enabled'] ?? false),
                    'approval_required' => (bool) ($data['approval_required'] ?? false),
                    'approval_limit' => $data['approval_limit'] ?? null,
                    'billing_cycle_day' => $data['billing_cycle_day'] ?? null,
                    'preventive_schedule' => $this->scheduleFromData($data),
                    'terms' => $data['terms'] ?? null,
                    'internal_notes' => $data['internal_notes'] ?? null,
                    'created_by' => $data['created_by'] ?? null,
                ]
            );

            $customer->forceFill(['customer_type' => 'fleet'])->save();

            $this->audit->record('fleet.account.saved', 'fleet', [
                'user_id' => $data['created_by'] ?? null,
                'auditable' => $fleet,
                'new_values' => [
                    'fleet_number' => $fleet->fleet_number,
                    'customer_id' => $fleet->customer_id,
                    'contract_type' => $fleet->contract_type,
                    'credit_limit' => $fleet->credit_limit,
                    'monthly_billing_enabled' => $fleet->monthly_billing_enabled,
                ],
            ]);

            return $fleet->fresh('customer');
        });
    }

    public function profile(MaintenanceFleetAccount $fleet): array
    {
        $fleet->load('customer');
        $customerId = $fleet->customer_id;

        $vehicles = Vehicle::query()
            ->where('customer_id', $customerId)
            ->with(['workOrders' => fn ($query) => $query->latest('id')->limit(5)])
            ->orderBy('plate_number')
            ->get();

        $workOrders = WorkOrder::query()
            ->with(['vehicle', 'branch'])
            ->where('customer_id', $customerId)
            ->latest('id')
            ->limit(50)
            ->get();

        $invoices = MaintenanceInvoice::query()
            ->where('customer_id', $customerId)
            ->latest('id')
            ->limit(50)
            ->get();

        return [
            'fleet' => $fleet,
            'vehicles' => $vehicles,
            'workOrders' => $workOrders,
            'invoices' => $invoices,
            'summary' => [
                'vehicles_count' => $vehicles->count(),
                'open_work_orders' => $workOrders->whereNotIn('status', ['delivered', 'closed', 'cancelled'])->count(),
                'invoice_total' => (float) $invoices->sum('grand_total'),
                'paid_total' => (float) $invoices->sum('paid_amount'),
                'pending_total' => (float) $invoices->sum('grand_total') - (float) $invoices->sum('paid_amount'),
            ],
        ];
    }

    public function reportRows(?MaintenanceFleetAccount $fleet = null): array
    {
        $accounts = $fleet ? collect([$fleet->load('customer')]) : $this->accounts(500);

        return [
            ['Fleet', 'Customer', 'Vehicles', 'Open Work Orders', 'Invoice Total', 'Paid Total', 'Pending Total'],
            ...$accounts->map(function (MaintenanceFleetAccount $account): array {
                $profile = $this->profile($account);

                return [
                    $account->fleet_number,
                    $account->customer?->name,
                    $profile['summary']['vehicles_count'],
                    $profile['summary']['open_work_orders'],
                    round($profile['summary']['invoice_total'], 2),
                    round($profile['summary']['paid_total'], 2),
                    round($profile['summary']['pending_total'], 2),
                ];
            })->all(),
        ];
    }

    protected function scheduleFromData(array $data): array
    {
        return [
            'default_mileage_interval' => isset($data['default_mileage_interval']) ? (int) $data['default_mileage_interval'] : null,
            'default_months_interval' => isset($data['default_months_interval']) ? (int) $data['default_months_interval'] : null,
            'notes' => $data['preventive_notes'] ?? null,
        ];
    }
}
