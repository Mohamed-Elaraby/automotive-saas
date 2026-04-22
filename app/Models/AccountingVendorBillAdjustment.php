<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingVendorBillAdjustment extends Model
{
    protected $fillable = [
        'accounting_vendor_bill_id',
        'journal_entry_id',
        'adjustment_number',
        'type',
        'adjustment_date',
        'amount',
        'tax_amount',
        'expense_account',
        'payable_account',
        'tax_account',
        'status',
        'reference',
        'reason',
        'created_by',
        'posted_at',
    ];

    protected $casts = [
        'adjustment_date' => 'date',
        'amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'posted_at' => 'datetime',
    ];

    public function vendorBill()
    {
        return $this->belongsTo(AccountingVendorBill::class, 'accounting_vendor_bill_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
