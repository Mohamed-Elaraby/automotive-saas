<?php

namespace App\Models\Maintenance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceSetting extends Model
{
    protected $fillable = [
        'setting_key',
        'group_code',
        'setting_value',
        'updated_by',
    ];

    protected $casts = [
        'setting_value' => 'array',
    ];

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
