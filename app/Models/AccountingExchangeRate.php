<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingExchangeRate extends Model
{
    protected $fillable = [
        'base_currency',
        'foreign_currency',
        'rate_date',
        'rate_to_base',
        'source',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'rate_date' => 'date',
        'rate_to_base' => 'decimal:8',
    ];
}
