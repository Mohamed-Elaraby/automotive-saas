<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantProductSubscription extends Model
{
    protected $fillable = [
        'tenant_id',
        'product_id',
        'plan_id',
        'legacy_subscription_id',
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
        'last_synced_from_stripe_at',
        'last_sync_status',
        'last_sync_error',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'grace_ends_at' => 'datetime',
        'last_payment_failed_at' => 'datetime',
        'past_due_started_at' => 'datetime',
        'suspended_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'ends_at' => 'datetime',
        'last_synced_from_stripe_at' => 'datetime',
        'payment_failures_count' => 'integer',
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

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function legacySubscription()
    {
        return $this->belongsTo(Subscription::class, 'legacy_subscription_id');
    }
}
