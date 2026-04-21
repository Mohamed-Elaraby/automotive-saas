<?php

namespace App\Services\Automotive;

use App\Models\AccountingAccount;
use App\Models\AccountingAuditEntry;
use App\Models\AccountingDepositBatch;
use App\Models\AccountingEvent;
use App\Models\AccountingPayment;
use App\Models\AccountingPeriodLock;
use App\Models\AccountingPolicy;
use App\Models\AccountingPostingGroup;
use App\Models\AccountingTaxRate;
use App\Models\AccountingVendorBill;
use App\Models\AccountingVendorBillPayment;
use App\Models\JournalEntry;
use App\Models\StockMovement;
use App\Models\WorkOrder;
use App\Services\Tenancy\WorkspaceIntegrationHandoffService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountingRuntimeService
{
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
            ->where('status', 'locked')
            ->whereDate('period_start', '<=', $date)
            ->whereDate('period_end', '>=', $date)
            ->latest('period_end')
            ->latest('id')
            ->first();

        $latestLock = AccountingPeriodLock::query()
            ->where('status', 'locked')
            ->latest('period_end')
            ->latest('id')
            ->first();

        return [
            'as_of_date' => $date,
            'current_status' => $currentLock ? 'locked' : 'open',
            'current_lock' => $currentLock,
            'latest_lock' => $latestLock,
            'locked_periods_count' => AccountingPeriodLock::query()
                ->where('status', 'locked')
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

    public function getAuditEntries(int $limit = 30): Collection
    {
        return AccountingAuditEntry::query()
            ->latest('created_at')
            ->latest('id')
            ->limit($limit)
            ->get();
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

    public function getRecentPayments(int $limit = 15): Collection
    {
        return AccountingPayment::query()
            ->with(['accountingEvent', 'journalEntry', 'depositBatch', 'creator'])
            ->latest('payment_date')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function getPayments(array $filters = [], int $limit = 50): Collection
    {
        return AccountingPayment::query()
            ->with(['accountingEvent', 'journalEntry', 'depositBatch', 'creator'])
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
            ->with(['payments', 'creator', 'corrector'])
            ->latest('deposit_date')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function getVendorBills(array $filters = [], int $limit = 25): Collection
    {
        return AccountingVendorBill::query()
            ->with(['supplier', 'journalEntry', 'payments', 'creator', 'poster'])
            ->when(! empty($filters['vendor_bill_status']), fn ($query) => $query->where('status', $filters['vendor_bill_status']))
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
                $bill->setAttribute('paid_amount', $paidAmount);
                $bill->setAttribute('open_amount', max(0, round((float) $bill->amount - $paidAmount, 2)));

                return $bill;
            });
    }

    public function payablesSummary(): array
    {
        $bills = $this->getVendorBills([], 500);
        $draft = $bills->where('status', 'draft');
        $open = $bills->whereIn('status', ['posted', 'partial']);
        $paid = $bills->where('status', 'paid');

        return [
            'draft_count' => $draft->count(),
            'draft_amount' => round((float) $draft->sum('amount'), 2),
            'open_count' => $open->filter(fn (AccountingVendorBill $bill): bool => (float) $bill->getAttribute('open_amount') > 0)->count(),
            'open_amount' => round((float) $open->sum('open_amount'), 2),
            'paid_count' => $paid->count(),
            'paid_amount' => round((float) $paid->sum('amount'), 2),
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
            ->with(['vendorBill', 'journalEntry', 'creator'])
            ->when(! empty($filters['status']), fn ($query) => $query->where('status', $filters['status']))
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
            'creator',
            'corrector',
        ]);
    }

    public function bankReconciliationReport(array $filters = []): array
    {
        $batches = AccountingDepositBatch::query()
            ->with(['payments', 'creator', 'corrector'])
            ->when(! empty($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(! empty($filters['deposit_account']), fn ($query) => $query->where('deposit_account', $filters['deposit_account']))
            ->when(! empty($filters['date_from']), fn ($query) => $query->whereDate('deposit_date', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($query) => $query->whereDate('deposit_date', '<=', $filters['date_to']))
            ->orderBy('deposit_date')
            ->orderBy('id')
            ->get();

        return [
            'filters' => $filters,
            'batches' => $batches,
            'posted_count' => $batches->where('status', 'posted')->count(),
            'corrected_count' => $batches->where('status', 'corrected')->count(),
            'posted_total' => round((float) $batches->where('status', 'posted')->sum('total_amount'), 2),
            'corrected_total' => round((float) $batches->where('status', 'corrected')->sum('total_amount'), 2),
        ];
    }

    public function paymentReconciliationSummary(): array
    {
        $payments = AccountingPayment::query()
            ->selectRaw('COALESCE(reconciliation_status, ?) as reconciliation_status, COUNT(*) as payments_count, COALESCE(SUM(amount), 0) as total_amount', ['pending'])
            ->where('status', 'posted')
            ->groupBy('reconciliation_status')
            ->get()
            ->keyBy('reconciliation_status');

        $pending = $payments->get('pending');
        $deposited = $payments->get('deposited');

        return [
            'pending_count' => (int) ($pending?->payments_count ?? 0),
            'pending_amount' => round((float) ($pending?->total_amount ?? 0), 2),
            'deposited_count' => (int) ($deposited?->payments_count ?? 0),
            'deposited_amount' => round((float) ($deposited?->total_amount ?? 0), 2),
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
        $payments = AccountingPayment::query()
            ->with('journalEntry')
            ->where('accounting_event_id', $event->id)
            ->latest('payment_date')
            ->latest('id')
            ->get();
        $paidAmount = $this->paidAmountForEvent($event->id);

        return [
            'invoice_number' => 'INV-' . str_pad((string) $event->id, 6, '0', STR_PAD_LEFT),
            'event' => $event,
            'journal_entry' => JournalEntry::query()
                ->where('accounting_event_id', $event->id)
                ->where('source_type', $event->reference_type)
                ->where('source_id', $event->reference_id)
                ->first(),
            'lines' => collect([
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
                'reference' => 'INV-' . str_pad((string) $event->id, 6, '0', STR_PAD_LEFT),
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
            ->filter(fn (StockMovement $movement): bool => $this->inventoryMovementValue($movement) > 0)
            ->values();
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

    public function createAccount(array $data): AccountingAccount
    {
        $code = $this->normalizeAccountCode((string) $data['code']);
        $type = (string) $data['type'];
        $normalBalance = (string) $data['normal_balance'];

        $this->assertValidNormalBalance($type, $normalBalance);

        return DB::transaction(function () use ($data, $code, $type, $normalBalance): AccountingAccount {
            $account = AccountingAccount::query()->where('code', $code)->first();
            $payload = [
                'name' => $data['name'],
                'type' => $type,
                'normal_balance' => $normalBalance,
                'is_active' => ! array_key_exists('is_active', $data) || (bool) $data['is_active'],
                'notes' => $data['notes'] ?? null,
            ];

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
                ])->save();

                return $account->refresh();
            }

            return AccountingAccount::query()->updateOrCreate(
                ['code' => $code],
                $payload
            );
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
        $start = Carbon::parse($data['period_start'])->toDateString();
        $end = Carbon::parse($data['period_end'])->toDateString();

        if ($end < $start) {
            throw ValidationException::withMessages([
                'period_end' => 'Period end date must be on or after period start date.',
            ]);
        }

        $overlapExists = AccountingPeriodLock::query()
            ->where('status', 'locked')
            ->whereDate('period_start', '<=', $end)
            ->whereDate('period_end', '>=', $start)
            ->exists();

        if ($overlapExists) {
            throw ValidationException::withMessages([
                'period_start' => 'This accounting period overlaps an existing locked period.',
            ]);
        }

        $lock = AccountingPeriodLock::query()->create([
            'period_start' => $start,
            'period_end' => $end,
            'status' => 'locked',
            'locked_by' => $createdBy,
            'locked_at' => now(),
            'notes' => $data['notes'] ?? null,
        ]);

        $this->recordAudit('period_locked', $lock, "Accounting period {$start} to {$end} locked.", [
            'period_start' => $start,
            'period_end' => $end,
        ], $createdBy);

        return $lock;
    }

    public function createPolicy(array $data): AccountingPolicy
    {
        return DB::transaction(function () use ($data): AccountingPolicy {
            $isDefault = ! empty($data['is_default']);
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
                ['code' => Str::slug((string) $data['code'], '_')],
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

            return $policy;
        });
    }

    public function createTaxRate(array $data): AccountingTaxRate
    {
        return DB::transaction(function () use ($data): AccountingTaxRate {
            $isDefault = ! empty($data['is_default']);
            $this->assertActiveAccountCodes([
                $data['input_tax_account'] ?? '1410 VAT Input Receivable',
                $data['output_tax_account'] ?? '2100 VAT Output Payable',
            ], 'tax_accounts');

            if ($isDefault) {
                AccountingTaxRate::query()->update(['is_default' => false]);
            }

            $rate = AccountingTaxRate::query()->updateOrCreate(
                ['code' => Str::slug((string) $data['code'], '_')],
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
        $cashAccount = trim((string) ($data['cash_account'] ?? '1000 Cash On Hand')) ?: '1000 Cash On Hand';
        $receivableAccount = $postingGroup?->receivable_account ?: '1100 Accounts Receivable';
        $this->ensureDefaultAccounts();
        $this->assertActiveAccountCodes([$cashAccount, $receivableAccount], 'cash_account');

        return DB::transaction(function () use ($data, $event, $paymentDate, $amount, $remainingAmount, $cashAccount, $receivableAccount, $createdBy): AccountingPayment {
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

            $this->recordAudit('customer_payment_recorded', $payment, "Customer payment {$payment->payment_number} recorded.", [
                'accounting_event_id' => $event->id,
                'journal_entry_id' => $entry->id,
                'amount' => $amount,
            ], $createdBy);

        return $payment->load(['accountingEvent', 'journalEntry', 'creator']);
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
            $depositAccount = trim((string) ($data['deposit_account'] ?? '1010 Bank Account')) ?: '1010 Bank Account';
            $this->ensureDefaultAccounts();
            $this->assertActiveAccountCodes([$depositAccount], 'deposit_account');
            $totalAmount = round((float) $payments->sum('amount'), 2);

            $batch = AccountingDepositBatch::query()->create([
                'deposit_number' => $this->nextJournalNumber('DEP'),
                'deposit_date' => $depositDate,
                'deposit_account' => $depositAccount,
                'currency' => $currency,
                'total_amount' => $totalAmount,
                'payments_count' => $payments->count(),
                'status' => 'posted',
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

            return $batch->load(['payments', 'creator']);
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

        $cashAccount = trim((string) ($data['cash_account'] ?? '1010 Bank Account')) ?: '1010 Bank Account';
        $payableAccount = $bill->payable_account ?: '2000 Accounts Payable';
        $this->ensureDefaultAccounts();
        $this->assertActiveAccountCodes([$cashAccount, $payableAccount], 'cash_account');

        return DB::transaction(function () use ($data, $bill, $paymentDate, $amount, $remainingAmount, $cashAccount, $payableAccount, $createdBy): AccountingVendorBillPayment {
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
                'payment_number' => $this->nextJournalNumber('VPMT'),
                'payment_date' => $paymentDate,
                'method' => $data['method'] ?? 'bank_transfer',
                'reference' => $data['reference'] ?? null,
                'currency' => strtoupper((string) ($data['currency'] ?? $bill->currency ?: 'USD')),
                'amount' => $amount,
                'cash_account' => $cashAccount,
                'payable_account' => $payableAccount,
                'status' => 'posted',
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

            return $payment->load(['vendorBill', 'journalEntry', 'creator']);
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
        $this->assertActiveAccountCodes([
            $postingGroup?->receivable_account ?: '1100 Accounts Receivable',
            $postingGroup?->labor_revenue_account ?: '4100 Service Labor Revenue',
            $postingGroup?->parts_revenue_account ?: '4200 Parts Revenue',
            $outputTaxAccount,
        ], 'posting_accounts');

        return DB::transaction(function () use ($event, $postingGroup, $entryDate, $laborAmount, $partsAmount, $totalAmount, $taxAmount, $outputTaxAccount, $createdBy): JournalEntry {
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
        $amount = $this->inventoryMovementValue($movement);
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
                'unit_cost' => $movement->product?->cost_price,
                'valuation_amount' => $amount,
            ],
        ], $createdBy);

        if ($amount <= 0) {
            $this->workspaceIntegrationHandoffService->markSkipped(
                $handoff,
                'Inventory movement valuation is zero.'
            );

            throw ValidationException::withMessages([
                'stock_movement' => 'Only inventory movements with positive valuation can be posted.',
            ]);
        }

        try {
            return DB::transaction(function () use ($movement, $amount, $entryDate, $policy, $createdBy, $handoff): JournalEntry {
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
        return DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->whereIn('journal_entries.status', ['posted', 'reversed'])
            ->when(! empty($filters['date_from']), fn ($query) => $query->whereDate('journal_entries.entry_date', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($query) => $query->whereDate('journal_entries.entry_date', '<=', $filters['date_to']))
            ->groupBy('journal_entry_lines.account_code', 'journal_entry_lines.account_name')
            ->orderBy('journal_entry_lines.account_code')
            ->select([
                'journal_entry_lines.account_code',
                'journal_entry_lines.account_name',
                DB::raw('SUM(journal_entry_lines.debit) as debit_total'),
                DB::raw('SUM(journal_entry_lines.credit) as credit_total'),
                DB::raw('SUM(journal_entry_lines.debit - journal_entry_lines.credit) as balance'),
            ])
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
        return DB::table('journal_entry_lines')
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
            ->groupBy('journal_entry_lines.account_code', 'journal_entry_lines.account_name', 'accounting_accounts.type')
            ->orderBy('journal_entry_lines.account_code')
            ->select([
                'journal_entry_lines.account_code',
                'journal_entry_lines.account_name',
                'accounting_accounts.type as account_type',
                DB::raw('SUM(journal_entry_lines.debit) as debit_total'),
                DB::raw('SUM(journal_entry_lines.credit) as credit_total'),
            ])
            ->get()
            ->filter(fn ($row): bool => round(abs((float) $row->debit_total - (float) $row->credit_total), 2) > 0)
            ->values();
    }

    protected function ensureDefaultAccounts(): void
    {
        $defaults = [
            ['1100 Accounts Receivable', 'Accounts Receivable', 'asset', 'debit'],
            ['1000 Cash On Hand', 'Cash On Hand', 'asset', 'debit'],
            ['1010 Bank Account', 'Bank Account', 'asset', 'debit'],
            ['1300 Inventory Asset', 'Inventory Asset', 'asset', 'debit'],
            ['1410 VAT Input Receivable', 'VAT Input Receivable', 'asset', 'debit'],
            ['2000 Accounts Payable', 'Accounts Payable', 'liability', 'credit'],
            ['2100 VAT Output Payable', 'VAT Output Payable', 'liability', 'credit'],
            ['3900 Inventory Adjustment Offset', 'Inventory Adjustment Offset', 'equity', 'credit'],
            ['4100 Service Labor Revenue', 'Service Labor Revenue', 'revenue', 'credit'],
            ['4100 Service Revenue', 'Service Revenue', 'revenue', 'credit'],
            ['4200 Parts Revenue', 'Parts Revenue', 'revenue', 'credit'],
            ['5000 Cost Of Goods Sold', 'Cost Of Goods Sold', 'expense', 'debit'],
            ['5100 Inventory Adjustment Expense', 'Inventory Adjustment Expense', 'expense', 'debit'],
            ['5200 Operating Expense', 'Operating Expense', 'expense', 'debit'],
        ];

        foreach ($defaults as [$code, $name, $type, $normalBalance]) {
            AccountingAccount::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'type' => $type,
                    'normal_balance' => $normalBalance,
                    'is_active' => true,
                ]
            );
        }
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

    protected function assertPeriodOpen(string $entryDate, string $operation = 'posting'): void
    {
        $date = Carbon::parse($entryDate)->toDateString();

        $lock = AccountingPeriodLock::query()
            ->where('status', 'locked')
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

    protected function paidAmountForVendorBill(int $billId): float
    {
        return round((float) AccountingVendorBillPayment::query()
            ->where('accounting_vendor_bill_id', $billId)
            ->where('status', 'posted')
            ->sum('amount'), 2);
    }

    protected function remainingAmountForVendorBill(AccountingVendorBill $bill): float
    {
        return max(0, round((float) $bill->amount - $this->paidAmountForVendorBill($bill->id), 2));
    }

    protected function recordAudit(string $eventType, Model $auditable, string $description, array $payload = [], ?int $createdBy = null): void
    {
        AccountingAuditEntry::query()->create([
            'event_type' => $eventType,
            'auditable_type' => $auditable::class,
            'auditable_id' => $auditable->id,
            'description' => $description,
            'payload' => $payload,
            'created_by' => $createdBy,
            'created_at' => now(),
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
        $movement->loadMissing('product');

        return round((float) $movement->quantity * (float) ($movement->product?->cost_price ?? 0), 2);
    }

    protected function inventoryMovementMemo(StockMovement $movement): string
    {
        $product = $movement->product?->name ?: 'Stock item';
        $branch = $movement->branch?->name ?: 'branch';

        return "Inventory valuation for {$movement->type}: {$product} at {$branch}";
    }
}
