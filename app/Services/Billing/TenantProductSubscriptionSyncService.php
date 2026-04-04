<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\TenantProductSubscription;

class TenantProductSubscriptionSyncService
{
    public function syncFromLegacySubscription(int|Subscription $subscription): ?TenantProductSubscription
    {
        $legacySubscription = $subscription instanceof Subscription
            ? $subscription
            : Subscription::query()->find($subscription);

        if (! $legacySubscription) {
            return null;
        }

        $plan = null;
        if (! empty($legacySubscription->plan_id)) {
            $plan = Plan::query()->find($legacySubscription->plan_id);
        }

        $productId = $plan?->product_id;
        if (! $productId) {
            return null;
        }

        return TenantProductSubscription::query()->updateOrCreate(
            [
                'tenant_id' => $legacySubscription->tenant_id,
                'product_id' => $productId,
                'legacy_subscription_id' => $legacySubscription->id,
            ],
            [
                'plan_id' => $legacySubscription->plan_id,
                'status' => $legacySubscription->status,
                'trial_ends_at' => $legacySubscription->trial_ends_at,
                'grace_ends_at' => $legacySubscription->grace_ends_at,
                'last_payment_failed_at' => $legacySubscription->last_payment_failed_at,
                'past_due_started_at' => $legacySubscription->past_due_started_at,
                'suspended_at' => $legacySubscription->suspended_at,
                'cancelled_at' => $legacySubscription->cancelled_at,
                'payment_failures_count' => $legacySubscription->payment_failures_count ?? 0,
                'ends_at' => $legacySubscription->ends_at,
                'external_id' => $legacySubscription->external_id,
                'gateway' => $legacySubscription->gateway,
                'gateway_customer_id' => $legacySubscription->gateway_customer_id,
                'gateway_subscription_id' => $legacySubscription->gateway_subscription_id,
                'gateway_checkout_session_id' => $legacySubscription->gateway_checkout_session_id,
                'gateway_price_id' => $legacySubscription->gateway_price_id,
            ]
        );
    }
}
