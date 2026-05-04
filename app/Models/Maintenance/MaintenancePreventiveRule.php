<?php

namespace App\Models\Maintenance;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenancePreventiveRule extends Model
{
    protected $fillable = ['branch_id', 'service_catalog_item_id', 'name', 'vehicle_make', 'vehicle_model', 'mileage_interval', 'months_interval', 'is_active', 'description'];

    protected $casts = ['is_active' => 'boolean'];

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function serviceCatalogItem(): BelongsTo { return $this->belongsTo(MaintenanceServiceCatalogItem::class); }
    public function reminders(): HasMany { return $this->hasMany(MaintenancePreventiveReminder::class, 'rule_id'); }
}
