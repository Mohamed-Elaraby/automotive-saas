<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'code',
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
        return $this->hasMany(Plan::class);
    }

    public function tenantProductSubscriptions()
    {
        return $this->hasMany(TenantProductSubscription::class);
    }

    public function enablementRequests()
    {
        return $this->hasMany(ProductEnablementRequest::class);
    }
}
