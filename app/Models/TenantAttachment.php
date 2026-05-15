<?php

namespace App\Models;

use App\Models\Concerns\HasBranchScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TenantAttachment extends Model
{
    use HasBranchScope;

    protected $table = 'tenant_attachments';

    protected $fillable = [
        'tenant_id',
        'product_key',
        'branch_id',
        'attachable_type',
        'attachable_id',
        'original_name',
        'stored_name',
        'mime_type',
        'extension',
        'file_size',
        'disk',
        'storage_path',
        'visibility',
        'uploaded_by',
        'metadata',
    ];

    protected $casts = [
        'branch_id' => 'integer',
        'attachable_id' => 'integer',
        'file_size' => 'integer',
        'uploaded_by' => 'integer',
        'metadata' => 'array',
    ];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeForProduct(Builder $query, string $productKey): Builder
    {
        return $query->where('product_key', $productKey);
    }
}
