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
        'ifrs_category',
        'statement_report',
        'statement_section',
        'statement_subsection',
        'statement_order',
        'cash_flow_category',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'statement_order' => 'integer',
    ];
}
