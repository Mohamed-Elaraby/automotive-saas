<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemErrorLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'occurred_at',
        'level',
        'exception_class',
        'message',
        'file_path',
        'file_line',
        'trace_excerpt',
        'app_env',
        'app_url',
        'request_method',
        'request_url',
        'request_path',
        'route_name',
        'route_action',
        'ip',
        'user_agent',
        'user_id',
        'user_email',
        'tenant_id',
        'input_payload',
        'context_payload',
        'is_read',
        'read_at',
        'is_resolved',
        'resolved_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'file_line' => 'integer',
        'user_id' => 'integer',
        'input_payload' => 'array',
        'context_payload' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'is_resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public function getConnectionName(): ?string
    {
        return (string) (config('tenancy.database.central_connection') ?? config('database.default'));
    }
}
