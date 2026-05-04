<?php

namespace App\Models\Maintenance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceInspectionTemplate extends Model
{
    protected $fillable = [
        'template_number',
        'name',
        'inspection_type',
        'is_default',
        'is_active',
        'description',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(MaintenanceInspectionTemplateItem::class, 'template_id')->orderBy('sort_order');
    }
}
