<?php

namespace App\Models\Maintenance;

use App\Models\Branch;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceDelayAlert extends Model
{
    protected $fillable = ['branch_id', 'work_order_id', 'stage_code', 'target_minutes', 'elapsed_minutes', 'status', 'message', 'detected_at', 'resolved_at'];

    protected $casts = ['detected_at' => 'datetime', 'resolved_at' => 'datetime'];

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function workOrder(): BelongsTo { return $this->belongsTo(WorkOrder::class); }
}
