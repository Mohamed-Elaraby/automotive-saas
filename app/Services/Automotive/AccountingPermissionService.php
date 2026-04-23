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

    public function definitions(): array
    {
        return [
            self::MANUAL_JOURNALS_CREATE => ['label' => 'Create manual journals', 'group' => 'journals'],
            self::MANUAL_JOURNALS_POST => ['label' => 'Post approved journals', 'group' => 'journals'],
            self::MANUAL_JOURNALS_APPROVE => ['label' => 'Approve or reject journals', 'group' => 'journals'],
            self::SOURCE_EVENTS_POST => ['label' => 'Post source accounting events', 'group' => 'journals'],
            self::AR_INVOICES_MANAGE => ['label' => 'Create invoices', 'group' => 'receivables'],
            self::AR_INVOICES_POST => ['label' => 'Post invoices', 'group' => 'receivables'],
            self::INVENTORY_MOVEMENTS_POST => ['label' => 'Post inventory valuation', 'group' => 'inventory'],
            self::VENDOR_BILLS_POST => ['label' => 'Post vendor bills', 'group' => 'payables'],
            self::VENDOR_BILLS_ADJUST => ['label' => 'Adjust vendor bills', 'group' => 'payables'],
            self::VENDOR_BILL_PAYMENTS_RECORD => ['label' => 'Record vendor payments', 'group' => 'payables'],
            self::CUSTOMER_PAYMENTS_RECORD => ['label' => 'Record customer payments', 'group' => 'receivables'],
            self::DEPOSIT_BATCHES_CREATE => ['label' => 'Create deposit batches', 'group' => 'cash'],
            self::DEPOSIT_BATCHES_CORRECT => ['label' => 'Correct deposit batches', 'group' => 'cash'],
            self::RECONCILIATION_MANAGE => ['label' => 'Manage reconciliation', 'group' => 'cash'],
            self::JOURNALS_REVERSE => ['label' => 'Reverse posted journals', 'group' => 'journals'],
            self::PERIODS_LOCK => ['label' => 'Start or lock accounting periods', 'group' => 'close'],
            self::ACCOUNTS_MANAGE => ['label' => 'Manage chart of accounts and setup', 'group' => 'setup'],
            self::TAX_RATES_MANAGE => ['label' => 'Manage tax rates and filings', 'group' => 'tax'],
            self::REPORTS_EXPORT => ['label' => 'Export reports and review packs', 'group' => 'reports'],
        ];
    }

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

    public function matrix(?User $user): array
    {
        $matrix = [];

        foreach (array_keys($this->definitions()) as $permission) {
            $matrix[$this->matrixKey($permission)] = $this->can($user, $permission);
        }

        return $matrix;
    }

    public function summary(?User $user): array
    {
        $definitions = $this->definitions();
        $matrix = $this->matrix($user);
        $allowed = collect($definitions)
            ->map(function (array $definition, string $permission) use ($matrix): array {
                return [
                    'permission' => $permission,
                    'label' => $definition['label'],
                    'group' => $definition['group'],
                    'allowed' => (bool) ($matrix[$this->matrixKey($permission)] ?? false),
                ];
            });

        $allowedCount = $allowed->where('allowed', true)->count();
        $totalCount = $allowed->count();
        $mode = $allowedCount === 0
            ? 'read_only'
            : ($allowedCount === $totalCount ? 'full_access' : 'restricted_access');

        return [
            'role' => $user?->accounting_role ?: 'legacy_full_access',
            'mode' => $mode,
            'mode_label' => match ($mode) {
                'read_only' => 'Read Only',
                'restricted_access' => 'Restricted Access',
                default => 'Full Access',
            },
            'allowed_count' => $allowedCount,
            'total_count' => $totalCount,
            'items' => $allowed->values(),
            'can_manage_setup' => (bool) ($matrix['accounts_manage'] ?? false),
            'can_export' => (bool) ($matrix['reports_export'] ?? false),
            'can_post_or_lock' => (bool) (($matrix['manual_journals_post'] ?? false) || ($matrix['periods_lock'] ?? false)),
        ];
    }

    protected function matrixKey(string $permission): string
    {
        return match ($permission) {
            self::MANUAL_JOURNALS_CREATE => 'manual_journals_create',
            self::MANUAL_JOURNALS_POST => 'manual_journals_post',
            self::MANUAL_JOURNALS_APPROVE => 'manual_journals_approve',
            self::SOURCE_EVENTS_POST => 'source_events_post',
            self::AR_INVOICES_MANAGE => 'ar_invoices_manage',
            self::AR_INVOICES_POST => 'ar_invoices_post',
            self::INVENTORY_MOVEMENTS_POST => 'inventory_movements_post',
            self::VENDOR_BILLS_POST => 'vendor_bills_post',
            self::VENDOR_BILLS_ADJUST => 'vendor_bills_adjust',
            self::VENDOR_BILL_PAYMENTS_RECORD => 'vendor_bill_payments_record',
            self::CUSTOMER_PAYMENTS_RECORD => 'customer_payments_record',
            self::DEPOSIT_BATCHES_CREATE => 'deposit_batches_create',
            self::DEPOSIT_BATCHES_CORRECT => 'deposit_batches_correct',
            self::RECONCILIATION_MANAGE => 'reconciliation_manage',
            self::JOURNALS_REVERSE => 'journals_reverse',
            self::PERIODS_LOCK => 'periods_lock',
            self::ACCOUNTS_MANAGE => 'accounts_manage',
            self::TAX_RATES_MANAGE => 'tax_rates_manage',
            self::REPORTS_EXPORT => 'reports_export',
            default => $permission,
        };
    }
}
