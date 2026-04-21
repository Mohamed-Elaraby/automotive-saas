<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingVendorBillPayment extends Model
{
    protected $fillable = [
        'accounting_vendor_bill_id',
        'journal_entry_id',
        'accounting_bank_account_id',
        'payment_number',
        'payment_date',
        'method',
        'reference',
        'currency',
        'amount',
        'cash_account',
        'payable_account',
        'status',
        'reconciliation_status',
        'notes',
        'created_by',
        'reconciled_by',
        'posted_at',
        'reconciled_at',
        'bank_reconciliation_date',
        'bank_reference',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'posted_at' => 'datetime',
        'reconciled_at' => 'datetime',
        'bank_reconciliation_date' => 'date',
    ];

    public function vendorBill()
    {
        return $this->belongsTo(AccountingVendorBill::class, 'accounting_vendor_bill_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(AccountingBankAccount::class, 'accounting_bank_account_id');
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
