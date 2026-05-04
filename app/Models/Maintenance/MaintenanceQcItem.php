<?php

namespace App\Models\Maintenance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceQcItem extends Model
{
    protected $fillable = [
        'qc_record_id',
        'label',
        'passed',
        'note',
    ];

    protected $casts = [
        'passed' => 'boolean',
    ];

    public function qcRecord(): BelongsTo
    {
        return $this->belongsTo(MaintenanceQcRecord::class, 'qc_record_id');
    }
}
