<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingPayment extends Model
{
    protected $fillable = [
        'accounting_event_id',
        'journal_entry_id',
        'deposit_batch_id',
        'payment_number',
        'payment_date',
        'payer_name',
        'method',
        'reference',
        'currency',
        'amount',
        'cash_account',
        'receivable_account',
        'status',
        'reconciliation_status',
        'notes',
        'created_by',
        'reconciled_by',
        'posted_at',
        'reconciled_at',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'posted_at' => 'datetime',
        'reconciled_at' => 'datetime',
    ];

    public function accountingEvent()
    {
        return $this->belongsTo(AccountingEvent::class);
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function depositBatch()
    {
        return $this->belongsTo(AccountingDepositBatch::class, 'deposit_batch_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reconciler()
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }
}
