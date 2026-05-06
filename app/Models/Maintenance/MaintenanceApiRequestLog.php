<?php

namespace App\Models\Maintenance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceApiRequestLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'token_id',
        'route_name',
        'method',
        'path',
        'ip_address',
        'user_agent',
        'idempotency_key',
        'status_code',
        'request_summary',
        'response_summary',
        'created_at',
    ];

    protected $casts = [
        'request_summary' => 'array',
        'response_summary' => 'array',
        'created_at' => 'datetime',
    ];

    public function token(): BelongsTo
    {
        return $this->belongsTo(MaintenanceApiToken::class, 'token_id');
    }
}
