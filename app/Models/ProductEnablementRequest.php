<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductEnablementRequest extends Model
{
    protected $fillable = [
        'user_id',
        'tenant_id',
        'product_id',
        'status',
        'requested_at',
        'approved_at',
        'rejected_at',
        'notes',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
