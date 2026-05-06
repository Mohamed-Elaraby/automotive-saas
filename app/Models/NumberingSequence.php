<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class NumberingSequence extends Model
{
    protected $fillable = [
        'tenant_id',
        'product_key',
        'document_type',
        'branch_id',
        'year',
        'prefix',
        'next_number',
        'padding',
        'reset_strategy',
        'metadata',
    ];

    protected $casts = [
        'branch_id' => 'integer',
        'year' => 'integer',
        'next_number' => 'integer',
        'padding' => 'integer',
        'metadata' => 'array',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeForScope(Builder $query, array $scope): Builder
    {
        return $query
            ->where('tenant_id', $scope['tenant_id'])
            ->where('product_key', $scope['product_key'])
            ->where('document_type', $scope['document_type'])
            ->where('branch_id', $scope['branch_id'])
            ->where('year', $scope['year']);
    }
}
