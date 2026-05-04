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
use Illuminate\Database\Eloquent\Relations\MorphMany;

class VehicleCheckIn extends Model
{
    protected $fillable = [
        'check_in_number',
        'branch_id',
        'customer_id',
        'vehicle_id',
        'work_order_id',
        'service_advisor_id',
        'status',
        'odometer',
        'fuel_level',
        'warning_lights',
        'personal_belongings',
        'customer_complaint',
        'existing_damage_notes',
        'customer_visible_notes',
        'internal_notes',
        'expected_delivery_at',
        'vin_number',
        'vin_verified_at',
        'vin_verified_by',
        'vin_verification_method',
        'vin_confidence_score',
        'vin_source_image_id',
        'customer_signature',
        'service_advisor_signature',
        'created_by',
        'checked_in_at',
    ];

    protected $casts = [
        'warning_lights' => 'array',
        'personal_belongings' => 'array',
        'expected_delivery_at' => 'datetime',
        'vin_verified_at' => 'datetime',
        'vin_confidence_score' => 'decimal:2',
        'checked_in_at' => 'datetime',
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

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function serviceAdvisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'service_advisor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function vinVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vin_verified_by');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(MaintenanceAttachment::class, 'attachable')->latest('id');
    }

    public function conditionMaps(): HasMany
    {
        return $this->hasMany(VehicleConditionMap::class, 'check_in_id');
    }
}
