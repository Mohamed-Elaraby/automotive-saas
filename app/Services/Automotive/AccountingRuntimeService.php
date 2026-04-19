<?php

namespace App\Services\Automotive;

use App\Models\AccountingEvent;
use App\Models\AccountingPostingGroup;
use App\Models\JournalEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountingRuntimeService
{
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

    protected function nextJournalNumber(): string
    {
        return 'JE-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(4));
    }

    protected function eventMemo(AccountingEvent $event): string
    {
        $workOrderNumber = (string) data_get($event->payload, 'work_order_number', '');
        $title = (string) data_get($event->payload, 'title', $event->event_type);

        return trim($workOrderNumber . ' ' . $title) ?: 'Accounting event journal entry';
    }
}
