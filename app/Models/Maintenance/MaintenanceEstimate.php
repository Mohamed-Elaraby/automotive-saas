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

class MaintenanceEstimate extends Model
{
    protected $fillable = [
        'estimate_number',
        'branch_id',
        'customer_id',
        'vehicle_id',
        'check_in_id',
        'work_order_id',
        'status',
        'valid_until',
        'expected_delivery_at',
        'subtotal',
        'discount_total',
        'tax_total',
        'grand_total',
        'terms',
        'customer_visible_notes',
        'internal_notes',
        'approval_method',
        'approved_amount',
        'approved_at',
        'approved_by',
        'created_by',
    ];

    protected $casts = [
        'valid_until' => 'date',
        'expected_delivery_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function checkIn(): BelongsTo
    {
        return $this->belongsTo(VehicleCheckIn::class, 'check_in_id');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(MaintenanceEstimateLine::class, 'estimate_id');
    }
}
