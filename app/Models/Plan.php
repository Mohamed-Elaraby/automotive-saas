<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'price',
        'currency',
        'billing_period',
        'stripe_price_id',
        'is_active',
        'sort_order',
        'max_users',
        'max_branches',
        'max_products',
        'max_storage_mb',
        'features',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'features' => 'array',
        'price' => 'decimal:2',
    ];
}
