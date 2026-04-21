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
        'close_checklist',
        'lock_override',
        'lock_override_reason',
        'closing_started_by',
        'closing_started_at',
        'archived_by',
        'archived_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'locked_at' => 'datetime',
        'close_checklist' => 'array',
        'lock_override' => 'boolean',
        'closing_started_at' => 'datetime',
        'archived_at' => 'datetime',
    ];
}
