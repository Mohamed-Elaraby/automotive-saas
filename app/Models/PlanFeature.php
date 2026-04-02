<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanFeature extends Model
{
    protected $fillable = [
        'plan_id',
        'title',
        'sort_order',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setConnection(
            config('tenancy.database.central_connection') ?? config('database.default')
        );
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
