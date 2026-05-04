<?php

namespace App\Models\Maintenance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceInspectionItem extends Model
{
    protected $fillable = [
        'inspection_id',
        'template_item_id',
        'section',
        'label',
        'result',
        'note',
        'recommendation',
        'estimated_cost',
        'customer_approval_status',
        'photo_id',
    ];

    protected $casts = [
        'estimated_cost' => 'decimal:2',
    ];

    public function inspection(): BelongsTo { return $this->belongsTo(MaintenanceInspection::class, 'inspection_id'); }
    public function templateItem(): BelongsTo { return $this->belongsTo(MaintenanceInspectionTemplateItem::class, 'template_item_id'); }
    public function photo(): BelongsTo { return $this->belongsTo(MaintenanceAttachment::class, 'photo_id'); }
}
