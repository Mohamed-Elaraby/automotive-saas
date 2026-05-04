<?php

namespace App\Models\Core\Documents;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentSnapshot extends Model
{
    protected $fillable = [
        'generated_document_id',
        'snapshot',
    ];

    protected $casts = [
        'snapshot' => 'array',
    ];

    public function generatedDocument(): BelongsTo
    {
        return $this->belongsTo(GeneratedDocument::class);
    }
}
