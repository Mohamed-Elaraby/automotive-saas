<?php

namespace App\Services\Automotive;

use App\Models\AccountingAccount;
use App\Models\AccountingAuditEntry;
use App\Models\AccountingBankAccount;
use App\Models\AccountingDepositBatch;
use App\Models\AccountingEvent;
use App\Models\AccountingInvoice;
use App\Models\AccountingPayment;
use App\Models\AccountingPeriodLock;
use App\Models\AccountingPolicy;
use App\Models\AccountingPostingGroup;
use App\Models\AccountingSetupProfile;
use App\Models\AccountingTaxRate;
use App\Models\AccountingVendorBill;
use App\Models\AccountingVendorBillAdjustment;
use App\Models\AccountingVendorBillPayment;
use App\Models\JournalEntry;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Tenancy\WorkspaceIntegrationHandoffService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountingRuntimeService
{
    protected const INVENTORY_VALUATION_METHOD = 'current_product_cost';
    protected const INVENTORY_VALUATION_METHOD_LABEL = 'Current product cost at posting time';
    protected const INVENTORY_VALUATION_SOURCE = 'products.cost_price';
    protected const INVENTORY_POSTABLE_MOVEMENT_TYPES = ['opening', 'adjustment_in', 'adjustment_out'];

    public function __construct(
        protected WorkspaceIntegrationHandoffService $workspaceIntegrationHandoffService
    ) {
    }

    public function getPostingGroups(): Collection
    {
        return AccountingPostingGroup::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    public function getAccounts(array $filters = []): Collection
    {
        $this->ensureDefaultAccounts();

        return AccountingAccount::query()
            ->when(! empty($filters['account_type']), fn ($query) => $query->where('type', $filters['account_type']))
            ->when(isset($filters['account_status']) && $filters['account_status'] !== '', function ($query) use ($filters) {
                $query->where('is_active', $filters['account_status'] === 'active');
            })
            ->when(! empty($filters['account_search']), function ($query) use ($filters) {
                $search = '%' . trim((string) $filters['account_search']) . '%';

                $query->where(function ($query) use ($search) {
                    $query->where('code', 'like', $search)
                        ->orWhere('name', 'like', $search)
                        ->orWhere('notes', 'like', $search);
                });
            })
            ->orderBy('code')
            ->get();
    }

    public function getPeriodLocks(): Collection
    {
        return AccountingPeriodLock::query()
            ->latest('period_end')
            ->latest('id')
            ->limit(12)
            ->get();
    }

    public function periodLockSummary(?string $asOfDate = null): array
    {
        $date = Carbon::parse($asOfDate ?: now())->toDateString();

        $currentLock = AccountingPeriodLock::query()
            ->whereIn('status', ['locked', 'archived'])
            ->whereDate('period_start', '<=', $date)
            ->whereDate('period_end', '>=', $date)
            ->latest('period_end')
            ->latest('id')
            ->first();

        $latestLock = AccountingPeriodLock::query()
            ->whereIn('status', ['locked', 'archived'])
            ->latest('period_end')
            ->latest('id')
            ->first();

        $activeClose = AccountingPeriodLock::query()
            ->where('status', 'closing')
            ->whereDate('period_start', '<=', $date)
            ->whereDate('period_end', '>=', $date)
            ->latest('period_end')
            ->latest('id')
            ->first();

        return [
            'as_of_date' => $date,
            'current_status' => $currentLock ? $currentLock->status : ($activeClose ? 'closing' : 'open'),
            'current_lock' => $currentLock,
            'active_close' => $activeClose,
            'latest_lock' => $latestLock,
            'locked_periods_count' => AccountingPeriodLock::query()
                ->whereIn('status', ['locked', 'archived'])
                ->count(),
            'posting_policy' => 'Journals are the accounting source of truth; locked periods require reversal or correction entries in an open period.',
        ];
    }

    public function getPolicies(): Collection
    {
        $this->ensureDefaultPolicy();

        return AccountingPolicy::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    public function getTaxRates(): Collection
    {
        $this->ensureDefaultTaxRate();

        return AccountingTaxRate::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    public function getBankAccounts(): Collection
    {
        $this->ensureDefaultBankAccounts();

        $balances = $this->bankAccountBalances();

        return AccountingBankAccount::query()
            ->orderByDesc('is_default_receipt')
            ->orderByDesc('is_default_payment')
            ->orderBy('name')
            ->get()
            ->map(function (AccountingBankAccount $account) use ($balances): AccountingBankAccount {
                $account->setAttribute('journal_balance', round((float) ($balances[$account->account_code] ?? 0), 2));

                return $account;
            });
    }

    public function getSetupProfile(): ?AccountingSetupProfile
    {
        return AccountingSetupProfile::query()->latest('id')->first();
    }

    public function setupSummary(): array
    {
        $profile = $this->getSetupProfile();
        $hasDefaultPostingGroup = AccountingPostingGroup::query()
            ->where('is_active', true)
            ->where('is_default', true)
            ->exists();
        $hasDefaultPolicy = AccountingPolicy::query()
            ->where('is_active', true)
            ->where('is_default', true)
            ->exists();
        $hasDefaultTaxRate = AccountingTaxRate::query()
            ->where('is_active', true)
            ->where('is_default', true)
            ->exists();
        $hasReceiptAccount = AccountingBankAccount::query()
            ->where('is_active', true)
            ->where('is_default_receipt', true)
            ->exists();
        $hasPaymentAccount = AccountingBankAccount::query()
            ->where('is_active', true)
            ->where('is_default_payment', true)
            ->exists();

        $items = [
            ['label' => 'Setup profile', 'ready' => (bool) $profile?->setup_completed_at],
            ['label' => 'Default posting group', 'ready' => $hasDefaultPostingGroup],
            ['label' => 'Default inventory policy', 'ready' => $hasDefaultPolicy],
            ['label' => 'Default tax rate', 'ready' => $hasDefaultTaxRate],
            ['label' => 'Receipt account', 'ready' => $hasReceiptAccount],
            ['label' => 'Payment account', 'ready' => $hasPaymentAccount],
        ];

        return [
            'profile' => $profile,
            'items' => $items,
            'complete' => collect($items)->every(fn (array $item): bool => (bool) $item['ready']),
        ];
    }

    public function applyFirstTimeSetup(array $data, ?int $createdBy = null): AccountingSetupProfile
    {
        $currency = strtoupper((string) ($data['base_currency'] ?? 'USD'));
        $taxMode = (string) ($data['tax_mode'] ?? 'vat_standard');
        $chartTemplate = (string) ($data['chart_template'] ?? 'service_business');

        return DB::transaction(function () use ($data, $createdBy, $currency, $taxMode, $chartTemplate): AccountingSetupProfile {
            $this->ensureDefaultAccounts();

            $receivableAccount = $this->normalizeAccountCode((string) $data['default_receivable_account']);
            $payableAccount = $this->normalizeAccountCode((string) $data['default_payable_account']);
            $cashAccount = $this->normalizeAccountCode((string) $data['default_cash_account']);
            $bankAccount = $this->normalizeAccountCode((string) $data['default_bank_account']);
            $revenueAccount = $this->normalizeAccountCode((string) $data['default_revenue_account']);
            $expenseAccount = $this->normalizeAccountCode((string) $data['default_expense_account']);
            $inputTaxAccount = $this->normalizeAccountCode((string) $data['default_input_tax_account']);
            $outputTaxAccount = $this->normalizeAccountCode((string) $data['default_output_tax_account']);

            $this->ensureAccountsFromCodes([
                $receivableAccount,
                $payableAccount,
                $cashAccount,
                $bankAccount,
                $revenueAccount,
                $expenseAccount,
                $inputTaxAccount,
                $outputTaxAccount,
                '1300 Inventory Asset',
                '3900 Inventory Adjustment Offset',
                '5000 Cost Of Goods Sold',
                '5100 Inventory Adjustment Expense',
            ]);

            $this->createBankAccount([
                'name' => 'Default Cash Account',
                'type' => 'cash',
                'account_code' => $cashAccount,
                'currency' => $currency,
                'reference' => 'first-time-setup',
                'is_default_receipt' => true,
                'is_default_payment' => $cashAccount === $bankAccount,
                'is_active' => true,
                'notes' => 'Configured by the accounting first-time setup wizard.',
            ]);

            if ($bankAccount !== $cashAccount) {
                $this->createBankAccount([
                    'name' => 'Default Bank Account',
                    'type' => 'bank',
                    'account_code' => $bankAccount,
                    'currency' => $currency,
                    'reference' => 'first-time-setup',
                    'is_default_receipt' => false,
                    'is_default_payment' => true,
                    'is_active' => true,
                    'notes' => 'Configured by the accounting first-time setup wizard.',
                ]);
            }

            AccountingPostingGroup::query()->where('is_default', true)->update(['is_default' => false]);
            $postingGroup = AccountingPostingGroup::query()->updateOrCreate(
                ['code' => 'default_revenue'],
                [
                    'name' => 'Default Revenue',
                    'receivable_account' => $receivableAccount,
                    'labor_revenue_account' => $revenueAccount,
                    'parts_revenue_account' => $revenueAccount,
                    'currency' => $currency,
                    'is_default' => true,
                    'is_active' => true,
                    'notes' => 'Configured by the accounting first-time setup wizard.',
                ]
            );

            $this->createPolicy([
                'code' => 'default_inventory_policy',
                'name' => 'Default Inventory Policy',
                'currency' => $currency,
                'inventory_asset_account' => '1300 Inventory Asset',
                'inventory_adjustment_offset_account' => '3900 Inventory Adjustment Offset',
                'inventory_adjustment_expense_account' => '5100 Inventory Adjustment Expense',
                'cogs_account' => '5000 Cost Of Goods Sold',
                'is_default' => true,
                'notes' => 'Configured by the accounting first-time setup wizard.',
            ], $createdBy);

            $this->createTaxRate([
                'code' => $taxMode === 'no_tax' ? 'no_tax' : 'vat_default',
                'name' => $taxMode === 'no_tax' ? 'No Tax' : 'Default VAT',
                'rate' => $taxMode === 'no_tax' ? 0 : (float) ($data['default_tax_rate'] ?? 5),
                'input_tax_account' => $inputTaxAccount,
                'output_tax_account' => $outputTaxAccount,
                'is_default' => true,
                'is_active' => true,
                'notes' => 'Configured by the accounting first-time setup wizard.',
            ], $createdBy);

            $profile = AccountingSetupProfile::query()->updateOrCreate(
                ['id' => 1],
                [
                    'base_currency' => $currency,
                    'fiscal_year_start_month' => (int) $data['fiscal_year_start_month'],
                    'fiscal_year_start_day' => (int) $data['fiscal_year_start_day'],
                    'tax_mode' => $taxMode,
                    'chart_template' => $chartTemplate,
                    'default_receivable_account' => $receivableAccount,
                    'default_payable_account' => $payableAccount,
                    'default_cash_account' => $cashAccount,
                    'default_bank_account' => $bankAccount,
                    'default_revenue_account' => $revenueAccount,
                    'default_expense_account' => $expenseAccount,
                    'default_input_tax_account' => $inputTaxAccount,
                    'default_output_tax_account' => $outputTaxAccount,
                    'payload' => [
                        'tax_rate' => $taxMode === 'no_tax' ? 0 : (float) ($data['default_tax_rate'] ?? 5),
                        'posting_group_id' => $postingGroup->id,
                        'journal_policy' => 'Setup configures defaults only; journals remain the accounting source of truth.',
                    ],
                    'created_by' => AccountingSetupProfile::query()->whereKey(1)->exists() ? AccountingSetupProfile::query()->whereKey(1)->value('created_by') : $createdBy,
                    'updated_by' => $createdBy,
                    'setup_completed_at' => now(),
                ]
            );

            $this->recordAudit('accounting_first_time_setup_completed', $profile, 'Accounting first-time setup completed.', [
                'base_currency' => $profile->base_currency,
                'tax_mode' => $profile->tax_mode,
                'chart_template' => $profile->chart_template,
                'default_receivable_account' => $profile->default_receivable_account,
                'default_payable_account' => $profile->default_payable_account,
                'default_cash_account' => $profile->default_cash_account,
                'default_bank_account' => $profile->default_bank_account,
                'default_revenue_account' => $profile->default_revenue_account,
                'default_expense_account' => $profile->default_expense_account,
            ], $createdBy);

            return $profile;
        });
    }

    public function createBankAccount(array $data): AccountingBankAccount
    {
        $accountCode = $this->normalizeAccountCode((string) $data['account_code']);
        $this->ensureDefaultAccounts();
        $this->assertActiveAccountCodes([$accountCode], 'account_code');

        return DB::transaction(function () use ($data, $accountCode): AccountingBankAccount {
            $isDefaultReceipt = ! empty($data['is_default_receipt']);
            $isDefaultPayment = ! empty($data['is_default_payment']);

            if ($isDefaultReceipt) {
                AccountingBankAccount::query()->update(['is_default_receipt' => false]);
            }

            if ($isDefaultPayment) {
                AccountingBankAccount::query()->update(['is_default_payment' => false]);
            }

            return AccountingBankAccount::query()->updateOrCreate(
                ['account_code' => $accountCode],
                [
                    'name' => $data['name'],
                    'type' => $data['type'],
                    'currency' => strtoupper((string) ($data['currency'] ?? 'USD')),
                    'reference' => $data['reference'] ?? null,
                    'is_default_receipt' => $isDefaultReceipt,
                    'is_default_payment' => $isDefaultPayment,
                    'is_active' => ! array_key_exists('is_active', $data) || (bool) $data['is_active'],
                    'notes' => $data['notes'] ?? null,
                ]
            );
        });
    }

    public function getAuditEntries(array|int $filters = [], int $limit = 30): Collection
    {
        if (is_int($filters)) {
            $limit = $filters;
            $filters = [];
        }

        return AccountingAuditEntry::query()
            ->with('actor:id,name,email')
            ->when(! empty($filters['audit_event_type']), fn ($query) => $query->where('event_type', $filters['audit_event_type']))
            ->when(! empty($filters['audit_actor_id']), fn ($query) => $query->where('created_by', (int) $filters['audit_actor_id']))
            ->when(! empty($filters['audit_source_type']), fn ($query) => $query->where('auditable_type', $filters['audit_source_type']))
            ->when(! empty($filters['audit_date_from']), fn ($query) => $query->whereDate('created_at', '>=', $filters['audit_date_from']))
            ->when(! empty($filters['audit_date_to']), fn ($query) => $query->whereDate('created_at', '<=', $filters['audit_date_to']))
            ->latest('created_at')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function getAuditEventTypes(): Collection
    {
        return AccountingAuditEntry::query()
            ->select('event_type')
            ->distinct()
            ->orderBy('event_type')
            ->pluck('event_type');
    }

    public function getAuditSourceTypes(): Collection
    {
        return AccountingAuditEntry::query()
            ->whereNotNull('auditable_type')
            ->select('auditable_type')
            ->distinct()
            ->orderBy('auditable_type')
            ->pluck('auditable_type')
            ->map(fn (string $type): array => [
                'value' => $type,
                'label' => class_basename($type),
            ]);
    }

    public function getAuditActors(): Collection
    {
        $actorIds = AccountingAuditEntry::query()
            ->whereNotNull('created_by')
            ->select('created_by')
            ->distinct()
            ->pluck('created_by');

        if ($actorIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $actorIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    public function getReceivableEvents(int $limit = 25): Collection
    {
        return AccountingEvent::query()
            ->where('status', 'journal_posted')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('journal_entries')
                    ->whereColumn('journal_entries.accounting_event_id', 'accounting_events.id')
                    ->where('journal_entries.status', 'posted');
            })
            ->latest('event_date')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(function (AccountingEvent $event): AccountingEvent {
                $paidAmount = $this->paidAmountForEvent($event->id);
                $event->setAttribute('paid_amount', $paidAmount);
                $event->setAttribute('open_amount', max(0, round((float) $event->total_amount - $paidAmount, 2)));

                return $event;
            })
            ->filter(fn (AccountingEvent $event): bool => (float) $event->getAttribute('open_amount') > 0)
            ->values();
    }

    public function getInvoices(array $filters = [], int $limit = 25): Collection
    {
        return AccountingInvoice::query()
            ->with(['lines', 'accountingEvent', 'journalEntry', 'creator', 'poster'])
            ->when(! empty($filters['invoice_status']), fn ($query) => $query->where('status', $filters['invoice_status']))
            ->when(! empty($filters['date_from']), fn ($query) => $query->whereDate('issue_date', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($query) => $query->whereDate('issue_date', '<=', $filters['date_to']))
            ->when(! empty($filters['search']), function ($query) use ($filters) {
                $search = '%' . trim((string) $filters['search']) . '%';

                $query->where(function ($query) use ($search) {
                    $query->where('invoice_number', 'like', $search)
                        ->orWhere('customer_name', 'like', $search)
                        ->orWhere('reference', 'like', $search);
                });
            })
            ->latest('issue_date')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(function (AccountingInvoice $invoice): AccountingInvoice {
                $paidAmount = $invoice->accounting_event_id
                    ? $this->paidAmountForEvent((int) $invoice->accounting_event_id)
                    : 0.0;
                $invoice->setAttribute('paid_amount', $paidAmount);
                $invoice->setAttribute('open_amount', max(0, round((float) $invoice->total_amount - $paidAmount, 2)));

                return $invoice;
            });
    }

    public function getRecentPayments(int $limit = 15): Collection
    {
        return AccountingPayment::query()
            ->with(['accountingEvent', 'journalEntry', 'depositBatch', 'bankAccount', 'creator'])
            ->latest('payment_date')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function getPayments(array $filters = [], int $limit = 50): Collection
    {
        return AccountingPayment::query()
            ->with(['accountingEvent', 'journalEntry', 'depositBatch', 'bankAccount', 'creator'])
            ->when(! empty($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(! empty($filters['reconciliation_status']), fn ($query) => $query->where('reconciliation_status', $filters['reconciliation_status']))
            ->when(! empty($filters['date_from']), fn ($query) => $query->whereDate('payment_date', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($query) => $query->whereDate('payment_date', '<=', $filters['date_to']))
            ->when(! empty($filters['search']), function ($query) use ($filters) {
                $search = '%' . trim((string) $filters['search']) . '%';

                $query->where(function ($query) use ($search) {
                    $query->where('payment_number', 'like', $search)
                        ->orWhere('payer_name', 'like', $search)
                        ->orWhere('reference', 'like', $search)
                        ->orWhere('method', 'like', $search);
                });
            })
            ->latest('payment_date')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function getReconcilablePayments(int $limit = 50): Collection
    {
        return AccountingPayment::query()
            ->with(['accountingEvent', 'journalEntry'])
            ->where('status', 'posted')
            ->where('reconciliation_status', 'pending')
            ->whereNull('deposit_batch_id')
            ->latest('payment_date')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function getDepositBatches(int $limit = 15): Collection
    {
        return AccountingDepositBatch::query()
            ->with(['payments', 'bankAccount', 'creator', 'reconciler', 'corrector'])
            ->latest('deposit_date')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function getVendorBills(array $filters = [], int $limit = 25): Collection
    {
        return AccountingVendorBill::query()
            ->with(['supplier', 'journalEntry', 'payments', 'adjustments.journalEntry', 'creator', 'poster'])
            ->when(! empty($filters['vendor_bill_status']), fn ($query) => $query->where('status', $filters['vendor_bill_status']))
            ->when(! empty($filters['supplier_id']), fn ($query) => $query->where('supplier_id', (int) $filters['supplier_id']))
            ->when(! empty($filters['due_status']), function ($query) use ($filters) {
                $today = now()->toDateString();

                if ($filters['due_status'] === 'overdue') {
                    $query->whereDate('due_date', '<', $today)->whereIn('status', ['posted', 'partial']);
                } elseif ($filters['due_status'] === 'due_soon') {
                    $query->whereDate('due_date', '>=', $today)
                        ->whereDate('due_date', '<=', now()->addDays(7)->toDateString())
                        ->whereIn('status', ['posted', 'partial']);
                }
            })
            ->when(! empty($filters['date_from']), fn ($query) => $query->whereDate('bill_date', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($query) => $query->whereDate('bill_date', '<=', $filters['date_to']))
            ->when(! empty($filters['search']), function ($query) use ($filters) {
                $search = '%' . trim((string) $filters['search']) . '%';

                $query->where(function ($query) use ($search) {
                    $query->where('bill_number', 'like', $search)
                        ->orWhere('supplier_name', 'like', $search)
                        ->orWhere('reference', 'like', $search)
                        ->orWhere('notes', 'like', $search);
                });
            })
            ->latest('bill_date')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(function (AccountingVendorBill $bill): AccountingVendorBill {
                $paidAmount = $this->paidAmountForVendorBill($bill->id);
                $adjustedAmount = $this->adjustedAmountForVendorBill($bill->id);
                $netAmount = max(0, round((float) $bill->amount - $adjustedAmount, 2));
                $bill->setAttribute('paid_amount', $paidAmount);
                $bill->setAttribute('adjusted_amount', $adjustedAmount);
                $bill->setAttribute('net_amount', $netAmount);
                $bill->setAttribute('open_amount', max(0, round($netAmount - $paidAmount, 2)));

                return $bill;
            });
    }

    public function payablesSummary(): array
    {
        $bills = $this->getVendorBills([], 500);
        $draft = $bills->where('status', 'draft');
        $open = $bills->whereIn('status', ['posted', 'partial']);
        $paid = $bills->where('status', 'paid');
        $dueSoon = $bills->whereIn('status', ['posted', 'partial'])
            ->filter(fn (AccountingVendorBill $bill): bool => $bill->due_date && $bill->due_date->isFuture() && $bill->due_date->lte(now()->addDays(7)));

        return [
            'draft_count' => $draft->count(),
            'draft_amount' => round((float) $draft->sum('amount'), 2),
            'open_count' => $open->filter(fn (AccountingVendorBill $bill): bool => (float) $bill->getAttribute('open_amount') > 0)->count(),
            'open_amount' => round((float) $open->sum('open_amount'), 2),
            'paid_count' => $paid->count(),
            'paid_amount' => round((float) $paid->sum('amount'), 2),
            'due_soon_count' => $dueSoon->count(),
            'due_soon_amount' => round((float) $dueSoon->sum('open_amount'), 2),
        ];
    }

    public function getOpenVendorBills(int $limit = 25): Collection
    {
        return $this->getVendorBills([], 500)
            ->filter(fn (AccountingVendorBill $bill): bool => in_array($bill->status, ['posted', 'partial'], true) && (float) $bill->getAttribute('open_amount') > 0)
            ->sortByDesc('bill_date')
            ->take($limit)
            ->values();
    }

    public function getVendorBillPayments(array $filters = [], int $limit = 25): Collection
    {
        return AccountingVendorBillPayment::query()
            ->with(['vendorBill', 'journalEntry', 'bankAccount', 'creator'])
            ->when(! empty($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(! empty($filters['reconciliation_status']), fn ($query) => $query->where('reconciliation_status', $filters['reconciliation_status']))
            ->when(! empty($filters['date_from']), fn ($query) => $query->whereDate('payment_date', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($query) => $query->whereDate('payment_date', '<=', $filters['date_to']))
            ->when(! empty($filters['search']), function ($query) use ($filters) {
                $search = '%' . trim((string) $filters['search']) . '%';

                $query->where(function ($query) use ($search) {
                    $query->where('payment_number', 'like', $search)
                        ->orWhere('reference', 'like', $search)
                        ->orWhere('method', 'like', $search)
                        ->orWhereHas('vendorBill', function ($query) use ($search) {
                            $query->where('supplier_name', 'like', $search)
                                ->orWhere('bill_number', 'like', $search)
                                ->orWhere('reference', 'like', $search);
                        });
                });
            })
            ->latest('payment_date')
            ->latest('id')
            ->limit($limit)
            ->get()
            ;
    }

    public function payablesAging(): array
    {
        $buckets = [
            'current' => ['label' => 'Current', 'amount' => 0.0, 'count' => 0],
            '1_30' => ['label' => '1-30 Days', 'amount' => 0.0, 'count' => 0],
            '31_60' => ['label' => '31-60 Days', 'amount' => 0.0, 'count' => 0],
            '61_90' => ['label' => '61-90 Days', 'amount' => 0.0, 'count' => 0],
            'over_90' => ['label' => 'Over 90 Days', 'amount' => 0.0, 'count' => 0],
        ];

        $this->getOpenVendorBills(500)->each(function (AccountingVendorBill $bill) use (&$buckets): void {
            $dueDate = Carbon::parse($bill->due_date ?: $bill->bill_date ?: now())->startOfDay();
            $age = $dueDate->isFuture() ? 0 : $dueDate->diffInDays(now()->startOfDay());
            $bucket = match (true) {
                $age === 0 => 'current',
                $age <= 30 => '1_30',
                $age <= 60 => '31_60',
                $age <= 90 => '61_90',
                default => 'over_90',
            };

            $buckets[$bucket]['amount'] = round($buckets[$bucket]['amount'] + (float) $bill->getAttribute('open_amount'), 2);
            $buckets[$bucket]['count']++;
        });

        $totalOpen = round(collect($buckets)->sum('amount'), 2);

        return [
            'buckets' => $buckets,
            'total_open' => $totalOpen,
            'overdue_total' => round($totalOpen - $buckets['current']['amount'], 2),
        ];
    }

    public function depositBatchDetail(AccountingDepositBatch $batch): AccountingDepositBatch
    {
        return $batch->load([
            'payments.accountingEvent',
            'payments.journalEntry',
            'bankAccount',
            'creator',
            'reconciler',
            'corrector',
        ]);
    }

    public function bankReconciliationReport(array $filters = []): array
    {
        $batches = AccountingDepositBatch::query()
            ->with(['payments', 'bankAccount', 'creator', 'reconciler', 'corrector'])
            ->when(! empty($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(! empty($filters['reconciliation_status']), fn ($query) => $query->where('reconciliation_status', $filters['reconciliation_status']))
            ->when(! empty($filters['deposit_account']), fn ($query) => $query->where('deposit_account', $filters['deposit_account']))
            ->when(! empty($filters['date_from']), fn ($query) => $query->whereDate('deposit_date', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($query) => $query->whereDate('deposit_date', '<=', $filters['date_to']))
            ->orderBy('deposit_date')
            ->orderBy('id')
            ->get();

        $vendorPayments = AccountingVendorBillPayment::query()
            ->with(['vendorBill', 'bankAccount'])
            ->where('status', 'posted')
            ->when(! empty($filters['reconciliation_status']), fn ($query) => $query->where('reconciliation_status', $filters['reconciliation_status']))
            ->when(! empty($filters['deposit_account']), fn ($query) => $query->where('cash_account', $filters['deposit_account']))
            ->when(! empty($filters['date_from']), fn ($query) => $query->whereDate('payment_date', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($query) => $query->whereDate('payment_date', '<=', $filters['date_to']))
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();

        $directReceipts = AccountingPayment::query()
            ->with(['accountingEvent', 'bankAccount'])
            ->where('status', 'posted')
            ->whereNull('deposit_batch_id')
            ->when(! empty($filters['reconciliation_status']), fn ($query) => $query->where('reconciliation_status', $filters['reconciliation_status']))
            ->when(! empty($filters['deposit_account']), fn ($query) => $query->where('cash_account', $filters['deposit_account']))
            ->when(! empty($filters['date_from']), fn ($query) => $query->whereDate('payment_date', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($query) => $query->whereDate('payment_date', '<=', $filters['date_to']))
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();

        return [
            'filters' => $filters,
            'batches' => $batches,
            'vendor_payments' => $vendorPayments,
            'direct_receipts' => $directReceipts,
            'posted_count' => $batches->where('status', 'posted')->count(),
            'corrected_count' => $batches->where('status', 'corrected')->count(),
            'reconciled_count' => $batches->where('reconciliation_status', 'reconciled')->count(),
            'posted_total' => round((float) $batches->where('status', 'posted')->sum('total_amount'), 2),
            'corrected_total' => round((float) $batches->where('status', 'corrected')->sum('total_amount'), 2),
            'reconciled_total' => round((float) $batches->where('reconciliation_status', 'reconciled')->sum('total_amount'), 2),
            'vendor_payments_total' => round((float) $vendorPayments->sum('amount'), 2),
            'direct_receipts_total' => round((float) $directReceipts->sum('amount'), 2),
        ];
    }

    public function paymentReconciliationSummary(): array
    {
        $unreconciledReceipts = AccountingPayment::query()
            ->where('status', 'posted')
            ->whereNull('deposit_batch_id')
            ->where('reconciliation_status', '!=', 'reconciled');
        $unreconciledDeposits = AccountingDepositBatch::query()
            ->where('status', 'posted')
            ->where('reconciliation_status', '!=', 'reconciled');
        $unreconciledVendorPayments = AccountingVendorBillPayment::query()
            ->where('status', 'posted')
            ->where('reconciliation_status', '!=', 'reconciled');
        $periodStart = now()->startOfMonth()->toDateString();
        $periodEnd = now()->endOfMonth()->toDateString();
        $reconciledDepositTotal = AccountingDepositBatch::query()
            ->where('status', 'posted')
            ->where('reconciliation_status', 'reconciled')
            ->whereBetween('bank_reconciliation_date', [$periodStart, $periodEnd])
            ->sum('total_amount');
        $reconciledVendorTotal = AccountingVendorBillPayment::query()
            ->where('status', 'posted')
            ->where('reconciliation_status', 'reconciled')
            ->whereBetween('bank_reconciliation_date', [$periodStart, $periodEnd])
            ->sum('amount');
        $reconciledDirectReceiptTotal = AccountingPayment::query()
            ->where('status', 'posted')
            ->whereNull('deposit_batch_id')
            ->where('reconciliation_status', 'reconciled')
            ->whereBetween('bank_reconciliation_date', [$periodStart, $periodEnd])
            ->sum('amount');

        return [
            'pending_count' => (int) (clone $unreconciledReceipts)->count(),
            'pending_amount' => round((float) (clone $unreconciledReceipts)->sum('amount'), 2),
            'deposited_count' => (int) (clone $unreconciledDeposits)->count(),
            'deposited_amount' => round((float) (clone $unreconciledDeposits)->sum('total_amount'), 2),
            'vendor_payment_count' => (int) (clone $unreconciledVendorPayments)->count(),
            'vendor_payment_amount' => round((float) (clone $unreconciledVendorPayments)->sum('amount'), 2),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'reconciled_period_amount' => round((float) $reconciledDepositTotal + (float) $reconciledDirectReceiptTotal - (float) $reconciledVendorTotal, 2),
        ];
    }

    public function receivablesAging(): array
    {
        $buckets = [
            'current' => ['label' => 'Current', 'amount' => 0.0, 'count' => 0],
            '1_30' => ['label' => '1-30 Days', 'amount' => 0.0, 'count' => 0],
            '31_60' => ['label' => '31-60 Days', 'amount' => 0.0, 'count' => 0],
            '61_90' => ['label' => '61-90 Days', 'amount' => 0.0, 'count' => 0],
            'over_90' => ['label' => 'Over 90 Days', 'amount' => 0.0, 'count' => 0],
        ];

        $this->getReceivableEvents(500)->each(function (AccountingEvent $event) use (&$buckets): void {
            $age = max(0, Carbon::parse($event->event_date ?: now())->startOfDay()->diffInDays(now()->startOfDay()));
            $bucket = match (true) {
                $age === 0 => 'current',
                $age <= 30 => '1_30',
                $age <= 60 => '31_60',
                $age <= 90 => '61_90',
                default => 'over_90',
            };

            $buckets[$bucket]['amount'] = round($buckets[$bucket]['amount'] + (float) $event->getAttribute('open_amount'), 2);
            $buckets[$bucket]['count']++;
        });

        $totalOpen = round(collect($buckets)->sum('amount'), 2);

        return [
            'buckets' => $buckets,
            'total_open' => $totalOpen,
            'overdue_total' => round($totalOpen - $buckets['current']['amount'], 2),
        ];
    }

    public function statementCustomerNames(): Collection
    {
        $eventNames = AccountingEvent::query()
            ->whereIn('status', ['journal_posted', 'paid'])
            ->latest('id')
            ->limit(500)
            ->get()
            ->map(fn (AccountingEvent $event): string => trim((string) data_get($event->payload, 'customer_name', '')));

        $paymentNames = AccountingPayment::query()
            ->latest('id')
            ->limit(500)
            ->pluck('payer_name')
            ->map(fn ($name): string => trim((string) $name));

        return $eventNames
            ->merge($paymentNames)
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    public function invoiceDocument(AccountingEvent $event): array
    {
        $invoice = $event->reference_type === AccountingInvoice::class && $event->reference_id
            ? AccountingInvoice::query()->with('lines')->find((int) $event->reference_id)
            : null;
        $payments = AccountingPayment::query()
            ->with('journalEntry')
            ->where('accounting_event_id', $event->id)
            ->latest('payment_date')
            ->latest('id')
            ->get();
        $paidAmount = $this->paidAmountForEvent($event->id);

        return [
            'invoice_number' => $invoice?->invoice_number ?: 'INV-' . str_pad((string) $event->id, 6, '0', STR_PAD_LEFT),
            'invoice' => $invoice,
            'event' => $event,
            'journal_entry' => JournalEntry::query()
                ->where('accounting_event_id', $event->id)
                ->where('source_type', $event->reference_type)
                ->where('source_id', $event->reference_id)
                ->first(),
            'lines' => $invoice
                ? $invoice->lines->map(fn ($line): array => [
                    'description' => $line->description,
                    'amount' => round((float) $line->line_total, 2),
                ])->values()
                : collect([
                [
                    'description' => 'Labor / service revenue',
                    'amount' => round((float) $event->labor_amount, 2),
                ],
                [
                    'description' => 'Parts revenue',
                    'amount' => round((float) $event->parts_amount, 2),
                ],
            ])->filter(fn (array $line): bool => $line['amount'] > 0)->values(),
            'payments' => $payments,
            'paid_amount' => $paidAmount,
            'open_amount' => max(0, round((float) $event->total_amount - $paidAmount, 2)),
        ];
    }

    public function customerStatement(string $customerName): array
    {
        $customerName = trim($customerName);
        $events = AccountingEvent::query()
            ->whereIn('status', ['journal_posted', 'paid'])
            ->latest('event_date')
            ->limit(500)
            ->get()
            ->filter(fn (AccountingEvent $event): bool => strcasecmp((string) data_get($event->payload, 'customer_name', ''), $customerName) === 0)
            ->values();
        $eventIds = $events->pluck('id')->all();
        $payments = AccountingPayment::query()
            ->whereIn('accounting_event_id', $eventIds ?: [0])
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();

        $rows = collect();

        foreach ($events as $event) {
            $rows->push([
                'date' => optional($event->event_date)->toDateString() ?: now()->toDateString(),
                'type' => 'Invoice',
                'reference' => data_get($event->payload, 'invoice_number') ?: 'INV-' . str_pad((string) $event->id, 6, '0', STR_PAD_LEFT),
                'description' => trim((string) data_get($event->payload, 'work_order_number', '') . ' ' . (string) data_get($event->payload, 'title', $event->event_type)),
                'debit' => round((float) $event->total_amount, 2),
                'credit' => 0.0,
            ]);
        }

        foreach ($payments as $payment) {
            $isVoid = $payment->status === 'void';
            $rows->push([
                'date' => optional($payment->payment_date)->toDateString() ?: now()->toDateString(),
                'type' => $isVoid ? 'Voided Payment' : 'Payment',
                'reference' => $payment->payment_number,
                'description' => trim(ucfirst(str_replace('_', ' ', $payment->method)) . ' ' . ($payment->reference ?: '')),
                'debit' => $isVoid ? round((float) $payment->amount, 2) : 0.0,
                'credit' => $isVoid ? 0.0 : round((float) $payment->amount, 2),
            ]);
        }

        $balance = 0.0;
        $statementRows = $rows
            ->sortBy([['date', 'asc'], ['type', 'asc']])
            ->values()
            ->map(function (array $row) use (&$balance): array {
                $balance = round($balance + $row['debit'] - $row['credit'], 2);

                return $row + ['balance' => $balance];
            });

        return [
            'customer_name' => $customerName,
            'rows' => $statementRows,
            'debit_total' => round((float) $statementRows->sum('debit'), 2),
            'credit_total' => round((float) $statementRows->sum('credit'), 2),
            'open_balance' => $balance,
        ];
    }

    public function getRecentJournalEntries(int $limit = 15): Collection
    {
        return JournalEntry::query()
            ->with(['lines', 'postingGroup', 'creator'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function getJournalEntries(array $filters = [], int $limit = 25): Collection
    {
        return JournalEntry::query()
            ->with(['lines', 'postingGroup', 'creator'])
            ->when(! empty($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(! empty($filters['date_from']), fn ($query) => $query->whereDate('entry_date', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($query) => $query->whereDate('entry_date', '<=', $filters['date_to']))
            ->when(! empty($filters['search']), function ($query) use ($filters) {
                $search = '%' . trim((string) $filters['search']) . '%';

                $query->where(function ($query) use ($search) {
                    $query->where('journal_number', 'like', $search)
                        ->orWhere('memo', 'like', $search)
                        ->orWhereHas('lines', function ($query) use ($search) {
                            $query->where('account_code', 'like', $search)
                                ->orWhere('account_name', 'like', $search)
                                ->orWhere('memo', 'like', $search);
                        });
                });
            })
            ->latest('entry_date')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function getPendingManualJournalApprovals(int $limit = 25): Collection
    {
        return JournalEntry::query()
            ->with(['lines', 'creator'])
            ->where('source_type', 'manual')
            ->where('status', 'pending_approval')
            ->latest('approval_submitted_at')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function getReviewableAccountingEvents(int $limit = 25): Collection
    {
        return AccountingEvent::query()
            ->leftJoin('journal_entries', 'journal_entries.accounting_event_id', '=', 'accounting_events.id')
            ->leftJoin('users', 'users.id', '=', 'accounting_events.created_by')
            ->whereNull('journal_entries.id')
            ->select([
                'accounting_events.*',
                'users.name as creator_name',
            ])
            ->latest('accounting_events.id')
            ->limit($limit)
            ->get();
    }

    public function getReviewableInventoryMovements(int $limit = 25): Collection
    {
        return StockMovement::query()
            ->with(['branch', 'product', 'creator'])
            ->leftJoin('journal_entries', function ($join) {
                $join->on('journal_entries.source_id', '=', 'stock_movements.id')
                    ->where('journal_entries.source_type', '=', StockMovement::class);
            })
            ->whereNull('journal_entries.id')
            ->whereIn('stock_movements.type', ['opening', 'adjustment_in', 'adjustment_out'])
            ->select('stock_movements.*')
            ->latest('stock_movements.id')
            ->limit($limit)
            ->get()
            ->map(function (StockMovement $movement): StockMovement {
                $movement->setAttribute('valuation_details', $this->inventoryMovementValuation($movement));

                return $movement;
            })
            ->filter(fn (StockMovement $movement): bool => (bool) data_get($movement->valuation_details, 'can_post'))
            ->values();
    }

    public function inventoryMovementValuationDetails(StockMovement $movement): array
    {
        return $this->inventoryMovementValuation($movement);
    }

    public function createPostingGroup(array $data): AccountingPostingGroup
    {
        return DB::transaction(function () use ($data): AccountingPostingGroup {
            $isDefault = ! empty($data['is_default']);
            $this->ensureDefaultAccounts();
            $this->assertActiveAccountCodes([
                $data['receivable_account'],
                $data['labor_revenue_account'],
                $data['parts_revenue_account'],
            ], 'posting_group_accounts');

            if ($isDefault) {
                AccountingPostingGroup::query()->update(['is_default' => false]);
            }

            return AccountingPostingGroup::query()->create([
                'code' => Str::slug((string) $data['code'], '_'),
                'name' => $data['name'],
                'receivable_account' => $data['receivable_account'],
                'labor_revenue_account' => $data['labor_revenue_account'],
                'parts_revenue_account' => $data['parts_revenue_account'],
                'currency' => strtoupper((string) ($data['currency'] ?? 'USD')),
                'is_default' => $isDefault,
                'is_active' => true,
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    public function createAccount(array $data, ?int $createdBy = null): AccountingAccount
    {
        $code = $this->normalizeAccountCode((string) $data['code']);
        $type = (string) $data['type'];
        $normalBalance = (string) $data['normal_balance'];

        $this->assertValidNormalBalance($type, $normalBalance);

        return DB::transaction(function () use ($data, $code, $type, $normalBalance, $createdBy): AccountingAccount {
            $account = AccountingAccount::query()->where('code', $code)->first();
            $alreadyExists = (bool) $account;
            $payload = [
                'name' => $data['name'],
                'type' => $type,
                'normal_balance' => $normalBalance,
                'is_active' => ! array_key_exists('is_active', $data) || (bool) $data['is_active'],
                'notes' => $data['notes'] ?? null,
            ];

            if ($this->accountMappingColumnsAvailable()) {
                $payload = array_merge($payload, $this->accountMappingPayload($data, $code, $type));
            }

            if ($account && $this->accountCodeIsUsed($account->code)) {
                foreach (['name', 'type', 'normal_balance'] as $field) {
                    if ((string) $account->{$field} !== (string) $payload[$field]) {
                        throw ValidationException::withMessages([
                            'code' => 'Accounts used by posted journal lines cannot be renamed or reclassified. Deactivate the account instead.',
                        ]);
                    }
                }

                $account->forceFill([
                    'is_active' => $payload['is_active'],
                    'notes' => $payload['notes'],
                ] + ($this->accountMappingColumnsAvailable() ? [
                    'ifrs_category' => $payload['ifrs_category'],
                    'statement_report' => $payload['statement_report'],
                    'statement_section' => $payload['statement_section'],
                    'statement_subsection' => $payload['statement_subsection'],
                    'statement_order' => $payload['statement_order'],
                    'cash_flow_category' => $payload['cash_flow_category'],
                ] : []))->save();

                $account = $account->refresh();
                $this->recordAudit('account_saved', $account, "Accounting account {$account->code} updated.", [
                    'code' => $account->code,
                    'action' => 'updated',
                    'changed_fields' => ['is_active', 'notes', 'ifrs_mapping'],
                ], $createdBy);

                return $account;
            }

            $account = AccountingAccount::query()->updateOrCreate(
                ['code' => $code],
                $payload
            );

            $this->recordAudit('account_saved', $account, "Accounting account {$account->code} " . ($alreadyExists ? 'updated' : 'created') . '.', [
                'code' => $account->code,
                'action' => $alreadyExists ? 'updated' : 'created',
                'type' => $account->type,
                'normal_balance' => $account->normal_balance,
                'ifrs_category' => $account->ifrs_category ?? null,
                'statement_report' => $account->statement_report ?? null,
                'statement_section' => $account->statement_section ?? null,
            ], $createdBy);

            return $account;
        });
    }

    public function deactivateAccount(AccountingAccount $account, ?int $createdBy = null): AccountingAccount
    {
        $account->forceFill(['is_active' => false])->save();

        $this->recordAudit('account_deactivated', $account, "Accounting account {$account->code} deactivated.", [
            'code' => $account->code,
            'used_by_posted_journals' => $this->accountCodeIsUsed($account->code),
        ], $createdBy);

        return $account->refresh();
    }

    public function deleteAccount(AccountingAccount $account, ?int $createdBy = null): void
    {
        if ($this->accountCodeIsUsed($account->code)) {
            throw ValidationException::withMessages([
                'account' => 'Accounts used by posted journal lines cannot be deleted. Deactivate the account instead.',
            ]);
        }

        $code = $account->code;
        $account->delete();

        $this->recordAudit('account_deleted', new AccountingAccount(['code' => $code]), "Unused accounting account {$code} deleted.", [
            'code' => $code,
        ], $createdBy);
    }

    public function createPeriodLock(array $data, ?int $createdBy = null): AccountingPeriodLock
    {
        [$start, $end] = $this->normalizePeriodRange($data);
        $checklist = $this->periodCloseChecklist($start, $end);
        $override = ! empty($data['allow_lock_override']);

        if (! $checklist['ready'] && ! $override) {
            throw ValidationException::withMessages([
                'close_checklist' => 'This accounting period is not ready to lock. Resolve the close checklist blockers or use a controlled override.',
            ]);
        }

        $overlapExists = AccountingPeriodLock::query()
            ->whereIn('status', ['locked', 'archived'])
            ->whereDate('period_start', '<=', $end)
            ->whereDate('period_end', '>=', $start)
            ->exists();

        if ($overlapExists) {
            throw ValidationException::withMessages([
                'period_start' => 'This accounting period overlaps an existing locked period.',
            ]);
        }

        $lock = DB::transaction(function () use ($data, $start, $end, $createdBy, $checklist, $override): AccountingPeriodLock {
            $lock = AccountingPeriodLock::query()
                ->where('status', 'closing')
                ->whereDate('period_start', $start)
                ->whereDate('period_end', $end)
                ->latest('id')
                ->first();

            $payload = [
                'period_start' => $start,
                'period_end' => $end,
                'status' => 'locked',
                'locked_by' => $createdBy,
                'locked_at' => now(),
                'notes' => $data['notes'] ?? null,
                'close_checklist' => $checklist,
                'lock_override' => $override,
                'lock_override_reason' => $override ? ($data['lock_override_reason'] ?? null) : null,
            ];

            if ($lock) {
                $lock->forceFill($payload)->save();

                return $lock->refresh();
            }

            return AccountingPeriodLock::query()->create($payload);
        });

        $this->recordAudit('period_locked', $lock, "Accounting period {$start} to {$end} locked.", [
            'period_start' => $start,
            'period_end' => $end,
            'close_ready' => $checklist['ready'],
            'blockers_count' => $checklist['blockers_count'],
            'lock_override' => $override,
        ], $createdBy);

        return $lock;
    }

    public function beginPeriodClose(array $data, ?int $createdBy = null): AccountingPeriodLock
    {
        [$start, $end] = $this->normalizePeriodRange($data);

        $overlapExists = AccountingPeriodLock::query()
            ->whereIn('status', ['locked', 'archived'])
            ->whereDate('period_start', '<=', $end)
            ->whereDate('period_end', '>=', $start)
            ->exists();

        if ($overlapExists) {
            throw ValidationException::withMessages([
                'period_start' => 'This accounting period overlaps an existing locked or archived period.',
            ]);
        }

        $checklist = $this->periodCloseChecklist($start, $end);

        $period = AccountingPeriodLock::query()->updateOrCreate(
            [
                'period_start' => $start,
                'period_end' => $end,
                'status' => 'closing',
            ],
            [
                'closing_started_by' => $createdBy,
                'closing_started_at' => now(),
                'notes' => $data['notes'] ?? null,
                'close_checklist' => $checklist,
            ]
        );

        $this->recordAudit('period_close_started', $period, "Accounting period {$start} to {$end} close review started.", [
            'period_start' => $start,
            'period_end' => $end,
            'close_ready' => $checklist['ready'],
            'blockers_count' => $checklist['blockers_count'],
        ], $createdBy);

        return $period->refresh();
    }

    public function archivePeriod(AccountingPeriodLock $period, ?int $createdBy = null): AccountingPeriodLock
    {
        if ($period->status !== 'locked') {
            throw ValidationException::withMessages([
                'period' => 'Only locked accounting periods can be archived.',
            ]);
        }

        $period->forceFill([
            'status' => 'archived',
            'archived_by' => $createdBy,
            'archived_at' => now(),
        ])->save();

        $this->recordAudit('period_archived', $period, "Accounting period {$period->period_start->toDateString()} to {$period->period_end->toDateString()} archived.", [
            'period_start' => $period->period_start->toDateString(),
            'period_end' => $period->period_end->toDateString(),
        ], $createdBy);

        return $period->refresh();
    }

    public function periodCloseChecklist(?string $periodStart = null, ?string $periodEnd = null): array
    {
        $start = Carbon::parse($periodStart ?: now()->startOfMonth())->toDateString();
        $end = Carbon::parse($periodEnd ?: now()->endOfMonth())->toDateString();

        $unpostedEvents = AccountingEvent::query()
            ->leftJoin('journal_entries', 'journal_entries.accounting_event_id', '=', 'accounting_events.id')
            ->whereNull('journal_entries.id')
            ->whereDate('accounting_events.event_date', '>=', $start)
            ->whereDate('accounting_events.event_date', '<=', $end)
            ->count();

        $unpostedInventoryMovements = StockMovement::query()
            ->with('product')
            ->leftJoin('journal_entries', function ($join) {
                $join->on('journal_entries.source_id', '=', 'stock_movements.id')
                    ->where('journal_entries.source_type', '=', StockMovement::class);
            })
            ->whereNull('journal_entries.id')
            ->whereIn('stock_movements.type', ['opening', 'adjustment_in', 'adjustment_out'])
            ->whereDate('stock_movements.movement_date', '>=', $start)
            ->whereDate('stock_movements.movement_date', '<=', $end)
            ->select('stock_movements.*')
            ->get()
            ->filter(fn (StockMovement $movement): bool => (bool) $this->inventoryMovementValuation($movement)['can_post'])
            ->count();

        $draftVendorBills = AccountingVendorBill::query()
            ->where('status', 'draft')
            ->whereDate('bill_date', '>=', $start)
            ->whereDate('bill_date', '<=', $end)
            ->count();

        $openReceivables = $this->receivablesInPeriod($start, $end);
        $unreconciledPayments = AccountingPayment::query()
            ->where('status', 'posted')
            ->where('reconciliation_status', '!=', 'reconciled')
            ->whereDate('payment_date', '>=', $start)
            ->whereDate('payment_date', '<=', $end)
            ->count();
        $unreconciledDepositBatches = AccountingDepositBatch::query()
            ->where('status', 'posted')
            ->where('reconciliation_status', '!=', 'reconciled')
            ->whereDate('deposit_date', '>=', $start)
            ->whereDate('deposit_date', '<=', $end)
            ->count();
        $unreconciledVendorPayments = AccountingVendorBillPayment::query()
            ->where('status', 'posted')
            ->where('reconciliation_status', '!=', 'reconciled')
            ->whereDate('payment_date', '>=', $start)
            ->whereDate('payment_date', '<=', $end)
            ->count();

        $unapprovedManualJournals = JournalEntry::query()
            ->where('source_type', 'manual')
            ->whereIn('status', ['pending_approval', 'approved'])
            ->whereDate('entry_date', '>=', $start)
            ->whereDate('entry_date', '<=', $end)
            ->count();

        $items = [
            'unposted_accounting_events' => [
                'label' => 'Unposted accounting events',
                'count' => $unpostedEvents,
                'blocking' => $unpostedEvents > 0,
            ],
            'unposted_inventory_movements' => [
                'label' => 'Unposted inventory movements',
                'count' => $unpostedInventoryMovements,
                'blocking' => $unpostedInventoryMovements > 0,
            ],
            'draft_vendor_bills' => [
                'label' => 'Draft vendor bills',
                'count' => $draftVendorBills,
                'blocking' => $draftVendorBills > 0,
            ],
            'open_receivables' => [
                'label' => 'Open receivables',
                'count' => $openReceivables['count'],
                'amount' => $openReceivables['amount'],
                'blocking' => $openReceivables['count'] > 0,
            ],
            'unreconciled_payments' => [
                'label' => 'Unreconciled cash activity',
                'count' => $unreconciledPayments + $unreconciledDepositBatches + $unreconciledVendorPayments,
                'blocking' => ($unreconciledPayments + $unreconciledDepositBatches + $unreconciledVendorPayments) > 0,
            ],
            'unapproved_manual_journals' => [
                'label' => 'Unapproved manual journals',
                'count' => $unapprovedManualJournals,
                'blocking' => $unapprovedManualJournals > 0,
            ],
        ];

        $blockers = collect($items)->filter(fn (array $item): bool => $item['blocking'])->values();

        return [
            'period_start' => $start,
            'period_end' => $end,
            'items' => $items,
            'ready' => $blockers->isEmpty(),
            'blockers_count' => $blockers->count(),
        ];
    }

    public function createPolicy(array $data, ?int $createdBy = null): AccountingPolicy
    {
        return DB::transaction(function () use ($data, $createdBy): AccountingPolicy {
            $isDefault = ! empty($data['is_default']);
            $code = Str::slug((string) $data['code'], '_');
            $alreadyExists = AccountingPolicy::query()->where('code', $code)->exists();
            $this->assertActiveAccountCodes([
                $data['inventory_asset_account'],
                $data['inventory_adjustment_offset_account'],
                $data['inventory_adjustment_expense_account'],
                $data['cogs_account'],
            ], 'accounts');

            if ($isDefault) {
                AccountingPolicy::query()->update(['is_default' => false]);
            }

            $policy = AccountingPolicy::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $data['name'],
                    'currency' => strtoupper((string) ($data['currency'] ?? 'USD')),
                    'inventory_asset_account' => $data['inventory_asset_account'],
                    'inventory_adjustment_offset_account' => $data['inventory_adjustment_offset_account'],
                    'inventory_adjustment_expense_account' => $data['inventory_adjustment_expense_account'],
                    'cogs_account' => $data['cogs_account'],
                    'is_default' => $isDefault,
                    'is_active' => true,
                    'notes' => $data['notes'] ?? null,
                ]
            );

            $this->recordAudit('accounting_policy_changed', $policy, "Accounting policy {$policy->code} " . ($alreadyExists ? 'updated' : 'created') . '.', [
                'code' => $policy->code,
                'action' => $alreadyExists ? 'updated' : 'created',
                'inventory_asset_account' => $policy->inventory_asset_account,
                'inventory_adjustment_offset_account' => $policy->inventory_adjustment_offset_account,
                'inventory_adjustment_expense_account' => $policy->inventory_adjustment_expense_account,
                'cogs_account' => $policy->cogs_account,
                'is_default' => (bool) $policy->is_default,
            ], $createdBy);

            return $policy;
        });
    }

    public function createTaxRate(array $data, ?int $createdBy = null): AccountingTaxRate
    {
        return DB::transaction(function () use ($data, $createdBy): AccountingTaxRate {
            $isDefault = ! empty($data['is_default']);
            $code = Str::slug((string) $data['code'], '_');
            $alreadyExists = AccountingTaxRate::query()->where('code', $code)->exists();
            $this->assertActiveAccountCodes([
                $data['input_tax_account'] ?? '1410 VAT Input Receivable',
                $data['output_tax_account'] ?? '2100 VAT Output Payable',
            ], 'tax_accounts');

            if ($isDefault) {
                AccountingTaxRate::query()->update(['is_default' => false]);
            }

            $rate = AccountingTaxRate::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $data['name'],
                    'rate' => round((float) ($data['rate'] ?? 0), 4),
                    'input_tax_account' => $data['input_tax_account'] ?? '1410 VAT Input Receivable',
                    'output_tax_account' => $data['output_tax_account'] ?? '2100 VAT Output Payable',
                    'is_default' => $isDefault,
                    'is_active' => ! array_key_exists('is_active', $data) || (bool) $data['is_active'],
                    'notes' => $data['notes'] ?? null,
                ]
            );

            $this->recordAudit('tax_rate_changed', $rate, "Tax rate {$rate->code} " . ($alreadyExists ? 'updated' : 'created') . '.', [
                'code' => $rate->code,
                'action' => $alreadyExists ? 'updated' : 'created',
                'rate' => (float) $rate->rate,
                'input_tax_account' => $rate->input_tax_account,
                'output_tax_account' => $rate->output_tax_account,
                'is_default' => (bool) $rate->is_default,
            ], $createdBy);

            return $rate;
        });
    }

    public function recordCustomerPayment(array $data, ?int $createdBy = null): AccountingPayment
    {
        $event = AccountingEvent::query()->findOrFail((int) $data['accounting_event_id']);

        if ($event->status !== 'journal_posted') {
            throw ValidationException::withMessages([
                'accounting_event_id' => 'Payments can only be recorded for journal-posted accounting events.',
            ]);
        }

        $paymentDate = Carbon::parse($data['payment_date'] ?? now()->toDateString())->toDateString();
        $this->assertPeriodOpen($paymentDate, 'recording customer payments');

        $amount = round((float) $data['amount'], 2);
        $remainingAmount = $this->remainingAmountForEvent($event);

        if ($amount <= 0 || $amount > $remainingAmount) {
            throw ValidationException::withMessages([
                'amount' => 'Payment amount must be greater than zero and cannot exceed the open receivable amount.',
            ]);
        }

        $postingGroup = $this->resolvePostingGroup();
        $bankAccount = $this->resolveBankAccount($data['accounting_bank_account_id'] ?? null, 'receipt');
        $cashAccount = $bankAccount->account_code;
        $receivableAccount = $postingGroup?->receivable_account ?: '1100 Accounts Receivable';
        $this->ensureDefaultAccounts();
        $this->assertActiveAccountCodes([$cashAccount, $receivableAccount], 'cash_account');

        return DB::transaction(function () use ($data, $event, $paymentDate, $amount, $remainingAmount, $bankAccount, $cashAccount, $receivableAccount, $createdBy): AccountingPayment {
            $entry = JournalEntry::query()->create([
                'accounting_event_id' => $event->id,
                'journal_number' => $this->nextJournalNumber('PAY'),
                'source_type' => AccountingPayment::class,
                'source_id' => null,
                'status' => 'posted',
                'entry_date' => $paymentDate,
                'currency' => strtoupper((string) ($data['currency'] ?? $event->currency ?: 'USD')),
                'debit_total' => $amount,
                'credit_total' => $amount,
                'memo' => 'Customer payment for ' . $this->eventMemo($event),
                'created_by' => $createdBy,
                'posted_at' => now(),
            ]);

            $entry->lines()->create([
                'account_code' => $cashAccount,
                'account_name' => $this->resolveAccountName($cashAccount, 'Cash On Hand'),
                'line_type' => 'debit',
                'debit' => $amount,
                'credit' => 0,
                'memo' => 'Customer payment received.',
            ]);

            $entry->lines()->create([
                'account_code' => $receivableAccount,
                'account_name' => $this->resolveAccountName($receivableAccount, 'Accounts Receivable'),
                'line_type' => 'credit',
                'debit' => 0,
                'credit' => $amount,
                'memo' => 'Receivable settled by customer payment.',
            ]);

            $payment = AccountingPayment::query()->create([
                'accounting_event_id' => $event->id,
                'journal_entry_id' => $entry->id,
                'accounting_bank_account_id' => $bankAccount->id,
                'payment_number' => $this->nextJournalNumber('PMT'),
                'payment_date' => $paymentDate,
                'payer_name' => $data['payer_name'] ?? data_get($event->payload, 'customer_name'),
                'method' => $data['method'] ?? 'cash',
                'reference' => $data['reference'] ?? null,
                'currency' => strtoupper((string) ($data['currency'] ?? $event->currency ?: 'USD')),
                'amount' => $amount,
                'cash_account' => $cashAccount,
                'receivable_account' => $receivableAccount,
                'status' => 'posted',
                'reconciliation_status' => 'pending',
                'notes' => $data['notes'] ?? null,
                'created_by' => $createdBy,
                'posted_at' => now(),
            ]);

            $entry->forceFill(['source_id' => $payment->id])->save();

            if (round($remainingAmount - $amount, 2) <= 0) {
                $event->forceFill(['status' => 'paid'])->save();
            }

            $this->syncInvoiceStatusForEvent($event);

            $this->recordAudit('customer_payment_recorded', $payment, "Customer payment {$payment->payment_number} recorded.", [
                'accounting_event_id' => $event->id,
                'journal_entry_id' => $entry->id,
                'amount' => $amount,
            ], $createdBy);

        return $payment->load(['accountingEvent', 'journalEntry', 'bankAccount', 'creator']);
        });
    }

    public function createInvoice(array $data, ?int $createdBy = null): AccountingInvoice
    {
        $issueDate = Carbon::parse($data['issue_date'] ?? now()->toDateString())->toDateString();
        $dueDate = ! empty($data['due_date']) ? Carbon::parse($data['due_date'])->toDateString() : null;
        $lines = collect($data['lines'] ?? [])
            ->map(function (array $line): array {
                $quantity = round((float) ($line['quantity'] ?? 1), 3);
                $unitPrice = round((float) ($line['unit_price'] ?? 0), 2);

                return [
                    'description' => trim((string) ($line['description'] ?? 'Invoice line')),
                    'account_code' => $this->normalizeAccountCode((string) ($line['account_code'] ?? '4100 Service Labor Revenue')),
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => round($quantity * $unitPrice, 2),
                ];
            })
            ->filter(fn (array $line): bool => $line['description'] !== '' && $line['quantity'] > 0 && $line['unit_price'] >= 0 && $line['line_total'] > 0)
            ->values();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'lines' => 'Add at least one positive invoice line.',
            ]);
        }

        $taxAmount = round((float) ($data['tax_amount'] ?? 0), 2);
        $subtotal = round((float) $lines->sum('line_total'), 2);
        $totalAmount = round($subtotal + $taxAmount, 2);
        $receivableAccount = $this->normalizeAccountCode((string) ($data['receivable_account'] ?? '1100 Accounts Receivable'));
        $revenueAccount = $lines->first()['account_code'];
        $taxAccount = $taxAmount > 0
            ? $this->normalizeAccountCode((string) ($data['tax_account'] ?? '2100 VAT Output Payable'))
            : null;

        if ($taxAmount < 0) {
            throw ValidationException::withMessages([
                'tax_amount' => 'Tax amount cannot be negative.',
            ]);
        }

        $this->ensureDefaultAccounts();
        $accountCodes = $lines->pluck('account_code')->push($receivableAccount);
        if ($taxAccount) {
            $accountCodes->push($taxAccount);
        }
        $this->assertActiveAccountCodes($accountCodes->all(), 'invoice_accounts');

        return DB::transaction(function () use ($data, $lines, $issueDate, $dueDate, $subtotal, $taxAmount, $totalAmount, $receivableAccount, $revenueAccount, $taxAccount, $createdBy): AccountingInvoice {
            $invoice = AccountingInvoice::query()->create([
                'invoice_number' => $this->nextJournalNumber('INV'),
                'customer_name' => $data['customer_name'],
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'currency' => strtoupper((string) ($data['currency'] ?? 'USD')),
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'receivable_account' => $receivableAccount,
                'revenue_account' => $revenueAccount,
                'tax_account' => $taxAccount,
                'status' => 'draft',
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $createdBy,
            ]);

            $lines->each(function (array $line, int $index) use ($invoice): void {
                $invoice->lines()->create($line + ['sort_order' => $index + 1]);
            });

            $this->recordAudit('customer_invoice_created', $invoice, "Invoice {$invoice->invoice_number} created.", [
                'customer_name' => $invoice->customer_name,
                'total_amount' => (float) $invoice->total_amount,
            ], $createdBy);

            return $invoice->load(['lines', 'creator']);
        });
    }

    public function postInvoice(AccountingInvoice $invoice, ?int $createdBy = null): JournalEntry
    {
        $invoice->loadMissing('lines');

        if ($invoice->status !== 'draft') {
            throw ValidationException::withMessages([
                'invoice' => 'Only draft invoices can be posted.',
            ]);
        }

        if ($invoice->accounting_event_id !== null || $invoice->journal_entry_id !== null) {
            throw ValidationException::withMessages([
                'invoice' => 'This invoice is already posted.',
            ]);
        }

        return DB::transaction(function () use ($invoice, $createdBy): JournalEntry {
            $event = AccountingEvent::query()->create([
                'event_type' => 'customer_invoice.posted',
                'reference_type' => AccountingInvoice::class,
                'reference_id' => $invoice->id,
                'status' => 'posted',
                'event_date' => $invoice->issue_date,
                'currency' => $invoice->currency ?: 'USD',
                'labor_amount' => $invoice->total_amount,
                'parts_amount' => 0,
                'total_amount' => $invoice->total_amount,
                'payload' => [
                    'invoice_number' => $invoice->invoice_number,
                    'customer_name' => $invoice->customer_name,
                    'title' => $invoice->reference ?: 'Customer invoice',
                    'tax_amount' => (float) $invoice->tax_amount,
                    'tax_account' => $invoice->tax_account,
                    'invoice_lines' => $invoice->lines->map(fn ($line): array => [
                        'description' => $line->description,
                        'account_code' => $line->account_code,
                        'amount' => (float) $line->line_total,
                    ])->values()->all(),
                ],
                'created_by' => $createdBy,
            ]);

            $entry = $this->postAccountingEvent($event, null, $createdBy);

            $invoice->forceFill([
                'accounting_event_id' => $event->id,
                'journal_entry_id' => $entry->id,
                'status' => 'posted',
                'posted_by' => $createdBy,
                'posted_at' => now(),
            ])->save();

            $this->recordAudit('customer_invoice_posted', $invoice, "Invoice {$invoice->invoice_number} posted.", [
                'accounting_event_id' => $event->id,
                'journal_entry_id' => $entry->id,
                'total_amount' => (float) $invoice->total_amount,
            ], $createdBy);

            return $entry->load(['lines', 'postingGroup', 'creator']);
        });
    }

    public function createDepositBatch(array $data, ?int $createdBy = null): AccountingDepositBatch
    {
        $paymentIds = collect($data['payment_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($paymentIds->isEmpty()) {
            throw ValidationException::withMessages([
                'payment_ids' => 'Select at least one posted payment to deposit.',
            ]);
        }

        $depositDate = Carbon::parse($data['deposit_date'] ?? now()->toDateString())->toDateString();
        $this->assertPeriodOpen($depositDate, 'creating deposit batches');

        return DB::transaction(function () use ($data, $paymentIds, $depositDate, $createdBy): AccountingDepositBatch {
            $payments = AccountingPayment::query()
                ->whereIn('id', $paymentIds->all())
                ->lockForUpdate()
                ->get();

            if ($payments->count() !== $paymentIds->count()) {
                throw ValidationException::withMessages([
                    'payment_ids' => 'One or more selected payments could not be found.',
                ]);
            }

            $invalidPayment = $payments->first(fn (AccountingPayment $payment): bool => $payment->status !== 'posted' || $payment->reconciliation_status !== 'pending' || $payment->deposit_batch_id !== null);

            if ($invalidPayment) {
                throw ValidationException::withMessages([
                    'payment_ids' => "Payment {$invalidPayment->payment_number} is not available for deposit.",
                ]);
            }

            $currencies = $payments->pluck('currency')->filter()->unique()->values();

            if ($currencies->count() > 1) {
                throw ValidationException::withMessages([
                    'payment_ids' => 'Deposit batches cannot mix currencies.',
                ]);
            }

            $currency = strtoupper((string) ($data['currency'] ?? $currencies->first() ?: 'USD'));
            $bankAccount = $this->resolveBankAccount($data['accounting_bank_account_id'] ?? null, 'receipt');
            $depositAccount = $bankAccount->account_code;
            $this->ensureDefaultAccounts();
            $this->assertActiveAccountCodes([$depositAccount], 'deposit_account');
            $totalAmount = round((float) $payments->sum('amount'), 2);

            $batch = AccountingDepositBatch::query()->create([
                'accounting_bank_account_id' => $bankAccount->id,
                'deposit_number' => $this->nextJournalNumber('DEP'),
                'deposit_date' => $depositDate,
                'deposit_account' => $depositAccount,
                'currency' => $currency,
                'total_amount' => $totalAmount,
                'payments_count' => $payments->count(),
                'status' => 'posted',
                'reconciliation_status' => 'pending',
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $createdBy,
                'posted_at' => now(),
            ]);

            AccountingPayment::query()
                ->whereIn('id', $payments->pluck('id')->all())
                ->update([
                    'deposit_batch_id' => $batch->id,
                    'reconciliation_status' => 'deposited',
                    'reconciled_by' => $createdBy,
                    'reconciled_at' => now(),
                    'updated_at' => now(),
                ]);

            $this->recordAudit('payment_deposit_batch_posted', $batch, "Deposit batch {$batch->deposit_number} posted.", [
                'payment_ids' => $payments->pluck('id')->all(),
                'payments_count' => $payments->count(),
                'total_amount' => $totalAmount,
            ], $createdBy);

            return $batch->load(['payments', 'bankAccount', 'creator']);
        });
    }

    public function correctDepositBatch(AccountingDepositBatch $batch, array $data = [], ?int $createdBy = null): AccountingDepositBatch
    {
        $batch->loadMissing('payments');

        if ($batch->status !== 'posted') {
            throw ValidationException::withMessages([
                'deposit_batch' => 'Only posted deposit batches can be corrected.',
            ]);
        }

        if ($batch->reconciliation_status === 'reconciled') {
            throw ValidationException::withMessages([
                'deposit_batch' => 'Reconciled deposit batches cannot be corrected. Reverse through a controlled accounting correction first.',
            ]);
        }

        $correctionDate = now()->toDateString();
        $this->assertPeriodOpen($correctionDate, 'correcting deposit batches');

        return DB::transaction(function () use ($batch, $data, $createdBy): AccountingDepositBatch {
            $lockedBatch = AccountingDepositBatch::query()
                ->whereKey($batch->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedBatch->status !== 'posted') {
                throw ValidationException::withMessages([
                    'deposit_batch' => 'Only posted deposit batches can be corrected.',
                ]);
            }

            if ($lockedBatch->reconciliation_status === 'reconciled') {
                throw ValidationException::withMessages([
                    'deposit_batch' => 'Reconciled deposit batches cannot be corrected. Reverse through a controlled accounting correction first.',
                ]);
            }

            AccountingPayment::query()
                ->where('deposit_batch_id', $lockedBatch->id)
                ->where('reconciliation_status', 'deposited')
                ->update([
                    'deposit_batch_id' => null,
                    'reconciliation_status' => 'pending',
                    'reconciled_by' => null,
                    'reconciled_at' => null,
                    'updated_at' => now(),
                ]);

            $lockedBatch->forceFill([
                'status' => 'corrected',
                'corrected_by' => $createdBy,
                'corrected_at' => now(),
                'correction_reason' => $data['correction_reason'] ?? null,
            ])->save();

            $this->recordAudit('deposit_batch_corrected', $lockedBatch, "Deposit batch {$lockedBatch->deposit_number} corrected.", [
                'deposit_batch_id' => $lockedBatch->id,
                'total_amount' => (float) $lockedBatch->total_amount,
                'correction_reason' => $lockedBatch->correction_reason,
            ], $createdBy);

            return $lockedBatch->load(['payments.accountingEvent', 'payments.journalEntry', 'creator', 'corrector']);
        });
    }

    public function reconcileDepositBatch(AccountingDepositBatch $batch, array $data = [], ?int $createdBy = null): AccountingDepositBatch
    {
        if ($batch->status !== 'posted') {
            throw ValidationException::withMessages([
                'deposit_batch' => 'Only posted deposit batches can be reconciled.',
            ]);
        }

        if ($batch->reconciliation_status === 'reconciled') {
            throw ValidationException::withMessages([
                'deposit_batch' => 'This deposit batch is already reconciled.',
            ]);
        }

        $reconciliationDate = Carbon::parse($data['bank_reconciliation_date'] ?? $batch->deposit_date ?? now()->toDateString())->toDateString();

        return DB::transaction(function () use ($batch, $data, $reconciliationDate, $createdBy): AccountingDepositBatch {
            $lockedBatch = AccountingDepositBatch::query()
                ->whereKey($batch->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedBatch->status !== 'posted' || $lockedBatch->reconciliation_status === 'reconciled') {
                throw ValidationException::withMessages([
                    'deposit_batch' => 'This deposit batch is not available for reconciliation.',
                ]);
            }

            $lockedBatch->forceFill([
                'reconciliation_status' => 'reconciled',
                'bank_reconciliation_date' => $reconciliationDate,
                'bank_reference' => $data['bank_reference'] ?? $lockedBatch->reference,
                'reconciled_by' => $createdBy,
                'reconciled_at' => now(),
            ])->save();

            AccountingPayment::query()
                ->where('deposit_batch_id', $lockedBatch->id)
                ->where('status', 'posted')
                ->update([
                    'reconciliation_status' => 'reconciled',
                    'bank_reconciliation_date' => $reconciliationDate,
                    'bank_reference' => $data['bank_reference'] ?? $lockedBatch->reference,
                    'reconciled_by' => $createdBy,
                    'reconciled_at' => now(),
                    'updated_at' => now(),
                ]);

            $this->recordAudit('deposit_batch_reconciled', $lockedBatch, "Deposit batch {$lockedBatch->deposit_number} reconciled.", [
                'deposit_batch_id' => $lockedBatch->id,
                'bank_reconciliation_date' => $reconciliationDate,
                'bank_reference' => $lockedBatch->bank_reference,
                'total_amount' => (float) $lockedBatch->total_amount,
            ], $createdBy);

            return $lockedBatch->load(['payments.accountingEvent', 'payments.journalEntry', 'bankAccount', 'creator', 'reconciler', 'corrector']);
        });
    }

    public function reconcileCustomerPayment(AccountingPayment $payment, array $data = [], ?int $createdBy = null): AccountingPayment
    {
        if ($payment->status !== 'posted') {
            throw ValidationException::withMessages([
                'payment' => 'Only posted customer payments can be reconciled.',
            ]);
        }

        if ($payment->deposit_batch_id !== null || $payment->reconciliation_status === 'deposited') {
            throw ValidationException::withMessages([
                'payment' => 'Deposited customer payments must be reconciled through their deposit batch.',
            ]);
        }

        if ($payment->reconciliation_status === 'reconciled') {
            throw ValidationException::withMessages([
                'payment' => 'This customer payment is already reconciled.',
            ]);
        }

        $reconciliationDate = Carbon::parse($data['bank_reconciliation_date'] ?? $payment->payment_date ?? now()->toDateString())->toDateString();

        $payment->forceFill([
            'reconciliation_status' => 'reconciled',
            'bank_reconciliation_date' => $reconciliationDate,
            'bank_reference' => $data['bank_reference'] ?? $payment->reference,
            'reconciled_by' => $createdBy,
            'reconciled_at' => now(),
        ])->save();

        $this->recordAudit('customer_payment_reconciled', $payment, "Customer payment {$payment->payment_number} reconciled.", [
            'payment_id' => $payment->id,
            'bank_reconciliation_date' => $reconciliationDate,
            'bank_reference' => $payment->bank_reference,
            'amount' => (float) $payment->amount,
        ], $createdBy);

        return $payment->load(['accountingEvent', 'journalEntry', 'bankAccount', 'creator', 'reconciler']);
    }

    public function reconcileVendorBillPayment(AccountingVendorBillPayment $payment, array $data = [], ?int $createdBy = null): AccountingVendorBillPayment
    {
        if ($payment->status !== 'posted') {
            throw ValidationException::withMessages([
                'payment' => 'Only posted vendor payments can be reconciled.',
            ]);
        }

        if ($payment->reconciliation_status === 'reconciled') {
            throw ValidationException::withMessages([
                'payment' => 'This vendor payment is already reconciled.',
            ]);
        }

        $reconciliationDate = Carbon::parse($data['bank_reconciliation_date'] ?? $payment->payment_date ?? now()->toDateString())->toDateString();

        $payment->forceFill([
            'reconciliation_status' => 'reconciled',
            'bank_reconciliation_date' => $reconciliationDate,
            'bank_reference' => $data['bank_reference'] ?? $payment->reference,
            'reconciled_by' => $createdBy,
            'reconciled_at' => now(),
        ])->save();

        $this->recordAudit('vendor_payment_reconciled', $payment, "Vendor payment {$payment->payment_number} reconciled.", [
            'vendor_payment_id' => $payment->id,
            'bank_reconciliation_date' => $reconciliationDate,
            'bank_reference' => $payment->bank_reference,
            'amount' => (float) $payment->amount,
        ], $createdBy);

        return $payment->load(['vendorBill', 'journalEntry', 'bankAccount', 'creator', 'reconciler']);
    }

    public function createVendorBill(array $data, ?int $createdBy = null): AccountingVendorBill
    {
        $billDate = Carbon::parse($data['bill_date'] ?? now()->toDateString())->toDateString();
        $dueDate = ! empty($data['due_date']) ? Carbon::parse($data['due_date'])->toDateString() : null;
        $amount = round((float) ($data['amount'] ?? 0), 2);
        $taxAmount = round((float) ($data['tax_amount'] ?? 0), 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Vendor bill amount must be greater than zero.',
            ]);
        }

        if ($taxAmount < 0 || $taxAmount > $amount) {
            throw ValidationException::withMessages([
                'tax_amount' => 'Tax amount cannot be negative or greater than the vendor bill amount.',
            ]);
        }

        $taxRate = ! empty($data['accounting_tax_rate_id'])
            ? AccountingTaxRate::query()->where('is_active', true)->find((int) $data['accounting_tax_rate_id'])
            : null;
        $expenseAccount = trim((string) ($data['expense_account'] ?? '5200 Operating Expense')) ?: '5200 Operating Expense';
        $payableAccount = trim((string) ($data['payable_account'] ?? '2000 Accounts Payable')) ?: '2000 Accounts Payable';
        $taxAccount = trim((string) ($data['tax_account'] ?? $taxRate?->input_tax_account ?? '1410 VAT Input Receivable')) ?: '1410 VAT Input Receivable';
        $this->ensureDefaultAccounts();
        $this->assertActiveAccountCodes($taxAmount > 0 ? [$expenseAccount, $payableAccount, $taxAccount] : [$expenseAccount, $payableAccount], 'vendor_bill_accounts');

        return AccountingVendorBill::query()->create([
            'supplier_id' => $data['supplier_id'] ?? null,
            'accounting_tax_rate_id' => $taxRate?->id,
            'bill_number' => $this->nextJournalNumber('VBILL'),
            'bill_date' => $billDate,
            'due_date' => $dueDate,
            'supplier_name' => $data['supplier_name'] ?? null,
            'reference' => $data['reference'] ?? null,
            'currency' => strtoupper((string) ($data['currency'] ?? 'USD')),
            'amount' => $amount,
            'tax_amount' => $taxAmount,
            'expense_account' => $expenseAccount,
            'payable_account' => $payableAccount,
            'tax_account' => $taxAmount > 0 ? $taxAccount : null,
            'status' => 'draft',
            'notes' => $data['notes'] ?? null,
            'attachment_name' => $data['attachment_name'] ?? null,
            'attachment_reference' => $data['attachment_reference'] ?? null,
            'attachment_url' => $data['attachment_url'] ?? null,
            'created_by' => $createdBy,
        ])->load(['supplier', 'creator']);
    }

    public function postVendorBill(AccountingVendorBill $bill, ?int $createdBy = null): JournalEntry
    {
        if ($bill->status !== 'draft') {
            throw ValidationException::withMessages([
                'vendor_bill' => 'Only draft vendor bills can be posted.',
            ]);
        }

        if ($bill->journal_entry_id !== null || JournalEntry::query()->where('source_type', AccountingVendorBill::class)->where('source_id', $bill->id)->exists()) {
            throw ValidationException::withMessages([
                'vendor_bill' => 'This vendor bill already has a journal entry.',
            ]);
        }

        $entryDate = optional($bill->bill_date)->toDateString() ?: now()->toDateString();
        $this->assertPeriodOpen($entryDate, 'posting vendor bills');
        $amount = round((float) $bill->amount, 2);
        $taxAmount = round((float) $bill->tax_amount, 2);
        $expenseAmount = round($amount - $taxAmount, 2);
        $this->ensureDefaultAccounts();
        $this->assertActiveAccountCodes($taxAmount > 0 ? [$bill->expense_account, $bill->payable_account, $bill->tax_account] : [$bill->expense_account, $bill->payable_account], 'vendor_bill_accounts');

        return DB::transaction(function () use ($bill, $entryDate, $amount, $taxAmount, $expenseAmount, $createdBy): JournalEntry {
            $entry = JournalEntry::query()->create([
                'journal_number' => $this->nextJournalNumber('AP'),
                'source_type' => AccountingVendorBill::class,
                'source_id' => $bill->id,
                'status' => 'posted',
                'entry_date' => $entryDate,
                'currency' => $bill->currency ?: 'USD',
                'debit_total' => $amount,
                'credit_total' => $amount,
                'memo' => 'Vendor bill ' . $bill->bill_number . ' ' . ($bill->supplier_name ?: $bill->supplier?->name ?: ''),
                'created_by' => $createdBy,
                'posted_at' => now(),
            ]);

            $entry->lines()->create([
                'account_code' => $bill->expense_account,
                'account_name' => $this->resolveAccountName($bill->expense_account, 'Operating Expense'),
                'line_type' => 'debit',
                'debit' => $expenseAmount,
                'credit' => 0,
                'memo' => 'Vendor bill expense.',
            ]);

            if ($taxAmount > 0) {
                $entry->lines()->create([
                    'account_code' => $bill->tax_account ?: '1410 VAT Input Receivable',
                    'account_name' => $this->resolveAccountName($bill->tax_account ?: '1410 VAT Input Receivable', 'VAT Input Receivable'),
                    'line_type' => 'debit',
                    'debit' => $taxAmount,
                    'credit' => 0,
                    'memo' => 'Recoverable input tax from vendor bill.',
                ]);
            }

            $entry->lines()->create([
                'account_code' => $bill->payable_account,
                'account_name' => $this->resolveAccountName($bill->payable_account, 'Accounts Payable'),
                'line_type' => 'credit',
                'debit' => 0,
                'credit' => $amount,
                'memo' => 'Vendor bill payable.',
            ]);

            $bill->forceFill([
                'journal_entry_id' => $entry->id,
                'status' => 'posted',
                'posted_by' => $createdBy,
                'posted_at' => now(),
            ])->save();

            $this->recordAudit('vendor_bill_posted', $bill, "Vendor bill {$bill->bill_number} posted.", [
                'journal_entry_id' => $entry->id,
                'amount' => $amount,
                'tax_amount' => $taxAmount,
            ], $createdBy);

            return $entry->load(['lines', 'creator']);
        });
    }

    public function createVendorBillCreditNote(AccountingVendorBill $bill, array $data, ?int $createdBy = null): AccountingVendorBillAdjustment
    {
        $bill->loadMissing(['adjustments', 'payments']);

        if (! in_array($bill->status, ['posted', 'partial', 'paid'], true)) {
            throw ValidationException::withMessages([
                'vendor_bill' => 'Only posted vendor bills can receive credit notes.',
            ]);
        }

        $adjustmentDate = Carbon::parse($data['adjustment_date'] ?? now()->toDateString())->toDateString();
        $this->assertPeriodOpen($adjustmentDate, 'posting vendor bill credit notes');
        $amount = round((float) ($data['amount'] ?? 0), 2);
        $taxAmount = round((float) ($data['tax_amount'] ?? 0), 2);
        $remainingBeforeAdjustment = $this->remainingAmountForVendorBill($bill);

        if ($amount <= 0 || $amount > $remainingBeforeAdjustment) {
            throw ValidationException::withMessages([
                'amount' => 'Credit note amount must be greater than zero and cannot exceed the open payable amount.',
            ]);
        }

        if ($taxAmount < 0 || $taxAmount > $amount) {
            throw ValidationException::withMessages([
                'tax_amount' => 'Tax amount cannot be negative or greater than the credit note amount.',
            ]);
        }

        $expenseAccount = $bill->expense_account ?: '5200 Operating Expense';
        $payableAccount = $bill->payable_account ?: '2000 Accounts Payable';
        $taxAccount = $taxAmount > 0 ? ($bill->tax_account ?: '1410 VAT Input Receivable') : null;
        $expenseReduction = round($amount - $taxAmount, 2);
        $this->ensureDefaultAccounts();
        $this->assertActiveAccountCodes($taxAccount ? [$expenseAccount, $payableAccount, $taxAccount] : [$expenseAccount, $payableAccount], 'vendor_bill_adjustment_accounts');

        return DB::transaction(function () use ($bill, $data, $adjustmentDate, $amount, $taxAmount, $expenseReduction, $expenseAccount, $payableAccount, $taxAccount, $createdBy): AccountingVendorBillAdjustment {
            $entry = JournalEntry::query()->create([
                'journal_number' => $this->nextJournalNumber('APCR'),
                'source_type' => AccountingVendorBillAdjustment::class,
                'source_id' => null,
                'status' => 'posted',
                'entry_date' => $adjustmentDate,
                'currency' => $bill->currency ?: 'USD',
                'debit_total' => $amount,
                'credit_total' => $amount,
                'memo' => 'Vendor bill credit note for ' . $bill->bill_number,
                'created_by' => $createdBy,
                'posted_at' => now(),
            ]);

            $entry->lines()->create([
                'account_code' => $payableAccount,
                'account_name' => $this->resolveAccountName($payableAccount, 'Accounts Payable'),
                'line_type' => 'debit',
                'debit' => $amount,
                'credit' => 0,
                'memo' => 'Reduce payable from vendor credit note.',
            ]);

            if ($expenseReduction > 0) {
                $entry->lines()->create([
                    'account_code' => $expenseAccount,
                    'account_name' => $this->resolveAccountName($expenseAccount, 'Operating Expense'),
                    'line_type' => 'credit',
                    'debit' => 0,
                    'credit' => $expenseReduction,
                    'memo' => 'Reduce vendor bill expense.',
                ]);
            }

            if ($taxAmount > 0) {
                $entry->lines()->create([
                    'account_code' => $taxAccount ?: '1410 VAT Input Receivable',
                    'account_name' => $this->resolveAccountName($taxAccount ?: '1410 VAT Input Receivable', 'VAT Input Receivable'),
                    'line_type' => 'credit',
                    'debit' => 0,
                    'credit' => $taxAmount,
                    'memo' => 'Reduce recoverable input tax from vendor credit note.',
                ]);
            }

            $adjustment = AccountingVendorBillAdjustment::query()->create([
                'accounting_vendor_bill_id' => $bill->id,
                'journal_entry_id' => $entry->id,
                'adjustment_number' => $this->nextJournalNumber('VCN'),
                'type' => 'credit_note',
                'adjustment_date' => $adjustmentDate,
                'amount' => $amount,
                'tax_amount' => $taxAmount,
                'expense_account' => $expenseAccount,
                'payable_account' => $payableAccount,
                'tax_account' => $taxAccount,
                'status' => 'posted',
                'reference' => $data['reference'] ?? null,
                'reason' => $data['reason'] ?? null,
                'created_by' => $createdBy,
                'posted_at' => now(),
            ]);

            $entry->forceFill(['source_id' => $adjustment->id])->save();
            $remainingAmount = $this->remainingAmountForVendorBill($bill->refresh());
            $bill->forceFill([
                'status' => $remainingAmount <= 0 ? 'paid' : ($this->paidAmountForVendorBill($bill->id) > 0 ? 'partial' : 'posted'),
            ])->save();

            $this->recordAudit('vendor_bill_credit_note_posted', $adjustment, "Credit note {$adjustment->adjustment_number} posted for {$bill->bill_number}.", [
                'accounting_vendor_bill_id' => $bill->id,
                'journal_entry_id' => $entry->id,
                'amount' => $amount,
                'tax_amount' => $taxAmount,
            ], $createdBy);

            return $adjustment->load(['vendorBill', 'journalEntry', 'creator']);
        });
    }

    public function recordVendorBillPayment(array $data, ?int $createdBy = null): AccountingVendorBillPayment
    {
        $bill = AccountingVendorBill::query()->findOrFail((int) $data['accounting_vendor_bill_id']);

        if (! in_array($bill->status, ['posted', 'partial'], true)) {
            throw ValidationException::withMessages([
                'accounting_vendor_bill_id' => 'Payments can only be recorded for posted vendor bills.',
            ]);
        }

        $paymentDate = Carbon::parse($data['payment_date'] ?? now()->toDateString())->toDateString();
        $this->assertPeriodOpen($paymentDate, 'recording vendor bill payments');
        $amount = round((float) ($data['amount'] ?? 0), 2);
        $remainingAmount = $this->remainingAmountForVendorBill($bill);

        if ($amount <= 0 || $amount > $remainingAmount) {
            throw ValidationException::withMessages([
                'amount' => 'Vendor payment amount must be greater than zero and cannot exceed the open payable amount.',
            ]);
        }

        $bankAccount = $this->resolveBankAccount($data['accounting_bank_account_id'] ?? null, 'payment');
        $cashAccount = $bankAccount->account_code;
        $payableAccount = $bill->payable_account ?: '2000 Accounts Payable';
        $this->ensureDefaultAccounts();
        $this->assertActiveAccountCodes([$cashAccount, $payableAccount], 'cash_account');

        return DB::transaction(function () use ($data, $bill, $paymentDate, $amount, $remainingAmount, $bankAccount, $cashAccount, $payableAccount, $createdBy): AccountingVendorBillPayment {
            $entry = JournalEntry::query()->create([
                'journal_number' => $this->nextJournalNumber('VPAY'),
                'source_type' => AccountingVendorBillPayment::class,
                'source_id' => null,
                'status' => 'posted',
                'entry_date' => $paymentDate,
                'currency' => strtoupper((string) ($data['currency'] ?? $bill->currency ?: 'USD')),
                'debit_total' => $amount,
                'credit_total' => $amount,
                'memo' => 'Vendor payment for ' . $bill->bill_number,
                'created_by' => $createdBy,
                'posted_at' => now(),
            ]);

            $entry->lines()->create([
                'account_code' => $payableAccount,
                'account_name' => $this->resolveAccountName($payableAccount, 'Accounts Payable'),
                'line_type' => 'debit',
                'debit' => $amount,
                'credit' => 0,
                'memo' => 'Payable settled by vendor payment.',
            ]);

            $entry->lines()->create([
                'account_code' => $cashAccount,
                'account_name' => $this->resolveAccountName($cashAccount, 'Bank Account'),
                'line_type' => 'credit',
                'debit' => 0,
                'credit' => $amount,
                'memo' => 'Cash or bank paid to vendor.',
            ]);

            $payment = AccountingVendorBillPayment::query()->create([
                'accounting_vendor_bill_id' => $bill->id,
                'journal_entry_id' => $entry->id,
                'accounting_bank_account_id' => $bankAccount->id,
                'payment_number' => $this->nextJournalNumber('VPMT'),
                'payment_date' => $paymentDate,
                'method' => $data['method'] ?? 'bank_transfer',
                'reference' => $data['reference'] ?? null,
                'currency' => strtoupper((string) ($data['currency'] ?? $bill->currency ?: 'USD')),
                'amount' => $amount,
                'cash_account' => $cashAccount,
                'payable_account' => $payableAccount,
                'status' => 'posted',
                'reconciliation_status' => 'pending',
                'notes' => $data['notes'] ?? null,
                'created_by' => $createdBy,
                'posted_at' => now(),
            ]);

            $entry->forceFill(['source_id' => $payment->id])->save();

            $bill->forceFill([
                'status' => round($remainingAmount - $amount, 2) <= 0 ? 'paid' : 'partial',
            ])->save();

            $this->recordAudit('vendor_bill_payment_recorded', $payment, "Vendor payment {$payment->payment_number} recorded.", [
                'accounting_vendor_bill_id' => $bill->id,
                'journal_entry_id' => $entry->id,
                'amount' => $amount,
            ], $createdBy);

            return $payment->load(['vendorBill', 'journalEntry', 'bankAccount', 'creator']);
        });
    }

    public function voidCustomerPayment(AccountingPayment $payment, ?int $createdBy = null): JournalEntry
    {
        $payment->loadMissing(['accountingEvent', 'journalEntry']);

        if ($payment->status !== 'posted') {
            throw ValidationException::withMessages([
                'payment' => 'Only posted payments can be voided.',
            ]);
        }

        if ($payment->deposit_batch_id !== null || $payment->reconciliation_status === 'deposited') {
            throw ValidationException::withMessages([
                'payment' => 'Deposited payments cannot be voided until the deposit is corrected.',
            ]);
        }

        if ($payment->reconciliation_status === 'reconciled') {
            throw ValidationException::withMessages([
                'payment' => 'Reconciled payments cannot be voided directly. Reverse through a controlled accounting correction first.',
            ]);
        }

        if (JournalEntry::query()
            ->where('source_type', 'payment_void')
            ->where('source_id', $payment->id)
            ->exists()) {
            throw ValidationException::withMessages([
                'payment' => 'This payment already has a void journal entry.',
            ]);
        }

        $entryDate = now()->toDateString();
        $this->assertPeriodOpen($entryDate, 'voiding customer payments');
        $amount = round((float) $payment->amount, 2);

        return DB::transaction(function () use ($payment, $amount, $entryDate, $createdBy): JournalEntry {
            $entry = JournalEntry::query()->create([
                'accounting_event_id' => $payment->accounting_event_id,
                'journal_number' => $this->nextJournalNumber('PVOID'),
                'source_type' => 'payment_void',
                'source_id' => $payment->id,
                'status' => 'posted',
                'entry_date' => $entryDate,
                'currency' => $payment->currency ?: 'USD',
                'debit_total' => $amount,
                'credit_total' => $amount,
                'memo' => 'Void payment ' . $payment->payment_number,
                'created_by' => $createdBy,
                'posted_at' => now(),
            ]);

            $entry->lines()->create([
                'account_code' => $payment->receivable_account,
                'account_name' => $this->resolveAccountName($payment->receivable_account, 'Accounts Receivable'),
                'line_type' => 'debit',
                'debit' => $amount,
                'credit' => 0,
                'memo' => 'Restore receivable from voided payment.',
            ]);

            $entry->lines()->create([
                'account_code' => $payment->cash_account,
                'account_name' => $this->resolveAccountName($payment->cash_account, 'Cash On Hand'),
                'line_type' => 'credit',
                'debit' => 0,
                'credit' => $amount,
                'memo' => 'Reverse cash receipt from voided payment.',
            ]);

            $payment->forceFill(['status' => 'void'])->save();

            if ($payment->accountingEvent && $payment->accountingEvent->status === 'paid') {
                $payment->accountingEvent->forceFill(['status' => 'journal_posted'])->save();
            }

            if ($payment->accountingEvent) {
                $this->syncInvoiceStatusForEvent($payment->accountingEvent);
            }

            $this->recordAudit('customer_payment_voided', $payment, "Customer payment {$payment->payment_number} voided.", [
                'void_journal_entry_id' => $entry->id,
                'amount' => $amount,
            ], $createdBy);

            return $entry->load(['lines', 'creator']);
        });
    }

    public function postAccountingEvent(AccountingEvent $event, ?int $postingGroupId = null, ?int $createdBy = null): JournalEntry
    {
        if (JournalEntry::query()->where('accounting_event_id', $event->id)->exists()) {
            throw ValidationException::withMessages([
                'accounting_event' => 'This accounting event already has a journal entry.',
            ]);
        }

        $postingGroup = $this->resolvePostingGroup($postingGroupId);
        $entryDate = optional($event->event_date)->toDateString() ?: now()->toDateString();
        $this->assertPeriodOpen($entryDate, 'posting accounting events');
        $laborAmount = round((float) $event->labor_amount, 2);
        $partsAmount = round((float) $event->parts_amount, 2);
        $totalAmount = round((float) $event->total_amount, 2);
        $taxAmount = round((float) data_get($event->payload, 'tax_amount', 0), 2);
        $outputTaxAccount = trim((string) data_get($event->payload, 'tax_account', '2100 VAT Output Payable')) ?: '2100 VAT Output Payable';
        $invoiceLines = collect(data_get($event->payload, 'invoice_lines', []))
            ->map(fn ($line): array => [
                'description' => trim((string) data_get($line, 'description', 'Invoice revenue')),
                'account_code' => $this->normalizeAccountCode((string) data_get($line, 'account_code', $postingGroup?->labor_revenue_account ?: '4100 Service Labor Revenue')),
                'amount' => round((float) data_get($line, 'amount', 0), 2),
            ])
            ->filter(fn (array $line): bool => $line['amount'] > 0)
            ->values();

        if ($totalAmount <= 0) {
            throw ValidationException::withMessages([
                'accounting_event' => 'Only accounting events with a positive total can be posted to journal.',
            ]);
        }

        if ($taxAmount < 0 || $taxAmount > $totalAmount) {
            throw ValidationException::withMessages([
                'accounting_event' => 'Tax amount cannot be negative or greater than the accounting event total.',
            ]);
        }

        $this->ensureDefaultAccounts();
        $postingAccounts = collect([
            $postingGroup?->receivable_account ?: '1100 Accounts Receivable',
            $postingGroup?->labor_revenue_account ?: '4100 Service Labor Revenue',
            $postingGroup?->parts_revenue_account ?: '4200 Parts Revenue',
            $outputTaxAccount,
        ])->merge($invoiceLines->pluck('account_code'))->unique()->values()->all();
        $this->assertActiveAccountCodes($postingAccounts, 'posting_accounts');

        return DB::transaction(function () use ($event, $postingGroup, $entryDate, $laborAmount, $partsAmount, $totalAmount, $taxAmount, $outputTaxAccount, $invoiceLines, $createdBy): JournalEntry {
            $entry = JournalEntry::query()->create([
                'accounting_event_id' => $event->id,
                'posting_group_id' => $postingGroup?->id,
                'journal_number' => $this->nextJournalNumber(),
                'source_type' => $event->reference_type,
                'source_id' => $event->reference_id,
                'status' => 'posted',
                'entry_date' => $entryDate,
                'currency' => $event->currency ?: ($postingGroup?->currency ?: 'USD'),
                'debit_total' => $totalAmount,
                'credit_total' => $totalAmount,
                'memo' => $this->eventMemo($event),
                'created_by' => $createdBy,
                'posted_at' => now(),
            ]);

            $entry->lines()->create([
                'account_code' => $postingGroup?->receivable_account ?: '1100 Accounts Receivable',
                'account_name' => 'Accounts Receivable',
                'line_type' => 'debit',
                'debit' => $totalAmount,
                'credit' => 0,
                'memo' => 'Customer receivable from posted accounting event.',
            ]);

            if ($invoiceLines->isNotEmpty()) {
                foreach ($invoiceLines as $line) {
                    $entry->lines()->create([
                        'account_code' => $line['account_code'],
                        'account_name' => $this->resolveAccountName($line['account_code'], 'Invoice Revenue'),
                        'line_type' => 'credit',
                        'debit' => 0,
                        'credit' => $line['amount'],
                        'memo' => $line['description'],
                    ]);
                }
            } else {
                $taxReduction = $taxAmount;
                $netLaborAmount = max(0, round($laborAmount - min($laborAmount, $taxReduction), 2));
                $taxReduction = max(0, round($taxReduction - ($laborAmount - $netLaborAmount), 2));
                $netPartsAmount = max(0, round($partsAmount - min($partsAmount, $taxReduction), 2));

                if ($netLaborAmount > 0) {
                    $entry->lines()->create([
                        'account_code' => $postingGroup?->labor_revenue_account ?: '4100 Service Labor Revenue',
                        'account_name' => 'Service Labor Revenue',
                        'line_type' => 'credit',
                        'debit' => 0,
                        'credit' => $netLaborAmount,
                        'memo' => 'Labor revenue from workshop work order.',
                    ]);
                }

                if ($netPartsAmount > 0) {
                    $entry->lines()->create([
                        'account_code' => $postingGroup?->parts_revenue_account ?: '4200 Parts Revenue',
                        'account_name' => 'Parts Revenue',
                        'line_type' => 'credit',
                        'debit' => 0,
                        'credit' => $netPartsAmount,
                        'memo' => 'Parts revenue from workshop work order.',
                    ]);
                }
            }

            if ($taxAmount > 0) {
                $entry->lines()->create([
                    'account_code' => $outputTaxAccount,
                    'account_name' => $this->resolveAccountName($outputTaxAccount, 'VAT Output Payable'),
                    'line_type' => 'credit',
                    'debit' => 0,
                    'credit' => $taxAmount,
                    'memo' => 'Output tax from customer revenue.',
                ]);
            }

            $event->forceFill(['status' => 'journal_posted'])->save();
            $this->recordAudit('journal_posted', $entry, "Accounting event posted as {$entry->journal_number}.", [
                'accounting_event_id' => $event->id,
                'total_amount' => $totalAmount,
                'tax_amount' => $taxAmount,
            ], $createdBy);

            return $entry->load(['lines', 'postingGroup']);
        });
    }

    public function createManualJournalEntry(array $data, ?int $createdBy = null): JournalEntry
    {
        $lines = collect($data['lines'] ?? [])
            ->map(function (array $line): array {
                return [
                    'account_code' => trim((string) ($line['account_code'] ?? '')),
                    'account_name' => trim((string) ($line['account_name'] ?? '')),
                    'line_type' => (string) ($line['line_type'] ?? ''),
                    'debit' => round((float) ($line['debit'] ?? 0), 2),
                    'credit' => round((float) ($line['credit'] ?? 0), 2),
                    'memo' => $line['memo'] ?? null,
                ];
            })
            ->filter(fn (array $line): bool => $line['account_code'] !== '' && ($line['debit'] > 0 || $line['credit'] > 0))
            ->values();

        $this->assertPeriodOpen($data['entry_date'] ?? now()->toDateString(), 'creating manual journal entries');
        $this->assertKnownAccounts($lines);

        if ($lines->count() < 2) {
            throw ValidationException::withMessages([
                'lines' => 'A manual journal entry needs at least two non-empty lines.',
            ]);
        }

        $debitTotal = round((float) $lines->sum('debit'), 2);
        $creditTotal = round((float) $lines->sum('credit'), 2);

        if ($debitTotal <= 0 || $creditTotal <= 0 || $debitTotal !== $creditTotal) {
            throw ValidationException::withMessages([
                'lines' => 'Manual journal debit and credit totals must be equal and greater than zero.',
            ]);
        }

        $isHighRisk = $this->isHighRiskManualJournal($debitTotal, $data);
        $status = $isHighRisk ? 'pending_approval' : 'posted';
        $now = now();

        return DB::transaction(function () use ($data, $lines, $debitTotal, $creditTotal, $createdBy, $isHighRisk, $status, $now): JournalEntry {
            $entry = JournalEntry::query()->create([
                'journal_number' => $this->nextJournalNumber('MJE'),
                'source_type' => 'manual',
                'source_id' => null,
                'status' => $status,
                'approval_status' => $isHighRisk ? 'pending_approval' : null,
                'risk_level' => $isHighRisk ? 'high' : 'normal',
                'entry_date' => $data['entry_date'] ?? now()->toDateString(),
                'currency' => strtoupper((string) ($data['currency'] ?? 'USD')),
                'debit_total' => $debitTotal,
                'credit_total' => $creditTotal,
                'memo' => $data['memo'] ?? 'Manual journal entry',
                'created_by' => $createdBy,
                'approval_submitted_by' => $isHighRisk ? $createdBy : null,
                'approval_submitted_at' => $isHighRisk ? $now : null,
                'posted_at' => $isHighRisk ? null : $now,
            ]);

            foreach ($lines as $line) {
                $entry->lines()->create($line + [
                    'line_type' => $line['debit'] > 0 ? 'debit' : 'credit',
                ]);
            }

            $this->recordAudit(
                $isHighRisk ? 'manual_journal_submitted_for_approval' : 'manual_journal_created',
                $entry,
                $isHighRisk
                    ? "Manual journal {$entry->journal_number} submitted for approval."
                    : "Manual journal {$entry->journal_number} created.",
                [
                'debit_total' => $debitTotal,
                'credit_total' => $creditTotal,
                'risk_level' => $entry->risk_level,
            ], $createdBy);

            return $entry->load(['lines', 'postingGroup', 'creator']);
        });
    }

    public function approveManualJournalEntry(JournalEntry $entry, ?int $approvedBy = null, ?string $notes = null): JournalEntry
    {
        $entry->loadMissing('lines');

        if ($entry->source_type !== 'manual' || $entry->status !== 'pending_approval') {
            throw ValidationException::withMessages([
                'journal_entry' => 'Only pending manual journals can be approved.',
            ]);
        }

        if ($entry->created_by && $approvedBy && (int) $entry->created_by === (int) $approvedBy) {
            throw ValidationException::withMessages([
                'journal_entry' => 'Manual journals must be approved by a different accounting user.',
            ]);
        }

        return DB::transaction(function () use ($entry, $approvedBy, $notes): JournalEntry {
            $entry->forceFill([
                'status' => 'approved',
                'approval_status' => 'approved',
                'approved_by' => $approvedBy,
                'approved_at' => now(),
                'approval_notes' => $notes,
            ])->save();

            $this->recordAudit('manual_journal_approved', $entry, "Manual journal {$entry->journal_number} approved.", [
                'debit_total' => $entry->debit_total,
                'credit_total' => $entry->credit_total,
            ], $approvedBy);

            return $entry->refresh()->load(['lines', 'postingGroup', 'creator']);
        });
    }

    public function rejectManualJournalEntry(JournalEntry $entry, ?int $rejectedBy = null, ?string $notes = null): JournalEntry
    {
        if ($entry->source_type !== 'manual' || ! in_array($entry->status, ['pending_approval', 'approved'], true)) {
            throw ValidationException::withMessages([
                'journal_entry' => 'Only pending or approved manual journals can be rejected.',
            ]);
        }

        return DB::transaction(function () use ($entry, $rejectedBy, $notes): JournalEntry {
            $entry->forceFill([
                'status' => 'rejected',
                'approval_status' => 'rejected',
                'rejected_by' => $rejectedBy,
                'rejected_at' => now(),
                'approval_notes' => $notes,
            ])->save();

            $this->recordAudit('manual_journal_rejected', $entry, "Manual journal {$entry->journal_number} rejected.", [
                'debit_total' => $entry->debit_total,
                'credit_total' => $entry->credit_total,
            ], $rejectedBy);

            return $entry->refresh()->load(['lines', 'postingGroup', 'creator']);
        });
    }

    public function postApprovedManualJournalEntry(JournalEntry $entry, ?int $postedBy = null): JournalEntry
    {
        $entry->loadMissing('lines');

        if ($entry->source_type !== 'manual' || $entry->status !== 'approved') {
            throw ValidationException::withMessages([
                'journal_entry' => 'Only approved manual journals can be posted.',
            ]);
        }

        $this->assertPeriodOpen(optional($entry->entry_date)->toDateString() ?: now()->toDateString(), 'posting approved manual journal entries');

        return DB::transaction(function () use ($entry, $postedBy): JournalEntry {
            $entry->forceFill([
                'status' => 'posted',
                'approval_status' => 'posted',
                'posted_at' => now(),
            ])->save();

            $this->recordAudit('manual_journal_posted_after_approval', $entry, "Approved manual journal {$entry->journal_number} posted.", [
                'debit_total' => $entry->debit_total,
                'credit_total' => $entry->credit_total,
                'approved_by' => $entry->approved_by,
            ], $postedBy);

            return $entry->refresh()->load(['lines', 'postingGroup', 'creator']);
        });
    }

    public function reverseJournalEntry(JournalEntry $entry, ?int $createdBy = null): JournalEntry
    {
        $entry->loadMissing('lines');
        $this->assertPeriodOpen(now()->toDateString(), 'reversing journal entries');

        if ($entry->status !== 'posted') {
            throw ValidationException::withMessages([
                'journal_entry' => 'Only posted journal entries can be reversed.',
            ]);
        }

        if ($entry->source_type === 'journal_reversal') {
            throw ValidationException::withMessages([
                'journal_entry' => 'Reversal journal entries cannot be reversed again.',
            ]);
        }

        if (JournalEntry::query()
            ->where('source_type', 'journal_reversal')
            ->where('source_id', $entry->id)
            ->exists()) {
            throw ValidationException::withMessages([
                'journal_entry' => 'This journal entry already has a reversal entry.',
            ]);
        }

        return DB::transaction(function () use ($entry, $createdBy): JournalEntry {
            $reversal = JournalEntry::query()->create([
                'accounting_event_id' => $entry->accounting_event_id,
                'posting_group_id' => $entry->posting_group_id,
                'journal_number' => $this->nextJournalNumber('REV'),
                'source_type' => 'journal_reversal',
                'source_id' => $entry->id,
                'status' => 'posted',
                'entry_date' => now()->toDateString(),
                'currency' => $entry->currency,
                'debit_total' => $entry->credit_total,
                'credit_total' => $entry->debit_total,
                'memo' => 'Reversal of ' . $entry->journal_number,
                'created_by' => $createdBy,
                'posted_at' => now(),
            ]);

            foreach ($entry->lines as $line) {
                $reversal->lines()->create([
                    'account_code' => $line->account_code,
                    'account_name' => $line->account_name,
                    'line_type' => (float) $line->credit > 0 ? 'debit' : 'credit',
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                    'memo' => 'Reversal: ' . ($line->memo ?: $entry->journal_number),
                ]);
            }

            $entry->forceFill(['status' => 'reversed'])->save();
            $this->recordAudit('journal_reversed', $reversal, "Journal {$entry->journal_number} reversed by {$reversal->journal_number}.", [
                'original_journal_entry_id' => $entry->id,
            ], $createdBy);

            return $reversal->load(['lines', 'postingGroup', 'creator']);
        });
    }

    public function postInventoryMovement(StockMovement $movement, ?int $createdBy = null): JournalEntry
    {
        if (JournalEntry::query()
            ->where('source_type', StockMovement::class)
            ->where('source_id', $movement->id)
            ->exists()) {
            throw ValidationException::withMessages([
                'stock_movement' => 'This inventory movement already has a journal entry.',
            ]);
        }

        $movement->loadMissing(['product', 'branch']);
        $valuation = $this->inventoryMovementValuation($movement);
        $entryDate = optional($movement->movement_date)->toDateString() ?: now()->toDateString();
        $this->assertPeriodOpen($entryDate, 'posting inventory valuation movements');
        $policy = $this->ensureDefaultPolicy();
        $this->assertActiveAccountCodes([
            $policy->inventory_asset_account,
            $policy->inventory_adjustment_offset_account,
            $policy->inventory_adjustment_expense_account,
            $policy->cogs_account,
        ], 'inventory_policy_accounts');
        $handoff = $this->workspaceIntegrationHandoffService->start([
            'integration_key' => 'parts-accounting',
            'event_name' => 'stock_movement.valued',
            'source_product' => 'parts_inventory',
            'target_product' => 'accounting',
            'source_type' => StockMovement::class,
            'source_id' => $movement->id,
            'payload' => [
                'stock_movement_id' => $movement->id,
                'movement_type' => $movement->type,
                'quantity' => $movement->quantity,
                'unit_cost' => $valuation['unit_cost'],
                'valuation_amount' => $valuation['amount'],
                'valuation_method' => $valuation['method'],
                'valuation_source' => $valuation['source'],
            ],
        ], $createdBy);

        if (! $valuation['can_post']) {
            $this->workspaceIntegrationHandoffService->markSkipped(
                $handoff,
                $valuation['reason']
            );

            throw ValidationException::withMessages([
                'stock_movement' => $valuation['reason'],
            ]);
        }

        try {
            $amount = (float) $valuation['amount'];

            return DB::transaction(function () use ($movement, $amount, $entryDate, $policy, $createdBy, $handoff, $valuation): JournalEntry {
            $entry = JournalEntry::query()->create([
                'journal_number' => $this->nextJournalNumber('INV'),
                'source_type' => StockMovement::class,
                'source_id' => $movement->id,
                'status' => 'posted',
                'entry_date' => $entryDate,
                'currency' => $policy->currency ?: 'USD',
                'debit_total' => $amount,
                'credit_total' => $amount,
                'memo' => $this->inventoryMovementMemo($movement),
                'created_by' => $createdBy,
                'posted_at' => now(),
            ]);

            if (in_array($movement->type, ['opening', 'adjustment_in'], true)) {
                $entry->lines()->create([
                    'account_code' => $policy->inventory_asset_account,
                    'account_name' => $this->resolveAccountName($policy->inventory_asset_account, 'Inventory Asset'),
                    'line_type' => 'debit',
                    'debit' => $amount,
                    'credit' => 0,
                    'memo' => 'Inventory value increase.',
                ]);

                $entry->lines()->create([
                    'account_code' => $policy->inventory_adjustment_offset_account,
                    'account_name' => $this->resolveAccountName($policy->inventory_adjustment_offset_account, 'Inventory Adjustment Offset'),
                    'line_type' => 'credit',
                    'debit' => 0,
                    'credit' => $amount,
                    'memo' => 'Offset for inventory value increase.',
                ]);
            } else {
                $expenseAccount = $movement->reference_type === WorkOrder::class
                    ? [$policy->cogs_account, $this->resolveAccountName($policy->cogs_account, 'Cost Of Goods Sold')]
                    : [$policy->inventory_adjustment_expense_account, $this->resolveAccountName($policy->inventory_adjustment_expense_account, 'Inventory Adjustment Expense')];

                $entry->lines()->create([
                    'account_code' => $expenseAccount[0],
                    'account_name' => $expenseAccount[1],
                    'line_type' => 'debit',
                    'debit' => $amount,
                    'credit' => 0,
                    'memo' => 'Inventory value decrease.',
                ]);

                $entry->lines()->create([
                    'account_code' => $policy->inventory_asset_account,
                    'account_name' => $this->resolveAccountName($policy->inventory_asset_account, 'Inventory Asset'),
                    'line_type' => 'credit',
                    'debit' => 0,
                    'credit' => $amount,
                    'memo' => 'Inventory asset reduction.',
                ]);
            }

                $this->workspaceIntegrationHandoffService->markPosted($handoff, $entry, [
                    'journal_entry_id' => $entry->id,
                ]);
                $this->recordAudit('inventory_valuation_posted', $entry, "Inventory movement posted as {$entry->journal_number}.", [
                    'stock_movement_id' => $movement->id,
                    'policy_id' => $policy->id,
                    'valuation_amount' => $amount,
                    'valuation_method' => $valuation['method'],
                    'valuation_source' => $valuation['source'],
                ], $createdBy);

                return $entry->load(['lines', 'creator']);
            });
        } catch (\Throwable $exception) {
            $this->workspaceIntegrationHandoffService->markFailed($handoff, $exception->getMessage());

            throw $exception;
        }
    }

    public function trialBalance(array $filters = []): Collection
    {
        $mappingAvailable = $this->accountMappingColumnsAvailable();
        $query = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->leftJoin('accounting_accounts', 'accounting_accounts.code', '=', 'journal_entry_lines.account_code')
            ->whereIn('journal_entries.status', ['posted', 'reversed'])
            ->when(! empty($filters['date_from']), fn ($query) => $query->whereDate('journal_entries.entry_date', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($query) => $query->whereDate('journal_entries.entry_date', '<=', $filters['date_to']))
            ->groupBy('journal_entry_lines.account_code', 'journal_entry_lines.account_name');

        $select = [
                'journal_entry_lines.account_code',
                'journal_entry_lines.account_name',
                DB::raw('SUM(journal_entry_lines.debit) as debit_total'),
                DB::raw('SUM(journal_entry_lines.credit) as credit_total'),
                DB::raw('SUM(journal_entry_lines.debit - journal_entry_lines.credit) as balance'),
        ];

        if ($mappingAvailable) {
            $query->groupBy(
                'accounting_accounts.ifrs_category',
                'accounting_accounts.statement_report',
                'accounting_accounts.statement_section',
                'accounting_accounts.statement_subsection',
                'accounting_accounts.statement_order',
                'accounting_accounts.cash_flow_category'
            );
            $select = array_merge($select, [
                'accounting_accounts.ifrs_category',
                'accounting_accounts.statement_report',
                'accounting_accounts.statement_section',
                'accounting_accounts.statement_subsection',
                'accounting_accounts.statement_order',
                'accounting_accounts.cash_flow_category',
            ]);
        }

        return $query
            ->orderBy($mappingAvailable ? 'accounting_accounts.statement_order' : 'journal_entry_lines.account_code')
            ->orderBy('journal_entry_lines.account_code')
            ->select($select)
            ->get();
    }

    public function revenueSummary(array $filters = []): Collection
    {
        return DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->whereIn('journal_entries.status', ['posted', 'reversed'])
            ->where(function ($query) {
                $query->where('journal_entry_lines.account_code', 'like', '4%')
                    ->orWhere('journal_entry_lines.account_name', 'like', '%Revenue%');
            })
            ->when(! empty($filters['date_from']), fn ($query) => $query->whereDate('journal_entries.entry_date', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($query) => $query->whereDate('journal_entries.entry_date', '<=', $filters['date_to']))
            ->groupBy('journal_entry_lines.account_code', 'journal_entry_lines.account_name')
            ->orderBy('journal_entry_lines.account_code')
            ->select([
                'journal_entry_lines.account_code',
                'journal_entry_lines.account_name',
                DB::raw('SUM(journal_entry_lines.credit - journal_entry_lines.debit) as revenue_total'),
            ])
            ->get();
    }

    public function taxSummary(array $filters = []): array
    {
        $rates = $this->getTaxRates();
        $inputAccounts = $rates->pluck('input_tax_account')->filter()->unique()->values();
        $outputAccounts = $rates->pluck('output_tax_account')->filter()->unique()->values();

        $rows = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.status', 'posted')
            ->where(function ($query) use ($inputAccounts, $outputAccounts) {
                $query->whereIn('journal_entry_lines.account_code', $inputAccounts->all() ?: ['1410 VAT Input Receivable'])
                    ->orWhereIn('journal_entry_lines.account_code', $outputAccounts->all() ?: ['2100 VAT Output Payable']);
            })
            ->when(! empty($filters['date_from']), fn ($query) => $query->whereDate('journal_entries.entry_date', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($query) => $query->whereDate('journal_entries.entry_date', '<=', $filters['date_to']))
            ->groupBy('journal_entry_lines.account_code', 'journal_entry_lines.account_name')
            ->orderBy('journal_entry_lines.account_code')
            ->select([
                'journal_entry_lines.account_code',
                'journal_entry_lines.account_name',
                DB::raw('SUM(journal_entry_lines.debit) as debit_total'),
                DB::raw('SUM(journal_entry_lines.credit) as credit_total'),
            ])
            ->get()
            ->map(function ($row) use ($inputAccounts, $outputAccounts) {
                $row->tax_type = $inputAccounts->contains($row->account_code) ? 'input' : ($outputAccounts->contains($row->account_code) ? 'output' : 'tax');
                $row->amount = $row->tax_type === 'input'
                    ? round((float) $row->debit_total - (float) $row->credit_total, 2)
                    : round((float) $row->credit_total - (float) $row->debit_total, 2);

                return $row;
            });

        $inputTotal = round((float) $rows->where('tax_type', 'input')->sum('amount'), 2);
        $outputTotal = round((float) $rows->where('tax_type', 'output')->sum('amount'), 2);

        return [
            'filters' => $filters,
            'rows' => $rows,
            'input_total' => $inputTotal,
            'output_total' => $outputTotal,
            'net_payable' => round($outputTotal - $inputTotal, 2),
        ];
    }

    public function profitAndLoss(array $filters = []): array
    {
        $rows = $this->statementRows(['revenue', 'expense'], ['4', '5'], $filters)
            ->map(function ($row) {
                $type = $row->account_type ?: (Str::startsWith($row->account_code, '4') ? 'revenue' : 'expense');
                $amount = $type === 'revenue'
                    ? round((float) $row->credit_total - (float) $row->debit_total, 2)
                    : round((float) $row->debit_total - (float) $row->credit_total, 2);

                $row->statement_type = $type;
                $row->amount = $amount;

                return $row;
            });

        $revenues = $rows->where('statement_type', 'revenue')->values();
        $expenses = $rows->where('statement_type', 'expense')->values();
        $revenueTotal = round((float) $revenues->sum('amount'), 2);
        $expenseTotal = round((float) $expenses->sum('amount'), 2);

        return [
            'title' => 'Profit And Loss',
            'filters' => $filters,
            'sections' => [
                ['label' => 'Revenue', 'rows' => $revenues, 'total' => $revenueTotal],
                ['label' => 'Expenses', 'rows' => $expenses, 'total' => $expenseTotal],
            ],
            'summary' => [
                'Revenue Total' => $revenueTotal,
                'Expense Total' => $expenseTotal,
                'Net Income' => round($revenueTotal - $expenseTotal, 2),
            ],
        ];
    }

    public function balanceSheet(array $filters = []): array
    {
        $rows = $this->statementRows(['asset', 'liability', 'equity'], ['1', '2', '3'], $filters)
            ->map(function ($row) {
                $type = $row->account_type ?: match (true) {
                    Str::startsWith($row->account_code, '2') => 'liability',
                    Str::startsWith($row->account_code, '3') => 'equity',
                    default => 'asset',
                };
                $amount = $type === 'asset'
                    ? round((float) $row->debit_total - (float) $row->credit_total, 2)
                    : round((float) $row->credit_total - (float) $row->debit_total, 2);

                $row->statement_type = $type;
                $row->amount = $amount;

                return $row;
            });

        $assets = $rows->where('statement_type', 'asset')->values();
        $liabilities = $rows->where('statement_type', 'liability')->values();
        $equity = $rows->where('statement_type', 'equity')->values();
        $assetTotal = round((float) $assets->sum('amount'), 2);
        $liabilityTotal = round((float) $liabilities->sum('amount'), 2);
        $equityTotal = round((float) $equity->sum('amount'), 2);

        return [
            'title' => 'Balance Sheet',
            'filters' => $filters,
            'sections' => [
                ['label' => 'Assets', 'rows' => $assets, 'total' => $assetTotal],
                ['label' => 'Liabilities', 'rows' => $liabilities, 'total' => $liabilityTotal],
                ['label' => 'Equity', 'rows' => $equity, 'total' => $equityTotal],
            ],
            'summary' => [
                'Asset Total' => $assetTotal,
                'Liabilities And Equity' => round($liabilityTotal + $equityTotal, 2),
                'Difference' => round($assetTotal - $liabilityTotal - $equityTotal, 2),
            ],
        ];
    }

    protected function resolvePostingGroup(?int $postingGroupId = null): ?AccountingPostingGroup
    {
        if ($postingGroupId) {
            $postingGroup = AccountingPostingGroup::query()
                ->where('is_active', true)
                ->find($postingGroupId);

            if ($postingGroup) {
                return $postingGroup;
            }
        }

        $postingGroup = AccountingPostingGroup::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        if ($postingGroup) {
            return $postingGroup;
        }

        $group = AccountingPostingGroup::query()->create([
            'code' => 'workshop_revenue',
            'name' => 'Workshop Revenue',
            'receivable_account' => '1100 Accounts Receivable',
            'labor_revenue_account' => '4100 Service Labor Revenue',
            'parts_revenue_account' => '4200 Parts Revenue',
            'currency' => 'USD',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->ensureAccountsFromCodes([
            $group->receivable_account,
            $group->labor_revenue_account,
            $group->parts_revenue_account,
        ]);

        return $group;
    }

    protected function statementRows(array $accountTypes, array $codePrefixes, array $filters = []): Collection
    {
        $mappingAvailable = $this->accountMappingColumnsAvailable();
        $query = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->leftJoin('accounting_accounts', 'accounting_accounts.code', '=', 'journal_entry_lines.account_code')
            ->where('journal_entries.status', 'posted')
            ->where(function ($query) use ($accountTypes, $codePrefixes) {
                $query->whereIn('accounting_accounts.type', $accountTypes);

                foreach ($codePrefixes as $prefix) {
                    $query->orWhere('journal_entry_lines.account_code', 'like', $prefix . '%');
                }
            })
            ->when(! empty($filters['date_from']), fn ($query) => $query->whereDate('journal_entries.entry_date', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($query) => $query->whereDate('journal_entries.entry_date', '<=', $filters['date_to']))
            ->groupBy('journal_entry_lines.account_code', 'journal_entry_lines.account_name', 'accounting_accounts.type');

        $select = [
                'journal_entry_lines.account_code',
                'journal_entry_lines.account_name',
                'accounting_accounts.type as account_type',
                DB::raw('SUM(journal_entry_lines.debit) as debit_total'),
                DB::raw('SUM(journal_entry_lines.credit) as credit_total'),
        ];

        if ($mappingAvailable) {
            $query->groupBy(
                'accounting_accounts.ifrs_category',
                'accounting_accounts.statement_report',
                'accounting_accounts.statement_section',
                'accounting_accounts.statement_subsection',
                'accounting_accounts.statement_order',
                'accounting_accounts.cash_flow_category'
            );
            $select = array_merge($select, [
                'accounting_accounts.ifrs_category',
                'accounting_accounts.statement_report',
                'accounting_accounts.statement_section',
                'accounting_accounts.statement_subsection',
                'accounting_accounts.statement_order',
                'accounting_accounts.cash_flow_category',
            ]);
        }

        return $query
            ->orderBy($mappingAvailable ? 'accounting_accounts.statement_order' : 'journal_entry_lines.account_code')
            ->orderBy('journal_entry_lines.account_code')
            ->select($select)
            ->get()
            ->filter(fn ($row): bool => round(abs((float) $row->debit_total - (float) $row->credit_total), 2) > 0)
            ->values();
    }

    protected function ensureDefaultAccounts(): void
    {
        $defaults = [
            ['1100 Accounts Receivable', 'Accounts Receivable', 'asset', 'debit', 'current_assets', 'balance_sheet', 'Assets', 'Trade and other receivables', 110],
            ['1000 Cash On Hand', 'Cash On Hand', 'asset', 'debit', 'current_assets', 'balance_sheet', 'Assets', 'Cash and cash equivalents', 100],
            ['1010 Bank Account', 'Bank Account', 'asset', 'debit', 'current_assets', 'balance_sheet', 'Assets', 'Cash and cash equivalents', 101],
            ['1300 Inventory Asset', 'Inventory Asset', 'asset', 'debit', 'current_assets', 'balance_sheet', 'Assets', 'Inventories', 130],
            ['1410 VAT Input Receivable', 'VAT Input Receivable', 'asset', 'debit', 'current_assets', 'balance_sheet', 'Assets', 'Tax recoverable', 140],
            ['2000 Accounts Payable', 'Accounts Payable', 'liability', 'credit', 'current_liabilities', 'balance_sheet', 'Liabilities', 'Trade and other payables', 200],
            ['2100 VAT Output Payable', 'VAT Output Payable', 'liability', 'credit', 'current_liabilities', 'balance_sheet', 'Liabilities', 'Tax payable', 210],
            ['3900 Inventory Adjustment Offset', 'Inventory Adjustment Offset', 'equity', 'credit', 'equity', 'balance_sheet', 'Equity', 'Retained earnings and reserves', 390],
            ['4100 Service Labor Revenue', 'Service Labor Revenue', 'revenue', 'credit', 'revenue', 'profit_and_loss', 'Revenue', 'Service revenue', 410],
            ['4100 Service Revenue', 'Service Revenue', 'revenue', 'credit', 'revenue', 'profit_and_loss', 'Revenue', 'Service revenue', 410],
            ['4200 Parts Revenue', 'Parts Revenue', 'revenue', 'credit', 'revenue', 'profit_and_loss', 'Revenue', 'Parts revenue', 420],
            ['5000 Cost Of Goods Sold', 'Cost Of Goods Sold', 'expense', 'debit', 'cost_of_sales', 'profit_and_loss', 'Expenses', 'Cost of sales', 500],
            ['5100 Inventory Adjustment Expense', 'Inventory Adjustment Expense', 'expense', 'debit', 'operating_expenses', 'profit_and_loss', 'Expenses', 'Inventory adjustments', 510],
            ['5200 Operating Expense', 'Operating Expense', 'expense', 'debit', 'operating_expenses', 'profit_and_loss', 'Expenses', 'Operating expenses', 520],
        ];

        foreach ($defaults as [$code, $name, $type, $normalBalance, $ifrsCategory, $statementReport, $statementSection, $statementSubsection, $statementOrder]) {
            $account = AccountingAccount::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'type' => $type,
                    'normal_balance' => $normalBalance,
                    'is_active' => true,
                ]
            );

            if ($this->accountMappingColumnsAvailable()) {
                $account->forceFill([
                    'ifrs_category' => $account->ifrs_category ?: $ifrsCategory,
                    'statement_report' => $account->statement_report ?: $statementReport,
                    'statement_section' => $account->statement_section ?: $statementSection,
                    'statement_subsection' => $account->statement_subsection ?: $statementSubsection,
                    'statement_order' => $account->statement_order ?: $statementOrder,
                    'cash_flow_category' => $account->cash_flow_category ?: $this->cashFlowCategoryForType($type),
                ])->save();
            }
        }
    }

    protected function accountMappingColumnsAvailable(): bool
    {
        return Schema::hasColumn('accounting_accounts', 'statement_section')
            && Schema::hasColumn('accounting_accounts', 'statement_order');
    }

    protected function accountMappingPayload(array $data, string $code, string $type): array
    {
        $defaults = $this->defaultAccountMapping($code, $type);

        return [
            'ifrs_category' => $data['ifrs_category'] ?? $defaults['ifrs_category'],
            'statement_report' => $data['statement_report'] ?? $defaults['statement_report'],
            'statement_section' => $data['statement_section'] ?? $defaults['statement_section'],
            'statement_subsection' => $data['statement_subsection'] ?? $defaults['statement_subsection'],
            'statement_order' => (int) ($data['statement_order'] ?? $defaults['statement_order']),
            'cash_flow_category' => $data['cash_flow_category'] ?? $defaults['cash_flow_category'],
        ];
    }

    protected function defaultAccountMapping(string $code, string $type): array
    {
        return [
            'ifrs_category' => match ($type) {
                'asset' => 'current_assets',
                'liability' => 'current_liabilities',
                'equity' => 'equity',
                'revenue' => 'revenue',
                default => Str::startsWith($code, '5') ? 'operating_expenses' : 'expenses',
            },
            'statement_report' => in_array($type, ['revenue', 'expense'], true) ? 'profit_and_loss' : 'balance_sheet',
            'statement_section' => match ($type) {
                'asset' => 'Assets',
                'liability' => 'Liabilities',
                'equity' => 'Equity',
                'revenue' => 'Revenue',
                default => 'Expenses',
            },
            'statement_subsection' => match ($type) {
                'asset' => 'Current assets',
                'liability' => 'Current liabilities',
                'equity' => 'Equity',
                'revenue' => 'Revenue',
                default => 'Operating expenses',
            },
            'statement_order' => match ($type) {
                'asset' => 100,
                'liability' => 200,
                'equity' => 300,
                'revenue' => 400,
                default => 500,
            },
            'cash_flow_category' => $this->cashFlowCategoryForType($type),
        ];
    }

    protected function cashFlowCategoryForType(string $type): string
    {
        return match ($type) {
            'asset', 'liability', 'revenue', 'expense' => 'operating',
            'equity' => 'financing',
            default => 'not_applicable',
        };
    }

    protected function ensureDefaultTaxRate(): AccountingTaxRate
    {
        $this->ensureAccountsFromCodes([
            '1410 VAT Input Receivable',
            '2100 VAT Output Payable',
        ]);

        $rate = AccountingTaxRate::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        if ($rate) {
            return $rate;
        }

        return AccountingTaxRate::query()->create([
            'code' => 'vat_5',
            'name' => 'VAT 5%',
            'rate' => 5,
            'input_tax_account' => '1410 VAT Input Receivable',
            'output_tax_account' => '2100 VAT Output Payable',
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    protected function ensureAccountsFromCodes(array $codes): void
    {
        foreach ($codes as $code) {
            $code = trim((string) $code);

            if ($code === '') {
                continue;
            }

            AccountingAccount::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $this->nameFromAccountCode($code),
                    'type' => match (true) {
                        Str::startsWith($code, '2') => 'liability',
                        Str::startsWith($code, '3') => 'equity',
                        Str::startsWith($code, '4') => 'revenue',
                        Str::startsWith($code, '5') => 'expense',
                        default => 'asset',
                    },
                    'normal_balance' => Str::startsWith($code, ['2', '3', '4']) ? 'credit' : 'debit',
                    'is_active' => true,
                ]
            );
        }
    }

    protected function ensureDefaultPolicy(): AccountingPolicy
    {
        $this->ensureDefaultAccounts();

        $policy = AccountingPolicy::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        if ($policy) {
            return $policy;
        }

        return AccountingPolicy::query()->create([
            'code' => 'default_inventory_policy',
            'name' => 'Default Inventory Policy',
            'currency' => 'USD',
            'inventory_asset_account' => '1300 Inventory Asset',
            'inventory_adjustment_offset_account' => '3900 Inventory Adjustment Offset',
            'inventory_adjustment_expense_account' => '5100 Inventory Adjustment Expense',
            'cogs_account' => '5000 Cost Of Goods Sold',
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    protected function ensureDefaultBankAccounts(): void
    {
        $this->ensureDefaultAccounts();

        if (! AccountingBankAccount::query()->where('account_code', '1000 Cash On Hand')->exists()) {
            AccountingBankAccount::query()->create([
                'name' => 'Cash On Hand',
                'type' => 'cash',
                'account_code' => '1000 Cash On Hand',
                'currency' => 'USD',
                'is_default_receipt' => true,
                'is_default_payment' => false,
                'is_active' => true,
            ]);
        }

        if (! AccountingBankAccount::query()->where('account_code', '1010 Bank Account')->exists()) {
            AccountingBankAccount::query()->create([
                'name' => 'Bank Account',
                'type' => 'bank',
                'account_code' => '1010 Bank Account',
                'currency' => 'USD',
                'is_default_receipt' => false,
                'is_default_payment' => true,
                'is_active' => true,
            ]);
        }
    }

    protected function resolveBankAccount(null|int|string $bankAccountId = null, string $purpose = 'receipt'): AccountingBankAccount
    {
        $this->ensureDefaultBankAccounts();

        $query = AccountingBankAccount::query()->where('is_active', true);

        if ($bankAccountId) {
            $account = (clone $query)->find((int) $bankAccountId);

            if (! $account) {
                throw ValidationException::withMessages([
                    'accounting_bank_account_id' => 'Select an active configured bank or cash account.',
                ]);
            }

            return $account;
        }

        $account = (clone $query)
            ->when($purpose === 'payment', fn ($query) => $query->orderByDesc('is_default_payment'))
            ->when($purpose !== 'payment', fn ($query) => $query->orderByDesc('is_default_receipt'))
            ->orderBy('id')
            ->first();

        if (! $account) {
            throw ValidationException::withMessages([
                'accounting_bank_account_id' => 'Configure an active bank or cash account before recording cash activity.',
            ]);
        }

        return $account;
    }

    protected function bankAccountBalances(): array
    {
        return DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('accounting_bank_accounts', 'accounting_bank_accounts.account_code', '=', 'journal_entry_lines.account_code')
            ->where('journal_entries.status', 'posted')
            ->groupBy('journal_entry_lines.account_code')
            ->select([
                'journal_entry_lines.account_code',
                DB::raw('SUM(journal_entry_lines.debit - journal_entry_lines.credit) as balance'),
            ])
            ->pluck('balance', 'account_code')
            ->map(fn ($value): float => round((float) $value, 2))
            ->all();
    }

    protected function normalizePeriodRange(array $data): array
    {
        $start = Carbon::parse($data['period_start'])->toDateString();
        $end = Carbon::parse($data['period_end'])->toDateString();

        if ($end < $start) {
            throw ValidationException::withMessages([
                'period_end' => 'Period end date must be on or after period start date.',
            ]);
        }

        return [$start, $end];
    }

    protected function receivablesInPeriod(string $start, string $end): array
    {
        $events = AccountingEvent::query()
            ->whereIn('status', ['journal_posted', 'paid'])
            ->whereDate('event_date', '>=', $start)
            ->whereDate('event_date', '<=', $end)
            ->get();

        $count = 0;
        $amount = 0.0;

        foreach ($events as $event) {
            $openAmount = $this->remainingAmountForEvent($event);

            if ($openAmount > 0) {
                $count++;
                $amount += $openAmount;
            }
        }

        return [
            'count' => $count,
            'amount' => round($amount, 2),
        ];
    }

    protected function assertPeriodOpen(string $entryDate, string $operation = 'posting'): void
    {
        $date = Carbon::parse($entryDate)->toDateString();

        $lock = AccountingPeriodLock::query()
            ->whereIn('status', ['locked', 'archived'])
            ->whereDate('period_start', '<=', $date)
            ->whereDate('period_end', '>=', $date)
            ->first();

        if ($lock) {
            throw ValidationException::withMessages([
                'entry_date' => "Accounting period is locked for {$date}; {$operation} is not allowed. Use a reversal or correction entry in an open period.",
            ]);
        }
    }

    protected function assertKnownAccounts(Collection $lines): void
    {
        $this->ensureDefaultAccounts();
        $this->assertActiveAccountCodes($lines->pluck('account_code')->all(), 'lines');
    }

    protected function assertActiveAccountCodes(array $codes, string $field = 'accounts'): void
    {
        $normalizedCodes = collect($codes)
            ->map(fn ($code): string => $this->normalizeAccountCode((string) $code))
            ->filter()
            ->unique()
            ->values();

        if ($normalizedCodes->isEmpty()) {
            return;
        }

        $activeCodes = AccountingAccount::query()
            ->where('is_active', true)
            ->whereIn('code', $normalizedCodes->all())
            ->pluck('code')
            ->all();

        $unknownCodes = $normalizedCodes
            ->reject(fn (string $code): bool => in_array($code, $activeCodes, true))
            ->values();

        if ($unknownCodes->isNotEmpty()) {
            throw ValidationException::withMessages([
                $field => 'Unknown or inactive account code: ' . $unknownCodes->implode(', '),
            ]);
        }
    }

    protected function assertValidNormalBalance(string $type, string $normalBalance): void
    {
        $expected = in_array($type, ['asset', 'expense'], true) ? 'debit' : 'credit';

        if ($normalBalance !== $expected) {
            throw ValidationException::withMessages([
                'normal_balance' => ucfirst($type) . " accounts must have a {$expected} normal balance.",
            ]);
        }
    }

    protected function accountCodeIsUsed(string $code): bool
    {
        return DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entry_lines.account_code', $code)
            ->whereIn('journal_entries.status', ['posted', 'reversed'])
            ->exists();
    }

    protected function normalizeAccountCode(string $code): string
    {
        return trim(preg_replace('/\s+/', ' ', $code) ?? '');
    }

    protected function resolveAccountName(string $code, string $fallback): string
    {
        return AccountingAccount::query()->where('code', $code)->value('name') ?: $fallback;
    }

    protected function nameFromAccountCode(string $code): string
    {
        $parts = explode(' ', $code, 2);

        return $parts[1] ?? $code;
    }

    protected function paidAmountForEvent(int $eventId): float
    {
        return round((float) AccountingPayment::query()
            ->where('accounting_event_id', $eventId)
            ->where('status', 'posted')
            ->sum('amount'), 2);
    }

    protected function remainingAmountForEvent(AccountingEvent $event): float
    {
        return max(0, round((float) $event->total_amount - $this->paidAmountForEvent($event->id), 2));
    }

    protected function syncInvoiceStatusForEvent(AccountingEvent $event): void
    {
        if ($event->reference_type !== AccountingInvoice::class || ! $event->reference_id) {
            return;
        }

        $invoice = AccountingInvoice::query()->find((int) $event->reference_id);

        if (! $invoice || ! in_array($invoice->status, ['posted', 'paid'], true)) {
            return;
        }

        $remainingAmount = $this->remainingAmountForEvent($event);

        $invoice->forceFill([
            'status' => $remainingAmount <= 0 ? 'paid' : 'posted',
        ])->save();
    }

    protected function paidAmountForVendorBill(int $billId): float
    {
        return round((float) AccountingVendorBillPayment::query()
            ->where('accounting_vendor_bill_id', $billId)
            ->where('status', 'posted')
            ->sum('amount'), 2);
    }

    protected function adjustedAmountForVendorBill(int $billId): float
    {
        return round((float) AccountingVendorBillAdjustment::query()
            ->where('accounting_vendor_bill_id', $billId)
            ->where('status', 'posted')
            ->sum('amount'), 2);
    }

    protected function remainingAmountForVendorBill(AccountingVendorBill $bill): float
    {
        return max(0, round((float) $bill->amount - $this->adjustedAmountForVendorBill($bill->id) - $this->paidAmountForVendorBill($bill->id), 2));
    }

    protected function recordAudit(string $eventType, Model $auditable, string $description, array $payload = [], ?int $createdBy = null): void
    {
        $recordedAt = now();
        $sourceType = $auditable::class;
        $sourceId = $auditable->getKey();

        AccountingAuditEntry::query()->create([
            'event_type' => $eventType,
            'auditable_type' => $sourceType,
            'auditable_id' => $sourceId,
            'description' => $description,
            'payload' => array_merge($payload, [
                'event_type' => $eventType,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'actor_id' => $createdBy,
                'recorded_at' => $recordedAt->toIso8601String(),
                'source_of_truth' => 'journal_entries_and_journal_entry_lines',
            ]),
            'created_by' => $createdBy,
            'created_at' => $recordedAt,
        ]);
    }

    protected function isHighRiskManualJournal(float $debitTotal, array $data): bool
    {
        $threshold = (float) config('accounting.manual_journal_approval_threshold', 5000);

        return ! empty($data['requires_approval']) || ($threshold > 0 && $debitTotal >= $threshold);
    }

    protected function nextJournalNumber(string $prefix = 'JE'): string
    {
        return $prefix . '-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(4));
    }

    protected function eventMemo(AccountingEvent $event): string
    {
        $workOrderNumber = (string) data_get($event->payload, 'work_order_number', '');
        $title = (string) data_get($event->payload, 'title', $event->event_type);

        return trim($workOrderNumber . ' ' . $title) ?: 'Accounting event journal entry';
    }

    protected function inventoryMovementValue(StockMovement $movement): float
    {
        return (float) $this->inventoryMovementValuation($movement)['amount'];
    }

    protected function inventoryMovementValuation(StockMovement $movement): array
    {
        $movement->loadMissing('product');

        $quantity = (float) $movement->quantity;
        $unitCost = (float) ($movement->product?->cost_price ?? 0);
        $amount = round(abs($quantity) * $unitCost, 2);
        $movementType = (string) $movement->type;
        $reason = null;

        if (! in_array($movementType, self::INVENTORY_POSTABLE_MOVEMENT_TYPES, true)) {
            $reason = 'Only opening, adjustment in, and adjustment out stock movements can be posted to accounting. Transfer movements are operational stock logistics only.';
        } elseif ($quantity <= 0) {
            $reason = 'Only stock movements with a positive quantity can be posted to accounting.';
        } elseif ($unitCost <= 0) {
            $reason = 'Only stock movements with a positive current product cost can be posted to accounting.';
        } elseif ($amount <= 0) {
            $reason = 'Only inventory movements with positive valuation can be posted.';
        }

        return [
            'method' => self::INVENTORY_VALUATION_METHOD,
            'method_label' => self::INVENTORY_VALUATION_METHOD_LABEL,
            'source' => self::INVENTORY_VALUATION_SOURCE,
            'source_label' => 'Current stock item cost price',
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'amount' => $amount,
            'can_post' => $reason === null,
            'reason' => $reason,
        ];
    }

    protected function inventoryMovementMemo(StockMovement $movement): string
    {
        $product = $movement->product?->name ?: 'Stock item';
        $branch = $movement->branch?->name ?: 'branch';

        return "Inventory valuation for {$movement->type}: {$product} at {$branch}";
    }
}
