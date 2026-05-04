<?php

namespace App\Models\Maintenance;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenancePreventiveReminder extends Model
{
    protected $fillable = ['branch_id', 'rule_id', 'vehicle_id', 'customer_id', 'service_catalog_item_id', 'status', 'due_date', 'due_mileage', 'notes', 'notified_at'];

    protected $casts = ['due_date' => 'date', 'notified_at' => 'datetime'];

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function rule(): BelongsTo { return $this->belongsTo(MaintenancePreventiveRule::class, 'rule_id'); }
    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function serviceCatalogItem(): BelongsTo { return $this->belongsTo(MaintenanceServiceCatalogItem::class); }
}
