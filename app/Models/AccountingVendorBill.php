<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingVendorBill extends Model
{
    protected $fillable = [
        'supplier_id',
        'journal_entry_id',
        'bill_number',
        'bill_date',
        'due_date',
        'supplier_name',
        'reference',
        'currency',
        'amount',
        'expense_account',
        'payable_account',
        'status',
        'notes',
        'created_by',
        'posted_by',
        'posted_at',
    ];

    protected $casts = [
        'bill_date' => 'date',
        'due_date' => 'date',
        'amount' => 'decimal:2',
        'posted_at' => 'datetime',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
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
