<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerPortalNotification extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'severity',
        'tenant_id',
        'product_id',
        'target_url',
        'context_payload',
        'is_read',
        'read_at',
        'notified_at',
    ];

    protected $casts = [
        'context_payload' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'notified_at' => 'datetime',
    ];

    public function getConnectionName(): ?string
    {
        return (string) (config('tenancy.database.central_connection') ?? config('database.default'));
    }
}
