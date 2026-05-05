<?php

namespace App\Models\Maintenance;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceAppointment extends Model
{
    protected $fillable = [
        'appointment_number',
        'branch_id',
        'customer_id',
        'vehicle_id',
        'service_advisor_id',
        'check_in_id',
        'type',
        'status',
        'source',
        'scheduled_at',
        'expected_arrival_at',
        'arrived_at',
        'cancelled_at',
        'customer_name',
        'customer_phone',
        'customer_email',
        'vehicle_make',
        'vehicle_model',
        'vehicle_year',
        'plate_number',
        'vin_number',
        'service_type',
        'priority',
        'customer_complaint',
        'customer_visible_notes',
        'internal_notes',
        'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'expected_arrival_at' => 'datetime',
        'arrived_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
    public function serviceAdvisor(): BelongsTo { return $this->belongsTo(User::class, 'service_advisor_id'); }
    public function checkIn(): BelongsTo { return $this->belongsTo(VehicleCheckIn::class, 'check_in_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
