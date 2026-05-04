<?php

namespace App\Models\Maintenance;

use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceWarrantyClaim extends Model
{
    protected $fillable = [
        'claim_number',
        'warranty_id',
        'original_work_order_id',
        'rework_work_order_id',
        'customer_id',
        'vehicle_id',
        'status',
        'comeback_reason',
        'customer_complaint',
        'root_cause',
        'resolution',
        'cost_impact',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'cost_impact' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function warranty(): BelongsTo { return $this->belongsTo(MaintenanceWarranty::class); }
    public function originalWorkOrder(): BelongsTo { return $this->belongsTo(WorkOrder::class, 'original_work_order_id'); }
    public function reworkWorkOrder(): BelongsTo { return $this->belongsTo(WorkOrder::class, 'rework_work_order_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
