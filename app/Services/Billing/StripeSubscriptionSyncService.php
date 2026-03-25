<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use RuntimeException;
use Stripe\StripeClient;

class StripeSubscriptionSyncService
{
    public function __construct(
        protected TenantBillingLifecycleService $billingLifecycleService
    ) {
    }

public function syncByGatewaySubscriptionId(string $gatewaySubscriptionId): ?Subscription
{
    $subscription = Subscription::query()
        ->where('gateway', 'stripe')
        ->where('gateway_subscription_id', $gatewaySubscriptionId)
        ->first();

    if (! $subscription) {
        return null;
    }

    $stripe = new StripeClient($this->stripeSecret());
    $stripeSubscription = $stripe->subscriptions->retrieve($gatewaySubscriptionId, []);

    return $this->syncFromStripePayload($subscription, $stripeSubscription);
}

public function syncFromStripePayload(Subscription $subscription, object|array $stripeSubscription): Subscription
    {
        $payload = is_array($stripeSubscription) ? $stripeSubscription : $stripeSubscription->toArray();
        $stripeStatus = $payload['status'] ?? null;
        $mappedStatus = $this->mapStripeStatus($stripeStatus);

        $gatewaySubscriptionId = (string) ($payload['id'] ?? '');
        $gatewayCustomerId = (string) ($payload['customer'] ?? '');

        $gatewayPriceId = null;
        if (! empty($payload['items']['data'][0]['price']['id'])) {
            $gatewayPriceId = (string) $payload['items']['data'][0]['price']['id'];
        }

        $resolvedPlanId = $this->resolveLocalPlanId($payload, $gatewayPriceId);

        $subscription->fill([
            'gateway' => 'stripe',
            'gateway_customer_id' => $gatewayCustomerId !== '' ? $gatewayCustomerId : $subscription->gateway_customer_id,
            'gateway_subscription_id' => $gatewaySubscriptionId !== '' ? $gatewaySubscriptionId : $subscription->gateway_subscription_id,
        ]);

        if ($gatewayPriceId !== null && $gatewayPriceId !== '') {
            $subscription->gateway_price_id = $gatewayPriceId;
        }

        if ($resolvedPlanId !== null) {
            $subscription->plan_id = $resolvedPlanId;
        }

        $subscription->save();

        match ($mappedStatus) {
        'active' => $this->billingLifecycleService->markAsRecovered($subscription),
            'trialing' => $this->markTrialing($subscription),
            'past_due' => $this->billingLifecycleService->markAsPastDue($subscription),
            'expired' => $this->billingLifecycleService->markAsExpired($subscription),
            'suspended' => $this->billingLifecycleService->markAsSuspended($subscription),
            default => null,
        };

        return Subscription::query()->findOrFail($subscription->id);
    }

    protected function resolveLocalPlanId(array $payload, ?string $gatewayPriceId): ?int
{
    $metadataPlanId = (int) ($payload['metadata']['plan_id'] ?? 0);

    if ($metadataPlanId > 0 && Plan::query()->whereKey($metadataPlanId)->exists()) {
        return $metadataPlanId;
    }

    if ($gatewayPriceId !== null && $gatewayPriceId !== '') {
        $plan = Plan::query()
            ->where('stripe_price_id', $gatewayPriceId)
            ->first();

        if ($plan) {
            return (int) $plan->id;
        }
    }

    return null;
}

    protected function mapStripeStatus(?string $stripeStatus): string
{
    return match ($stripeStatus) {
    'active' => 'active',
            'trialing' => 'trialing',
            'past_due', 'unpaid' => 'past_due',
            'canceled', 'incomplete_expired' => 'expired',
            'incomplete' => 'past_due',
            'paused' => 'suspended',
            default => 'past_due',
        };
    }

    protected function markTrialing(Subscription $subscription): Subscription
{
    $subscription->fill([
        'status' => 'trialing',
        'last_payment_failed_at' => null,
        'past_due_started_at' => null,
        'grace_ends_at' => null,
        'suspended_at' => null,
        'payment_failures_count' => 0,
    ]);

    $subscription->save();

    return $subscription;
}

    protected function stripeSecret(): string
{
    $secret = trim((string) config('billing.gateways.stripe.secret'));

    if ($secret === '') {
        throw new RuntimeException('Stripe secret key is not configured.');
    }

    return $secret;
}
}
