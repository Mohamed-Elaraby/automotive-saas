<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class TenantProductSubscription extends Model
{
    protected $fillable = [
        'tenant_id',
        'product_id',
        'plan_id',
        'legacy_subscription_id',
        'status',
        'activation_status',
        'provisioning_status',
        'provisioning_started_at',
        'provisioning_completed_at',
        'provisioning_failed_at',
        'activated_at',
        'activation_error',
        'activation_source',
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
        'provisioning_started_at' => 'datetime',
        'provisioning_completed_at' => 'datetime',
        'provisioning_failed_at' => 'datetime',
        'activated_at' => 'datetime',
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

    protected static function booted(): void
    {
        static::creating(function (TenantProductSubscription $subscription): void {
            if (
                in_array((string) $subscription->status, ['active', 'trialing'], true)
                && blank($subscription->activation_status)
                && blank($subscription->provisioning_status)
                && Schema::connection($subscription->getConnectionName())->hasColumn($subscription->getTable(), 'activation_status')
                && Schema::connection($subscription->getConnectionName())->hasColumn($subscription->getTable(), 'provisioning_status')
            ) {
                $subscription->activation_status = 'active';
                $subscription->provisioning_status = 'active';
                $subscription->provisioning_completed_at = $subscription->provisioning_completed_at ?? now();
                $subscription->activated_at = $subscription->activated_at ?? now();
                $subscription->activation_source = $subscription->activation_source ?? 'model_default';
            }
        });
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
