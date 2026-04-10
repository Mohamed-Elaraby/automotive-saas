<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductCapability extends Model
{
    protected $fillable = [
        'product_id',
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

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
