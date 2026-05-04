<?php

namespace App\Models\Maintenance;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceInspection extends Model
{
    protected $fillable = [
        'inspection_number',
        'branch_id',
        'work_order_id',
        'check_in_id',
        'vehicle_id',
        'customer_id',
        'template_id',
        'inspection_type',
        'status',
        'assigned_to',
        'started_by',
        'completed_by',
        'started_at',
        'completed_at',
        'summary',
        'customer_visible_notes',
        'internal_notes',
        'created_by',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function workOrder(): BelongsTo { return $this->belongsTo(WorkOrder::class); }
    public function checkIn(): BelongsTo { return $this->belongsTo(VehicleCheckIn::class, 'check_in_id'); }
    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function template(): BelongsTo { return $this->belongsTo(MaintenanceInspectionTemplate::class, 'template_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function starter(): BelongsTo { return $this->belongsTo(User::class, 'started_by'); }
    public function completer(): BelongsTo { return $this->belongsTo(User::class, 'completed_by'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function items(): HasMany
    {
        return $this->hasMany(MaintenanceInspectionItem::class, 'inspection_id');
    }
}
