<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingDepositBatch extends Model
{
    protected $fillable = [
        'deposit_number',
        'deposit_date',
        'deposit_account',
        'currency',
        'total_amount',
        'payments_count',
        'status',
        'reference',
        'notes',
        'correction_reason',
        'created_by',
        'corrected_by',
        'posted_at',
        'corrected_at',
    ];

    protected $casts = [
        'deposit_date' => 'date',
        'total_amount' => 'decimal:2',
        'posted_at' => 'datetime',
        'corrected_at' => 'datetime',
    ];

    public function payments()
    {
        return $this->hasMany(AccountingPayment::class, 'deposit_batch_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function corrector()
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
