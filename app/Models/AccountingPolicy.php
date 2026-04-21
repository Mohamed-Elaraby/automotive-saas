<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingPolicy extends Model
{
    protected $fillable = [
        'code',
        'name',
        'currency',
        'inventory_asset_account',
        'inventory_adjustment_offset_account',
        'inventory_adjustment_expense_account',
        'cogs_account',
        'is_default',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];
}
