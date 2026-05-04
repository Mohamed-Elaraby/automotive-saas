<?php

namespace App\Models\Maintenance;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceComplaint extends Model
{
    protected $fillable = [
        'complaint_number',
        'branch_id',
        'work_order_id',
        'customer_id',
        'vehicle_id',
        'source',
        'status',
        'severity',
        'customer_visible_note',
        'internal_note',
        'resolution',
        'assigned_to',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function workOrder(): BelongsTo { return $this->belongsTo(WorkOrder::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function resolver(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by'); }
}
