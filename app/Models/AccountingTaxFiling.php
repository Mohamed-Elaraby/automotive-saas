<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingTaxFiling extends Model
{
    protected $fillable = [
        'filing_number',
        'period_start',
        'period_end',
        'status',
        'return_type',
        'input_tax_total',
        'output_tax_total',
        'net_tax_due',
        'notes',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'input_tax_total' => 'decimal:2',
        'output_tax_total' => 'decimal:2',
        'net_tax_due' => 'decimal:2',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
