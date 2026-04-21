<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingTaxRate extends Model
{
    protected $fillable = [
        'code',
        'name',
        'rate',
        'input_tax_account',
        'output_tax_account',
        'is_default',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];
}
