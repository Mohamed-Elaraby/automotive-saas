<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingInvoice extends Model
{
    protected $fillable = [
        'accounting_event_id',
        'journal_entry_id',
        'invoice_number',
        'customer_name',
        'issue_date',
        'due_date',
        'currency',
        'subtotal',
        'tax_amount',
        'total_amount',
        'receivable_account',
        'revenue_account',
        'tax_account',
        'status',
        'reference',
        'notes',
        'created_by',
        'posted_by',
        'posted_at',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'posted_at' => 'datetime',
    ];

    public function lines()
    {
        return $this->hasMany(AccountingInvoiceLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function accountingEvent()
    {
        return $this->belongsTo(AccountingEvent::class);
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poster()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
