<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AccessAuditLog extends Model
{
    protected $fillable = [
        'product_key',
        'branch_id',
        'actor_user_id',
        'target_user_id',
        'subject_type',
        'subject_id',
        'action',
        'event_key',
        'old_values',
        'new_values',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'branch_id' => 'integer',
        'actor_user_id' => 'integer',
        'target_user_id' => 'integer',
        'subject_id' => 'integer',
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForProduct(Builder $query, string $productKey): Builder
    {
        return $query->where('product_key', $productKey);
    }

    public function scopeEvent(Builder $query, string $eventKey): Builder
    {
        return $query->where('event_key', $eventKey);
    }
}
