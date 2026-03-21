<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillingInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'tenant_id',
        'gateway',
        'gateway_invoice_id',
        'gateway_customer_id',
        'gateway_subscription_id',
        'invoice_number',
        'status',
        'billing_reason',
        'currency',
        'total_minor',
        'total_decimal',
        'amount_paid_minor',
        'amount_paid_decimal',
        'amount_due_minor',
        'amount_due_decimal',
        'hosted_invoice_url',
        'invoice_pdf',
        'issued_at',
        'paid_at',
        'raw_payload',
    ];

    protected $casts = [
        'total_decimal' => 'decimal:2',
        'amount_paid_decimal' => 'decimal:2',
        'amount_due_decimal' => 'decimal:2',
        'issued_at' => 'datetime',
        'paid_at' => 'datetime',
        'raw_payload' => 'array',
    ];

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}
