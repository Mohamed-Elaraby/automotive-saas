<?php

namespace App\Models\Core\Documents;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class GeneratedDocument extends Model
{
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'product_code',
        'module_code',
        'documentable_type',
        'documentable_id',
        'document_type',
        'document_number',
        'document_title',
        'language',
        'direction',
        'file_disk',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'checksum',
        'version',
        'status',
        'generated_by',
        'generated_at',
        'verified_token',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function generator(): BelongsTo { return $this->belongsTo(User::class, 'generated_by'); }
    public function canceller(): BelongsTo { return $this->belongsTo(User::class, 'cancelled_by'); }
    public function documentable(): MorphTo { return $this->morphTo(); }
    public function snapshot(): HasOne { return $this->hasOne(DocumentSnapshot::class, 'generated_document_id'); }
}
