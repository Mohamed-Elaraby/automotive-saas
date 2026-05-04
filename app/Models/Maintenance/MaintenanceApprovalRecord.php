<?php

namespace App\Models\Maintenance;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceApprovalRecord extends Model
{
    protected $fillable = [
        'branch_id',
        'estimate_id',
        'work_order_id',
        'customer_id',
        'vehicle_id',
        'approval_type',
        'status',
        'method',
        'approved_amount',
        'approved_items',
        'rejected_items',
        'reason',
        'terms_snapshot',
        'terms_accepted',
        'ip_address',
        'device_summary',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'approved_amount' => 'decimal:2',
        'approved_items' => 'array',
        'rejected_items' => 'array',
        'terms_accepted' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function estimate(): BelongsTo { return $this->belongsTo(MaintenanceEstimate::class, 'estimate_id'); }
    public function workOrder(): BelongsTo { return $this->belongsTo(WorkOrder::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
