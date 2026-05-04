<?php

namespace App\Models\Maintenance;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceDiagnosisRecord extends Model
{
    protected $fillable = [
        'diagnosis_number',
        'branch_id',
        'work_order_id',
        'inspection_id',
        'vehicle_id',
        'customer_id',
        'complaint',
        'symptoms',
        'scanner_report',
        'fault_codes',
        'root_cause',
        'recommended_repair',
        'estimated_labor_hours',
        'estimated_minutes',
        'priority',
        'technician_notes',
        'diagnosed_by',
        'created_by',
    ];

    protected $casts = [
        'fault_codes' => 'array',
        'estimated_labor_hours' => 'decimal:2',
    ];

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function workOrder(): BelongsTo { return $this->belongsTo(WorkOrder::class); }
    public function inspection(): BelongsTo { return $this->belongsTo(MaintenanceInspection::class, 'inspection_id'); }
    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function technician(): BelongsTo { return $this->belongsTo(User::class, 'diagnosed_by'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
