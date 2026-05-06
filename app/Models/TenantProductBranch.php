<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TenantProductBranch extends Model
{
    protected $fillable = [
        'tenant_id',
        'product_key',
        'branch_id',
        'is_enabled',
        'activated_at',
        'deactivated_at',
        'metadata',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'activated_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true)->whereNull('deactivated_at');
    }

    public function scopeForProduct(Builder $query, string $productKey): Builder
    {
        return $query->where('product_key', $productKey);
    }
}
