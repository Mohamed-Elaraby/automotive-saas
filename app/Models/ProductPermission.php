<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProductPermission extends Model
{
    protected $fillable = [
        'tenant_id',
        'product_key',
        'permission_key',
        'name',
        'group_key',
        'description',
        'is_system',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function roles()
    {
        return $this->belongsToMany(ProductRole::class, 'product_role_permission')
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForProduct(Builder $query, string $productKey): Builder
    {
        return $query->where('product_key', $productKey);
    }
}
