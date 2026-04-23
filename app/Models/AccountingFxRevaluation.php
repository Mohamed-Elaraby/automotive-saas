<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingFxRevaluation extends Model
{
    protected $fillable = [
        'journal_entry_id',
        'account_code',
        'base_currency',
        'foreign_currency',
        'rate_date',
        'exchange_rate',
        'foreign_amount',
        'carrying_base_amount',
        'revalued_base_amount',
        'gain_loss_amount',
        'gain_loss_direction',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'rate_date' => 'date',
        'exchange_rate' => 'decimal:8',
        'foreign_amount' => 'decimal:2',
        'carrying_base_amount' => 'decimal:2',
        'revalued_base_amount' => 'decimal:2',
        'gain_loss_amount' => 'decimal:2',
    ];

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
