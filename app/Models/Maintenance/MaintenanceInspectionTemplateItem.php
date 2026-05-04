<?php

namespace App\Models\Maintenance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceInspectionTemplateItem extends Model
{
    protected $fillable = [
        'template_id',
        'section',
        'label',
        'default_result',
        'requires_photo',
        'sort_order',
    ];

    protected $casts = [
        'requires_photo' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(MaintenanceInspectionTemplate::class, 'template_id');
    }
}
