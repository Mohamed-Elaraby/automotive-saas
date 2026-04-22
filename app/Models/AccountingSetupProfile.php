<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingSetupProfile extends Model
{
    protected $fillable = [
        'base_currency',
        'fiscal_year_start_month',
        'fiscal_year_start_day',
        'tax_mode',
        'chart_template',
        'default_receivable_account',
        'default_payable_account',
        'default_cash_account',
        'default_bank_account',
        'default_revenue_account',
        'default_expense_account',
        'default_input_tax_account',
        'default_output_tax_account',
        'payload',
        'created_by',
        'updated_by',
        'setup_completed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'setup_completed_at' => 'datetime',
    ];
}
