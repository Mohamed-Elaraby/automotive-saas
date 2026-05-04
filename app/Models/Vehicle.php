<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $fillable = [
        'vehicle_number',
        'customer_id',
        'make',
        'model',
        'year',
        'trim',
        'color',
        'plate_number',
        'plate_source',
        'plate_country',
        'vin',
        'vin_verified_at',
        'vin_verified_by',
        'vin_verification_method',
        'vin_confidence_score',
        'vin_source_image_id',
        'odometer',
        'fuel_type',
        'transmission',
        'engine_number',
        'warranty_status',
        'last_service_date',
        'next_service_due_at',
        'notes',
    ];

    protected $casts = [
        'vin_verified_at' => 'datetime',
        'vin_confidence_score' => 'decimal:2',
        'last_service_date' => 'date',
        'next_service_due_at' => 'date',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function workOrders()
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function checkIns()
    {
        return $this->hasMany(\App\Models\Maintenance\VehicleCheckIn::class);
    }

    public function attachments()
    {
        return $this->morphMany(\App\Models\Maintenance\MaintenanceAttachment::class, 'attachable')->latest('id');
    }

    public function healthScores()
    {
        return $this->hasMany(\App\Models\Maintenance\MaintenanceVehicleHealthScore::class)->latest('id');
    }

    public function serviceRecommendations()
    {
        return $this->hasMany(\App\Models\Maintenance\MaintenanceServiceRecommendation::class)->latest('id');
    }

    public function preventiveReminders()
    {
        return $this->hasMany(\App\Models\Maintenance\MaintenancePreventiveReminder::class)->latest('id');
    }
}
