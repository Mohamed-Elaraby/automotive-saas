<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingAccount extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'normal_balance',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
