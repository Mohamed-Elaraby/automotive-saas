<?php

namespace App\Models\Maintenance;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceDelivery extends Model
{
    protected $fillable = [
        'delivery_number',
        'branch_id',
        'work_order_id',
        'customer_id',
        'vehicle_id',
        'status',
        'checklist',
        'payment_status',
        'customer_signature',
        'advisor_signature',
        'delivered_at',
        'delivered_by',
        'customer_visible_notes',
        'internal_notes',
    ];

    protected $casts = [
        'checklist' => 'array',
        'delivered_at' => 'datetime',
    ];

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function workOrder(): BelongsTo { return $this->belongsTo(WorkOrder::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
    public function deliverer(): BelongsTo { return $this->belongsTo(User::class, 'delivered_by'); }
}
