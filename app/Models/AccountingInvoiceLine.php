<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingInvoiceLine extends Model
{
    protected $fillable = [
        'accounting_invoice_id',
        'description',
        'account_code',
        'quantity',
        'unit_price',
        'line_total',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(AccountingInvoice::class, 'accounting_invoice_id');
    }
}
