<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingPeriodCloseAdjustment extends Model
{
    protected $fillable = [
        'accounting_period_lock_id',
        'journal_entry_id',
        'adjustment_type',
        'target_period_start',
        'target_period_end',
        'rationale',
        'review_status',
        'review_notes',
        'reviewed_by',
        'reviewed_at',
        'created_by',
    ];

    protected $casts = [
        'target_period_start' => 'date',
        'target_period_end' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function periodLock(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriodLock::class, 'accounting_period_lock_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
