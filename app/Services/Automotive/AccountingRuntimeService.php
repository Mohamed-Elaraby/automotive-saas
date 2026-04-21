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

    public function getAccounts(): Collection
    {
        $this->ensureDefaultAccounts();

        return AccountingAccount::query()
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

    public function getPolicies(): Collection
    {
        $this->ensureDefaultPolicy();

        return AccountingPolicy::query()
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
        return AccountingAccount::query()->updateOrCreate(
            ['code' => trim((string) $data['code'])],
            [
                'name' => $data['name'],
                'type' => $data['type'],
                'normal_balance' => $data['normal_balance'],
                'is_active' => ! array_key_exists('is_active', $data) || (bool) $data['is_active'],
                'notes' => $data['notes'] ?? null,
            ]
        );
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

            $this->ensureAccountsFromCodes([
                $policy->inventory_asset_account,
                $policy->inventory_adjustment_offset_account,
                $policy->inventory_adjustment_expense_account,
                $policy->cogs_account,
            ]);

            return $policy;
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
        $this->assertPeriodOpen($paymentDate);

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
        $this->ensureAccountsFromCodes([$cashAccount, $receivableAccount]);

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
        $this->assertPeriodOpen($depositDate);

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
            $this->ensureAccountsFromCodes([$depositAccount]);
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
        $this->assertPeriodOpen($correctionDate);

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
        $this->assertPeriodOpen($entryDate);
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
        $this->assertPeriodOpen($entryDate);
        $laborAmount = round((float) $event->labor_amount, 2);
        $partsAmount = round((float) $event->parts_amount, 2);
        $totalAmount = round((float) $event->total_amount, 2);

        if ($totalAmount <= 0) {
            throw ValidationException::withMessages([
                'accounting_event' => 'Only accounting events with a positive total can be posted to journal.',
            ]);
        }

        return DB::transaction(function () use ($event, $postingGroup, $entryDate, $laborAmount, $partsAmount, $totalAmount, $createdBy): JournalEntry {
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

            if ($laborAmount > 0) {
                $entry->lines()->create([
                    'account_code' => $postingGroup?->labor_revenue_account ?: '4100 Service Labor Revenue',
                    'account_name' => 'Service Labor Revenue',
                    'line_type' => 'credit',
                    'debit' => 0,
                    'credit' => $laborAmount,
                    'memo' => 'Labor revenue from workshop work order.',
                ]);
            }

            if ($partsAmount > 0) {
                $entry->lines()->create([
                    'account_code' => $postingGroup?->parts_revenue_account ?: '4200 Parts Revenue',
                    'account_name' => 'Parts Revenue',
                    'line_type' => 'credit',
                    'debit' => 0,
                    'credit' => $partsAmount,
                    'memo' => 'Parts revenue from workshop work order.',
                ]);
            }

            $event->forceFill(['status' => 'journal_posted'])->save();
            $this->recordAudit('journal_posted', $entry, "Accounting event posted as {$entry->journal_number}.", [
                'accounting_event_id' => $event->id,
                'total_amount' => $totalAmount,
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

        $this->assertPeriodOpen($data['entry_date'] ?? now()->toDateString());
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

        return DB::transaction(function () use ($data, $lines, $debitTotal, $creditTotal, $createdBy): JournalEntry {
            $entry = JournalEntry::query()->create([
                'journal_number' => $this->nextJournalNumber('MJE'),
                'source_type' => 'manual',
                'source_id' => null,
                'status' => 'posted',
                'entry_date' => $data['entry_date'] ?? now()->toDateString(),
                'currency' => strtoupper((string) ($data['currency'] ?? 'USD')),
                'debit_total' => $debitTotal,
                'credit_total' => $creditTotal,
                'memo' => $data['memo'] ?? 'Manual journal entry',
                'created_by' => $createdBy,
                'posted_at' => now(),
            ]);

            foreach ($lines as $line) {
                $entry->lines()->create($line + [
                    'line_type' => $line['debit'] > 0 ? 'debit' : 'credit',
                ]);
            }

            $this->recordAudit('manual_journal_created', $entry, "Manual journal {$entry->journal_number} created.", [
                'debit_total' => $debitTotal,
                'credit_total' => $creditTotal,
            ], $createdBy);

            return $entry->load(['lines', 'postingGroup', 'creator']);
        });
    }

    public function reverseJournalEntry(JournalEntry $entry, ?int $createdBy = null): JournalEntry
    {
        $entry->loadMissing('lines');
        $this->assertPeriodOpen(now()->toDateString());

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
        $this->assertPeriodOpen($entryDate);
        $policy = $this->ensureDefaultPolicy();
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

    protected function ensureDefaultAccounts(): void
    {
        $defaults = [
            ['1100 Accounts Receivable', 'Accounts Receivable', 'asset', 'debit'],
            ['1000 Cash On Hand', 'Cash On Hand', 'asset', 'debit'],
            ['1010 Bank Account', 'Bank Account', 'asset', 'debit'],
            ['1300 Inventory Asset', 'Inventory Asset', 'asset', 'debit'],
            ['3900 Inventory Adjustment Offset', 'Inventory Adjustment Offset', 'equity', 'credit'],
            ['4100 Service Labor Revenue', 'Service Labor Revenue', 'revenue', 'credit'],
            ['4100 Service Revenue', 'Service Revenue', 'revenue', 'credit'],
            ['4200 Parts Revenue', 'Parts Revenue', 'revenue', 'credit'],
            ['5000 Cost Of Goods Sold', 'Cost Of Goods Sold', 'expense', 'debit'],
            ['5100 Inventory Adjustment Expense', 'Inventory Adjustment Expense', 'expense', 'debit'],
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
                    'type' => Str::startsWith($code, '4') ? 'revenue' : (Str::startsWith($code, '5') ? 'expense' : 'asset'),
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

    protected function assertPeriodOpen(string $entryDate): void
    {
        $date = Carbon::parse($entryDate)->toDateString();

        $lock = AccountingPeriodLock::query()
            ->where('status', 'locked')
            ->whereDate('period_start', '<=', $date)
            ->whereDate('period_end', '>=', $date)
            ->first();

        if ($lock) {
            throw ValidationException::withMessages([
                'entry_date' => "Accounting period is locked for {$date}.",
            ]);
        }
    }

    protected function assertKnownAccounts(Collection $lines): void
    {
        $this->ensureDefaultAccounts();
        $activeCodes = AccountingAccount::query()
            ->where('is_active', true)
            ->pluck('code')
            ->all();

        if ($activeCodes === []) {
            return;
        }

        $unknownCodes = $lines
            ->pluck('account_code')
            ->unique()
            ->reject(fn (string $code): bool => in_array($code, $activeCodes, true))
            ->values();

        if ($unknownCodes->isNotEmpty()) {
            throw ValidationException::withMessages([
                'lines' => 'Unknown or inactive account code: ' . $unknownCodes->implode(', '),
            ]);
        }
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
