<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TenantUserProductAccess extends Model
{
    protected $table = 'tenant_user_product_access';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'product_key',
        'status',
        'role_id',
        'granted_by',
        'granted_at',
        'revoked_at',
        'metadata',
    ];

    protected $casts = [
        'role_id' => 'integer',
        'granted_by' => 'integer',
        'granted_at' => 'datetime',
        'revoked_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function grantedBy()
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')->whereNull('revoked_at');
    }

    public function scopeForProduct(Builder $query, string $productKey): Builder
    {
        return $query->where('product_key', $productKey);
    }
}
