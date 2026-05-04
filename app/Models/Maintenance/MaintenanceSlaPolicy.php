<?php

namespace App\Models\Maintenance;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceSlaPolicy extends Model
{
    protected $fillable = ['branch_id', 'stage_code', 'name', 'target_minutes', 'is_active', 'description'];

    protected $casts = ['is_active' => 'boolean'];

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
}
