<?php

namespace App\Models\Maintenance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceJobTimeLog extends Model
{
    protected $fillable = [
        'job_id',
        'technician_id',
        'action',
        'started_at',
        'ended_at',
        'duration_minutes',
        'note',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function job(): BelongsTo { return $this->belongsTo(MaintenanceWorkOrderJob::class, 'job_id'); }
    public function technician(): BelongsTo { return $this->belongsTo(User::class, 'technician_id'); }
}
