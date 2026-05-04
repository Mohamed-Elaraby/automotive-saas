<?php

namespace App\Models\Maintenance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceEstimateLine extends Model
{
    protected $fillable = [
        'estimate_id',
        'service_catalog_item_id',
        'line_type',
        'description',
        'quantity',
        'unit_price',
        'discount_amount',
        'tax_amount',
        'total_price',
        'approval_status',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function estimate(): BelongsTo
    {
        return $this->belongsTo(MaintenanceEstimate::class, 'estimate_id');
    }

    public function serviceCatalogItem(): BelongsTo
    {
        return $this->belongsTo(MaintenanceServiceCatalogItem::class);
    }
}
