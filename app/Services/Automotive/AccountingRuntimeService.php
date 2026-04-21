<?php

namespace App\Services\Automotive;

use App\Models\AccountingEvent;
use App\Models\AccountingPostingGroup;
use App\Models\JournalEntry;
use App\Models\StockMovement;
use App\Models\WorkOrder;
use App\Services\Tenancy\WorkspaceIntegrationHandoffService;
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

    public function postAccountingEvent(AccountingEvent $event, ?int $postingGroupId = null, ?int $createdBy = null): JournalEntry
    {
        if (JournalEntry::query()->where('accounting_event_id', $event->id)->exists()) {
            throw ValidationException::withMessages([
                'accounting_event' => 'This accounting event already has a journal entry.',
            ]);
        }

        $postingGroup = $this->resolvePostingGroup($postingGroupId);
        $laborAmount = round((float) $event->labor_amount, 2);
        $partsAmount = round((float) $event->parts_amount, 2);
        $totalAmount = round((float) $event->total_amount, 2);

        if ($totalAmount <= 0) {
            throw ValidationException::withMessages([
                'accounting_event' => 'Only accounting events with a positive total can be posted to journal.',
            ]);
        }

        return DB::transaction(function () use ($event, $postingGroup, $laborAmount, $partsAmount, $totalAmount, $createdBy): JournalEntry {
            $entry = JournalEntry::query()->create([
                'accounting_event_id' => $event->id,
                'posting_group_id' => $postingGroup?->id,
                'journal_number' => $this->nextJournalNumber(),
                'source_type' => $event->reference_type,
                'source_id' => $event->reference_id,
                'status' => 'posted',
                'entry_date' => optional($event->event_date)->toDateString() ?: now()->toDateString(),
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

            return $entry->load(['lines', 'postingGroup', 'creator']);
        });
    }

    public function reverseJournalEntry(JournalEntry $entry, ?int $createdBy = null): JournalEntry
    {
        $entry->loadMissing('lines');

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
            return DB::transaction(function () use ($movement, $amount, $createdBy, $handoff): JournalEntry {
            $entry = JournalEntry::query()->create([
                'journal_number' => $this->nextJournalNumber('INV'),
                'source_type' => StockMovement::class,
                'source_id' => $movement->id,
                'status' => 'posted',
                'entry_date' => optional($movement->movement_date)->toDateString() ?: now()->toDateString(),
                'currency' => 'USD',
                'debit_total' => $amount,
                'credit_total' => $amount,
                'memo' => $this->inventoryMovementMemo($movement),
                'created_by' => $createdBy,
                'posted_at' => now(),
            ]);

            if (in_array($movement->type, ['opening', 'adjustment_in'], true)) {
                $entry->lines()->create([
                    'account_code' => '1300 Inventory Asset',
                    'account_name' => 'Inventory Asset',
                    'line_type' => 'debit',
                    'debit' => $amount,
                    'credit' => 0,
                    'memo' => 'Inventory value increase.',
                ]);

                $entry->lines()->create([
                    'account_code' => '3900 Inventory Adjustment Offset',
                    'account_name' => 'Inventory Adjustment Offset',
                    'line_type' => 'credit',
                    'debit' => 0,
                    'credit' => $amount,
                    'memo' => 'Offset for inventory value increase.',
                ]);
            } else {
                $expenseAccount = $movement->reference_type === WorkOrder::class
                    ? ['5000 Cost Of Goods Sold', 'Cost Of Goods Sold']
                    : ['5100 Inventory Adjustment Expense', 'Inventory Adjustment Expense'];

                $entry->lines()->create([
                    'account_code' => $expenseAccount[0],
                    'account_name' => $expenseAccount[1],
                    'line_type' => 'debit',
                    'debit' => $amount,
                    'credit' => 0,
                    'memo' => 'Inventory value decrease.',
                ]);

                $entry->lines()->create([
                    'account_code' => '1300 Inventory Asset',
                    'account_name' => 'Inventory Asset',
                    'line_type' => 'credit',
                    'debit' => 0,
                    'credit' => $amount,
                    'memo' => 'Inventory asset reduction.',
                ]);
            }

                $this->workspaceIntegrationHandoffService->markPosted($handoff, $entry, [
                    'journal_entry_id' => $entry->id,
                ]);

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

        return AccountingPostingGroup::query()->create([
            'code' => 'workshop_revenue',
            'name' => 'Workshop Revenue',
            'receivable_account' => '1100 Accounts Receivable',
            'labor_revenue_account' => '4100 Service Labor Revenue',
            'parts_revenue_account' => '4200 Parts Revenue',
            'currency' => 'USD',
            'is_default' => true,
            'is_active' => true,
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
