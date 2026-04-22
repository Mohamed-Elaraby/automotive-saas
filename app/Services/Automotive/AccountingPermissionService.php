<?php

namespace App\Services\Automotive;

use App\Models\User;
use Illuminate\Support\Facades\Schema;

class AccountingPermissionService
{
    public const MANUAL_JOURNALS_CREATE = 'accounting.manual_journals.create';
    public const MANUAL_JOURNALS_POST = 'accounting.manual_journals.post';
    public const MANUAL_JOURNALS_APPROVE = 'accounting.manual_journals.approve';
    public const SOURCE_EVENTS_POST = 'accounting.source_events.post';
    public const AR_INVOICES_MANAGE = 'accounting.ar_invoices.manage';
    public const AR_INVOICES_POST = 'accounting.ar_invoices.post';
    public const INVENTORY_MOVEMENTS_POST = 'accounting.inventory_movements.post';
    public const VENDOR_BILLS_POST = 'accounting.vendor_bills.post';
    public const VENDOR_BILLS_ADJUST = 'accounting.vendor_bills.adjust';
    public const VENDOR_BILL_PAYMENTS_RECORD = 'accounting.vendor_bill_payments.record';
    public const CUSTOMER_PAYMENTS_RECORD = 'accounting.customer_payments.record';
    public const DEPOSIT_BATCHES_CREATE = 'accounting.deposit_batches.create';
    public const DEPOSIT_BATCHES_CORRECT = 'accounting.deposit_batches.correct';
    public const RECONCILIATION_MANAGE = 'accounting.reconciliation.manage';
    public const JOURNALS_REVERSE = 'accounting.journals.reverse';
    public const PERIODS_LOCK = 'accounting.periods.lock';
    public const ACCOUNTS_MANAGE = 'accounting.accounts.manage';
    public const TAX_RATES_MANAGE = 'accounting.tax_rates.manage';
    public const REPORTS_EXPORT = 'accounting.reports.export';

    public function can(?User $user, string $permission): bool
    {
        if (! $user) {
            return false;
        }

        if (! Schema::hasColumn('users', 'accounting_permissions')) {
            return true;
        }

        $permissions = $user->accounting_permissions;

        if ($permissions === null) {
            return true;
        }

        if (in_array('*', $permissions, true) || in_array('accounting.*', $permissions, true)) {
            return true;
        }

        return in_array($permission, $permissions, true);
    }
}
