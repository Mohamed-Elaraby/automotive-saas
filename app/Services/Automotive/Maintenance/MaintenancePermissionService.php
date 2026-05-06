<?php

namespace App\Services\Automotive\Maintenance;

use App\Models\User;
use Illuminate\Support\Facades\Schema;

class MaintenancePermissionService
{
    public function roles(): array
    {
        return [
            'company_owner' => 'Company Owner',
            'general_manager' => 'General Manager',
            'branch_manager' => 'Branch Manager',
            'service_advisor' => 'Service Advisor',
            'receptionist' => 'Receptionist',
            'technician' => 'Technician',
            'senior_technician' => 'Senior Technician',
            'qc_inspector' => 'QC Inspector',
            'cashier' => 'Cashier',
            'fleet_coordinator' => 'Fleet Coordinator',
            'accountant' => 'Accountant',
            'read_only_auditor' => 'Read-only Auditor',
        ];
    }

    public function definitions(): array
    {
        return [
            'maintenance.customers.view' => ['label' => 'View customers', 'group' => 'customers'],
            'maintenance.customers.create' => ['label' => 'Create customers', 'group' => 'customers'],
            'maintenance.customers.update' => ['label' => 'Update customers', 'group' => 'customers'],
            'maintenance.vehicles.view' => ['label' => 'View vehicles', 'group' => 'vehicles'],
            'maintenance.vehicles.create' => ['label' => 'Create vehicles', 'group' => 'vehicles'],
            'maintenance.vehicles.update' => ['label' => 'Update vehicles', 'group' => 'vehicles'],
            'maintenance.checkins.create' => ['label' => 'Create check-ins', 'group' => 'checkins'],
            'maintenance.checkins.capture_photos' => ['label' => 'Capture check-in photos', 'group' => 'checkins'],
            'maintenance.checkins.confirm_vin' => ['label' => 'Confirm VIN', 'group' => 'checkins'],
            'maintenance.inspections.create' => ['label' => 'Create inspections', 'group' => 'inspections'],
            'maintenance.inspections.complete' => ['label' => 'Complete inspections', 'group' => 'inspections'],
            'maintenance.estimates.create' => ['label' => 'Create estimates', 'group' => 'estimates'],
            'maintenance.estimates.send' => ['label' => 'Send estimates', 'group' => 'estimates'],
            'maintenance.estimates.approve_manually' => ['label' => 'Approve estimates manually', 'group' => 'estimates'],
            'maintenance.estimates.apply_discount' => ['label' => 'Apply discounts', 'group' => 'estimates'],
            'maintenance.work_orders.view' => ['label' => 'View work orders', 'group' => 'work_orders'],
            'maintenance.work_orders.create' => ['label' => 'Create work orders', 'group' => 'work_orders'],
            'maintenance.work_orders.assign' => ['label' => 'Assign work orders', 'group' => 'work_orders'],
            'maintenance.work_orders.close' => ['label' => 'Close work orders', 'group' => 'work_orders'],
            'maintenance.work_orders.cancel' => ['label' => 'Cancel work orders', 'group' => 'work_orders'],
            'maintenance.jobs.view_assigned' => ['label' => 'View assigned jobs', 'group' => 'jobs'],
            'maintenance.jobs.start' => ['label' => 'Start jobs', 'group' => 'jobs'],
            'maintenance.jobs.pause' => ['label' => 'Pause jobs', 'group' => 'jobs'],
            'maintenance.jobs.complete' => ['label' => 'Complete jobs', 'group' => 'jobs'],
            'maintenance.qc.perform' => ['label' => 'Perform QC', 'group' => 'qc'],
            'maintenance.qc.pass' => ['label' => 'Pass QC', 'group' => 'qc'],
            'maintenance.qc.fail' => ['label' => 'Fail QC', 'group' => 'qc'],
            'maintenance.delivery.release_vehicle' => ['label' => 'Release vehicle', 'group' => 'delivery'],
            'maintenance.invoices.view' => ['label' => 'View invoices', 'group' => 'billing'],
            'maintenance.invoices.create' => ['label' => 'Create invoices', 'group' => 'billing'],
            'maintenance.invoices.collect_payment' => ['label' => 'Collect payment', 'group' => 'billing'],
            'maintenance.invoices.cancel' => ['label' => 'Cancel invoices', 'group' => 'billing'],
            'maintenance.documents.generate' => ['label' => 'Generate documents', 'group' => 'documents'],
            'maintenance.reports.view' => ['label' => 'View reports', 'group' => 'reports'],
            'maintenance.reports.financial' => ['label' => 'View financial reports', 'group' => 'reports'],
            'maintenance.settings.manage' => ['label' => 'Manage settings', 'group' => 'settings'],
            'maintenance.users.manage' => ['label' => 'Manage maintenance users', 'group' => 'settings'],
            'maintenance.roles.manage' => ['label' => 'Manage roles', 'group' => 'settings'],
        ];
    }

    public function can(?User $user, string $permission): bool
    {
        if (! $user) {
            return false;
        }

        if (! Schema::hasColumn('users', 'maintenance_permissions')) {
            return true;
        }

        $permissions = $user->maintenance_permissions;

        if ($permissions === null) {
            return true;
        }

        if (in_array('*', $permissions, true) || in_array('maintenance.*', $permissions, true)) {
            return true;
        }

        return in_array($permission, $permissions, true);
    }

    public function summary(?User $user): array
    {
        $definitions = collect($this->definitions());
        $allowed = $definitions->filter(fn (array $definition, string $permission) => $this->can($user, $permission))->count();

        return [
            'role' => $user?->maintenance_role ?: 'legacy_full_access',
            'allowed_count' => $allowed,
            'total_count' => $definitions->count(),
            'mode' => $allowed === $definitions->count() ? 'full_access' : ($allowed === 0 ? 'read_only' : 'restricted_access'),
        ];
    }
}
