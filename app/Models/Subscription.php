<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'tenant_id',
        'plan_id',
        'status',
        'trial_ends_at',
        'grace_ends_at',
        'last_payment_failed_at',
        'past_due_started_at',
        'suspended_at',
        'cancelled_at',
        'payment_failures_count',
        'ends_at',
        'external_id',
        'gateway',
        'gateway_customer_id',
        'gateway_subscription_id',
        'gateway_checkout_session_id',
        'gateway_price_id',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'grace_ends_at' => 'datetime',
        'last_payment_failed_at' => 'datetime',
        'past_due_started_at' => 'datetime',
        'suspended_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'ends_at' => 'datetime',
        'payment_failures_count' => 'integer',
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
