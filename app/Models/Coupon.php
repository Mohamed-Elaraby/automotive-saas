<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    public const TYPE_PERCENTAGE = 'percentage';
    public const TYPE_FIXED = 'fixed';

    protected $fillable = [
        'code',
        'name',
        'discount_type',
        'discount_value',
        'currency_code',
        'is_active',
        'applies_to_all_plans',
        'first_billing_cycle_only',
        'max_redemptions',
        'max_redemptions_per_tenant',
        'times_redeemed',
        'starts_at',
        'ends_at',
        'notes',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'is_active' => 'boolean',
        'applies_to_all_plans' => 'boolean',
        'first_billing_cycle_only' => 'boolean',
        'max_redemptions' => 'integer',
        'max_redemptions_per_tenant' => 'integer',
        'times_redeemed' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function getConnectionName(): ?string
    {
        return (string) (config('tenancy.database.central_connection') ?? config('database.default'));
    }

    public function plans()
    {
        return $this->belongsToMany(Plan::class, 'coupon_plan', 'coupon_id', 'plan_id')
            ->withTimestamps();
    }

    public function redemptions()
    {
        return $this->hasMany(CouponRedemption::class, 'coupon_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
