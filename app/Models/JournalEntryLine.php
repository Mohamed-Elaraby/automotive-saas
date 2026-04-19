<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalEntryLine extends Model
{
    protected $fillable = [
        'journal_entry_id',
        'account_code',
        'account_name',
        'line_type',
        'debit',
        'credit',
        'memo',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
