<?php

namespace App\Models\Maintenance;

use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class MaintenanceWorkOrderJob extends Model
{
    protected $fillable = [
        'job_number',
        'work_order_id',
        'service_catalog_item_id',
        'assigned_technician_id',
        'title',
        'description',
        'status',
        'priority',
        'estimated_minutes',
        'actual_minutes',
        'assigned_at',
        'started_at',
        'paused_at',
        'completed_at',
        'qc_status',
        'customer_visible_notes',
        'internal_notes',
        'blocker_note',
        'created_by',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'started_at' => 'datetime',
        'paused_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function workOrder(): BelongsTo { return $this->belongsTo(WorkOrder::class); }
    public function serviceCatalogItem(): BelongsTo { return $this->belongsTo(MaintenanceServiceCatalogItem::class); }
    public function technician(): BelongsTo { return $this->belongsTo(User::class, 'assigned_technician_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function timeLogs(): HasMany { return $this->hasMany(MaintenanceJobTimeLog::class, 'job_id')->latest('id'); }
    public function partsRequests(): HasMany { return $this->hasMany(MaintenancePartsRequest::class, 'job_id')->latest('id'); }
    public function attachments(): MorphMany { return $this->morphMany(MaintenanceAttachment::class, 'attachable')->latest('id'); }
}
