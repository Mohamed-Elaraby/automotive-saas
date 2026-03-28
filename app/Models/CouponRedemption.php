<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CouponRedemption extends Model
{
    use HasFactory;

    protected $fillable = [
        'coupon_id',
        'tenant_id',
        'subscription_id',
        'plan_id',
        'status',
        'discount_amount',
        'currency_code',
        'context_payload',
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
        'context_payload' => 'array',
    ];

    public function getConnectionName(): ?string
    {
        return (string) (config('tenancy.database.central_connection') ?? config('database.default'));
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class, 'coupon_id');
    }
}
