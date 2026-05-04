<?php

namespace App\Models\Maintenance;

use App\Models\Branch;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleConditionMap extends Model
{
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'vehicle_id',
        'check_in_id',
        'work_order_id',
        'type',
        'status',
        'created_by',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
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

    public function items(): HasMany
    {
        return $this->hasMany(VehicleConditionMapItem::class, 'map_id');
    }
}
