<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingVendorBill extends Model
{
    protected $fillable = [
        'supplier_id',
        'journal_entry_id',
        'accounting_tax_rate_id',
        'bill_number',
        'bill_date',
        'due_date',
        'supplier_name',
        'reference',
        'currency',
        'amount',
        'tax_amount',
        'expense_account',
        'payable_account',
        'tax_account',
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
        'tax_amount' => 'decimal:2',
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

    public function taxRate()
    {
        return $this->belongsTo(AccountingTaxRate::class, 'accounting_tax_rate_id');
    }

    public function payments()
    {
        return $this->hasMany(AccountingVendorBillPayment::class);
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
