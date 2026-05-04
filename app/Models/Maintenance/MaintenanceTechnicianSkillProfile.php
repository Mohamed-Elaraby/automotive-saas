<?php

namespace App\Models\Maintenance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceTechnicianSkillProfile extends Model
{
    protected $fillable = ['technician_id', 'skill_code', 'level', 'is_active', 'notes'];

    protected $casts = ['is_active' => 'boolean'];

    public function technician(): BelongsTo { return $this->belongsTo(User::class, 'technician_id'); }
}
