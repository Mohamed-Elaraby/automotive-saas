<?php

namespace App\Models\Maintenance;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceServiceRecommendation extends Model
{
    protected $fillable = ['branch_id', 'vehicle_id', 'customer_id', 'service_catalog_item_id', 'source', 'priority', 'status', 'title', 'description', 'due_date', 'due_mileage', 'signals'];

    protected $casts = ['due_date' => 'date', 'signals' => 'array'];

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function serviceCatalogItem(): BelongsTo { return $this->belongsTo(MaintenanceServiceCatalogItem::class); }
}
