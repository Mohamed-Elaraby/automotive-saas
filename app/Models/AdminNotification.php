<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'title',
        'message',
        'severity',
        'source_type',
        'source_id',
        'route_name',
        'route_params',
        'target_url',
        'tenant_id',
        'user_id',
        'user_email',
        'context_payload',
        'is_read',
        'read_at',
        'is_archived',
        'archived_at',
        'notified_at',
    ];

    protected $casts = [
        'route_params' => 'array',
        'context_payload' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'is_archived' => 'boolean',
        'archived_at' => 'datetime',
        'notified_at' => 'datetime',
    ];

    public function getConnectionName(): ?string
    {
        return (string) (config('tenancy.database.central_connection') ?? config('database.default'));
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeActive($query)
    {
        return $query->where('is_archived', false);
    }

    public function resolvedUrl(): string
    {
        if (! empty($this->target_url)) {
            return (string) $this->target_url;
        }

        if (! empty($this->route_name)) {
            return route((string) $this->route_name, $this->route_params ?? []);
        }

        return route('admin.notifications.show', $this->id);
    }
}
