<?php

namespace App\Models\Maintenance;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MaintenanceApprovalRequest extends Model
{
    protected $fillable = [
        'branch_id',
        'requested_by',
        'decided_by',
        'approval_type',
        'status',
        'approvable_type',
        'approvable_id',
        'reason',
        'payload',
        'decision_notes',
        'requested_at',
        'decided_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'requested_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }
}
