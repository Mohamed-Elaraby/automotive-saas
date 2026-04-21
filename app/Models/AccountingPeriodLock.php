<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingPeriodLock extends Model
{
    protected $fillable = [
        'period_start',
        'period_end',
        'status',
        'locked_by',
        'locked_at',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'locked_at' => 'datetime',
    ];
}
