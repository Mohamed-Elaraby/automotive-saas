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
        'stripe_product_id',
        'stripe_price_id',
        'is_active',
        'sort_order',
        'max_users',
        'max_branches',
        'max_products',
        'max_storage_mb',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'decimal:2',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setConnection(
            config('tenancy.database.central_connection') ?? config('database.default')
        );
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function billingFeatures()
    {
        return $this->belongsToMany(BillingFeature::class, 'billing_feature_plan')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order')
            ->orderBy('billing_features.id');
    }
}
