<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingDepositBatch extends Model
{
    protected $fillable = [
        'deposit_number',
        'accounting_bank_account_id',
        'deposit_date',
        'deposit_account',
        'currency',
        'total_amount',
        'payments_count',
        'status',
        'reconciliation_status',
        'reference',
        'notes',
        'correction_reason',
        'created_by',
        'reconciled_by',
        'corrected_by',
        'posted_at',
        'reconciled_at',
        'bank_reconciliation_date',
        'bank_reference',
        'corrected_at',
    ];

    protected $casts = [
        'deposit_date' => 'date',
        'total_amount' => 'decimal:2',
        'posted_at' => 'datetime',
        'reconciled_at' => 'datetime',
        'bank_reconciliation_date' => 'date',
        'corrected_at' => 'datetime',
    ];

    public function payments()
    {
        return $this->hasMany(AccountingPayment::class, 'deposit_batch_id');
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

    public function corrector()
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
