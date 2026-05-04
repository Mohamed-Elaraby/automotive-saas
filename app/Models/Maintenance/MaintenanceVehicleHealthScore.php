<?php

namespace App\Models\Maintenance;

use App\Models\Customer;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceVehicleHealthScore extends Model
{
    protected $fillable = ['vehicle_id', 'customer_id', 'overall_score', 'engine_score', 'brakes_score', 'suspension_score', 'ac_score', 'electrical_score', 'tires_score', 'signals', 'calculated_at'];

    protected $casts = ['signals' => 'array', 'calculated_at' => 'datetime'];

    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
}
