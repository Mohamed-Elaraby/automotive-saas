<?php

namespace App\Models\Core\Documents;

use Illuminate\Database\Eloquent\Model;

class DocumentTemplate extends Model
{
    protected $fillable = [
        'tenant_id',
        'product_key',
        'document_type',
        'document_key',
        'name',
        'view_path',
        'language',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];
}
