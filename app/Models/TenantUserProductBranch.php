<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TenantUserProductBranch extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'product_key',
        'branch_id',
        'access_level',
        'is_enabled',
        'granted_at',
        'revoked_at',
        'metadata',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'granted_at' => 'datetime',
        'revoked_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true)->whereNull('revoked_at');
    }

    public function scopeForProduct(Builder $query, string $productKey): Builder
    {
        return $query->where('product_key', $productKey);
    }
}
