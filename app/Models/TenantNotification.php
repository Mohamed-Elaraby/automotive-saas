<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TenantNotification extends Model
{
    protected $fillable = [
        'tenant_id',
        'product_key',
        'branch_id',
        'event_key',
        'channel',
        'recipient_type',
        'recipient_id',
        'recipient_contact',
        'title',
        'body',
        'status',
        'metadata',
        'sent_at',
        'read_at',
        'archived_at',
    ];

    protected $casts = [
        'branch_id' => 'integer',
        'recipient_id' => 'integer',
        'metadata' => 'array',
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function scopeForProduct(Builder $query, string $productKey): Builder
    {
        return $query->where('product_key', $productKey);
    }
}
