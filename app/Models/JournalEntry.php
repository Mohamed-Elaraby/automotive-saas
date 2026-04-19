<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    protected $fillable = [
        'accounting_event_id',
        'posting_group_id',
        'journal_number',
        'source_type',
        'source_id',
        'status',
        'entry_date',
        'currency',
        'debit_total',
        'credit_total',
        'memo',
        'created_by',
        'posted_at',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'debit_total' => 'decimal:2',
        'credit_total' => 'decimal:2',
        'posted_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function accountingEvent()
    {
        return $this->belongsTo(AccountingEvent::class);
    }

    public function postingGroup()
    {
        return $this->belongsTo(AccountingPostingGroup::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
