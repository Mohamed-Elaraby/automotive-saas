<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingPostingGroup extends Model
{
    protected $fillable = [
        'code',
        'name',
        'receivable_account',
        'labor_revenue_account',
        'parts_revenue_account',
        'currency',
        'is_default',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];
}
