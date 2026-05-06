<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'tenant_id',
        'customer_number',
        'name',
        'display_name',
        'phone',
        'email',
        'tax_number',
        'address',
        'customer_type',
        'company_name',
        'status',
        'internal_notes',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(\App\Models\Maintenance\VehicleCheckIn::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(\App\Models\Maintenance\MaintenanceAppointment::class);
    }

    public function estimates(): HasMany
    {
        return $this->hasMany(\App\Models\Maintenance\MaintenanceEstimate::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(\App\Models\Maintenance\MaintenanceInvoice::class);
    }

    public function warranties(): HasMany
    {
        return $this->hasMany(\App\Models\Maintenance\MaintenanceWarranty::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(\App\Models\Maintenance\MaintenanceComplaint::class);
    }

    public function fleetAccount()
    {
        return $this->hasOne(\App\Models\Maintenance\MaintenanceFleetAccount::class);
    }

    public function productProfiles(): HasMany
    {
        return $this->hasMany(ProductCustomerProfile::class);
    }
}
