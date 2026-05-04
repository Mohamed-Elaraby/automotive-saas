<?php

namespace App\Models\Maintenance;

use Illuminate\Database\Eloquent\Model;

class MaintenanceServiceCatalogItem extends Model
{
    protected $fillable = [
        'service_number',
        'name',
        'category',
        'estimated_minutes',
        'default_labor_price',
        'is_taxable',
        'warranty_days',
        'required_skill',
        'required_bay_type',
        'is_package',
        'is_active',
        'description',
    ];

    protected $casts = [
        'default_labor_price' => 'decimal:2',
        'is_taxable' => 'boolean',
        'is_package' => 'boolean',
        'is_active' => 'boolean',
    ];
}
