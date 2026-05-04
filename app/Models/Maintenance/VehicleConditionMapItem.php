<?php

namespace App\Models\Maintenance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleConditionMapItem extends Model
{
    protected $fillable = [
        'map_id',
        'vehicle_area_code',
        'label',
        'note_type',
        'severity',
        'description',
        'customer_visible_note',
        'internal_note',
        'photo_id',
    ];

    public function map(): BelongsTo
    {
        return $this->belongsTo(VehicleConditionMap::class, 'map_id');
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(MaintenanceAttachment::class, 'photo_id');
    }
}
