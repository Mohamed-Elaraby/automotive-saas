<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkOrder extends Model
{
    protected $fillable = [
        'branch_id',
        'customer_id',
        'vehicle_id',
        'service_advisor_id',
        'work_order_number',
        'title',
        'status',
        'priority',
        'vehicle_status',
        'payment_status',
        'customer_tracking_token',
        'opened_at',
        'expected_delivery_at',
        'closed_at',
        'notes',
        'customer_visible_notes',
        'internal_notes',
        'created_by',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'expected_delivery_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function serviceAdvisor()
    {
        return $this->belongsTo(User::class, 'service_advisor_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'reference_id')
            ->where('reference_type', self::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(WorkOrderLine::class)->latest('id');
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(\App\Models\Maintenance\VehicleCheckIn::class);
    }

    public function attachments()
    {
        return $this->morphMany(\App\Models\Maintenance\MaintenanceAttachment::class, 'attachable')->latest('id');
    }

    public function timelineEntries(): HasMany
    {
        return $this->hasMany(\App\Models\Maintenance\MaintenanceTimelineEntry::class)->latest('id');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(\App\Models\Maintenance\MaintenanceInspection::class);
    }

    public function diagnosisRecords(): HasMany
    {
        return $this->hasMany(\App\Models\Maintenance\MaintenanceDiagnosisRecord::class);
    }

    public function maintenanceJobs(): HasMany
    {
        return $this->hasMany(\App\Models\Maintenance\MaintenanceWorkOrderJob::class);
    }

    public function qcRecords(): HasMany
    {
        return $this->hasMany(\App\Models\Maintenance\MaintenanceQcRecord::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(\App\Models\Maintenance\MaintenanceDelivery::class);
    }

    public function warranties(): HasMany
    {
        return $this->hasMany(\App\Models\Maintenance\MaintenanceWarranty::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(\App\Models\Maintenance\MaintenanceComplaint::class);
    }

    public function partsRequests(): HasMany
    {
        return $this->hasMany(\App\Models\Maintenance\MaintenancePartsRequest::class);
    }
}
