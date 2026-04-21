<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspaceIntegrationHandoff extends Model
{
    protected $fillable = [
        'integration_key',
        'event_name',
        'source_product',
        'target_product',
        'source_type',
        'source_id',
        'target_type',
        'target_id',
        'status',
        'idempotency_key',
        'payload',
        'error_message',
        'attempts',
        'created_by',
        'last_attempted_at',
        'completed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'last_attempted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
