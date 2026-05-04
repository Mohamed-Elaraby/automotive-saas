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

class MaintenanceWarranty extends Model
{
    protected $fillable = [
        'warranty_number',
        'branch_id',
        'work_order_id',
        'service_catalog_item_id',
        'customer_id',
        'vehicle_id',
        'warranty_type',
        'start_date',
        'end_date',
        'mileage_limit',
        'status',
        'terms',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function workOrder(): BelongsTo { return $this->belongsTo(WorkOrder::class); }
    public function serviceCatalogItem(): BelongsTo { return $this->belongsTo(MaintenanceServiceCatalogItem::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function claims(): HasMany { return $this->hasMany(MaintenanceWarrantyClaim::class, 'warranty_id'); }
}
