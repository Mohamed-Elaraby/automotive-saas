<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingBankAccount extends Model
{
    protected $fillable = [
        'name',
        'type',
        'account_code',
        'currency',
        'reference',
        'is_default_receipt',
        'is_default_payment',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_default_receipt' => 'boolean',
        'is_default_payment' => 'boolean',
        'is_active' => 'boolean',
    ];
}
