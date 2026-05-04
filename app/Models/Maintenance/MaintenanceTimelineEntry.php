<?php

namespace App\Models\Maintenance;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceTimelineEntry extends Model
{
    protected $fillable = [
        'work_order_id',
        'check_in_id',
        'vehicle_id',
        'event_type',
        'title',
        'customer_visible_note',
        'internal_note',
        'payload',
        'created_by',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function checkIn(): BelongsTo
    {
        return $this->belongsTo(VehicleCheckIn::class, 'check_in_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
