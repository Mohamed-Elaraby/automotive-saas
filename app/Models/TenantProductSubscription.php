<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class TenantProductSubscription extends Model
{
    protected $fillable = [
        'tenant_id',
        'product_id',
        'product_key',
        'plan_id',
        'legacy_subscription_id',
        'status',
        'included_seats',
        'extra_seats',
        'branch_limit',
        'usage_limits',
        'activation_status',
        'provisioning_status',
        'provisioning_started_at',
        'provisioning_completed_at',
        'provisioning_failed_at',
        'activated_at',
        'activation_error',
        'activation_source',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
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
        'included_seats' => 'integer',
        'extra_seats' => 'integer',
        'branch_limit' => 'integer',
        'usage_limits' => 'array',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
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
                blank($subscription->product_key)
                && filled($subscription->product_id)
                && Schema::connection($subscription->getConnectionName())->hasColumn($subscription->getTable(), 'product_key')
            ) {
                $subscription->product_key = Product::query()
                    ->whereKey($subscription->product_id)
                    ->value('code');
            }

            if (
                blank($subscription->included_seats)
                && filled($subscription->plan_id)
                && Schema::connection($subscription->getConnectionName())->hasColumn($subscription->getTable(), 'included_seats')
            ) {
                $plan = Plan::query()->whereKey($subscription->plan_id)->first(['max_users', 'max_branches']);

                if ($plan) {
                    $subscription->included_seats = $plan->max_users;
                    $subscription->branch_limit = $subscription->branch_limit ?? $plan->max_branches;
                }
            }

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
