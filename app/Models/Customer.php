<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'customer_number',
        'name',
        'phone',
        'email',
        'customer_type',
        'company_name',
        'tax_number',
        'internal_notes',
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
}
