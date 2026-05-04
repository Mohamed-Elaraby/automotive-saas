<?php

namespace App\Models\Maintenance;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MaintenanceAttachment extends Model
{
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'attachable_type',
        'attachable_id',
        'category',
        'file_disk',
        'file_path',
        'original_name',
        'mime_type',
        'size',
        'uploaded_by',
        'captured_at',
        'notes',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
    ];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
