<?php

namespace App\Models\Maintenance;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MaintenanceNotification extends Model
{
    protected $fillable = [
        'branch_id',
        'user_id',
        'channel',
        'event_type',
        'title',
        'message',
        'severity',
        'notifiable_type',
        'notifiable_id',
        'payload',
        'read_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'read_at' => 'datetime',
    ];

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function notifiable(): MorphTo { return $this->morphTo(); }
}
