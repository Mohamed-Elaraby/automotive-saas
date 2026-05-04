<?php

namespace App\Models\Maintenance;

use App\Models\Branch;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceQcRecord extends Model
{
    protected $fillable = [
        'qc_number',
        'branch_id',
        'work_order_id',
        'vehicle_id',
        'status',
        'result',
        'qc_inspector_id',
        'started_at',
        'completed_at',
        'final_notes',
        'rework_reason',
        'created_by',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function workOrder(): BelongsTo { return $this->belongsTo(WorkOrder::class); }
    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
    public function inspector(): BelongsTo { return $this->belongsTo(User::class, 'qc_inspector_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function items(): HasMany { return $this->hasMany(MaintenanceQcItem::class, 'qc_record_id'); }
}
