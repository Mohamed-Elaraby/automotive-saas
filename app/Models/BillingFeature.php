<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingFeature extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setConnection(
            config('tenancy.database.central_connection') ?? config('database.default')
        );
    }

    public function plans()
    {
        return $this->belongsToMany(Plan::class, 'billing_feature_plan')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }
}
