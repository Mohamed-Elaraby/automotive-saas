<?php

namespace App\Services\Billing;

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

        $subscription->fill([
            'gateway_customer_id' => $payload['customer'] ?? $subscription->gateway_customer_id,
        ]);

        if (! empty($payload['items']['data'][0]['price']['id'])) {
            $subscription->gateway_price_id = $payload['items']['data'][0]['price']['id'];
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
