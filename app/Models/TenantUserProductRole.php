<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TenantUserProductRole extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'product_key',
        'product_role_id',
        'is_active',
        'assigned_at',
        'revoked_at',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'assigned_at' => 'datetime',
        'revoked_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function role()
    {
        return $this->belongsTo(ProductRole::class, 'product_role_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNull('revoked_at');
    }

    public function scopeForProduct(Builder $query, string $productKey): Builder
    {
        return $query->where('product_key', $productKey);
    }
}
