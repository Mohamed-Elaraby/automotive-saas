<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use RuntimeException;
use Stripe\Collection;
use Stripe\StripeClient;

class StripeSubscriptionSyncService
{
    public function __construct(
        protected TenantBillingLifecycleService $billingLifecycleService,
        protected TenantProductSubscriptionSyncService $tenantProductSubscriptionSyncService
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

        $stripeSubscription = $this->client()
            ->subscriptions
            ->retrieve($gatewaySubscriptionId, []);

        return $this->syncFromStripePayload($subscription, $stripeSubscription);
    }

    public function syncLocalStripeSubscription(Subscription $subscription): ?Subscription
    {
        if (($subscription->gateway ?? null) !== 'stripe') {
            return null;
        }

        $gatewaySubscriptionId = (string) ($subscription->gateway_subscription_id ?? '');

        if ($gatewaySubscriptionId === '') {
            $resolvedIds = $this->resolveMissingGatewaySubscriptionId($subscription);

            if (! empty($resolvedIds['gateway_subscription_id'])) {
                $subscription->fill([
                    'gateway' => 'stripe',
                    'gateway_subscription_id' => $resolvedIds['gateway_subscription_id'],
                    'gateway_customer_id' => $resolvedIds['gateway_customer_id'] ?: $subscription->gateway_customer_id,
                ]);
                $subscription->save();

                $gatewaySubscriptionId = (string) $subscription->gateway_subscription_id;
            }
        }

        if ($gatewaySubscriptionId === '') {
            return null;
        }

        return $this->syncByGatewaySubscriptionId($gatewaySubscriptionId);
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
            'active' => $this->markPaidActive($subscription),
            'trialing' => $this->markTrialing($subscription),
            'past_due' => $this->billingLifecycleService->markAsPastDue($subscription),
            'expired' => $this->billingLifecycleService->markAsExpired($subscription),
            'suspended' => $this->billingLifecycleService->markAsSuspended($subscription),
            default => null,
        };

        $fresh = Subscription::query()->findOrFail($subscription->id);
        $this->tenantProductSubscriptionSyncService->syncFromLegacySubscription($fresh);

        return $fresh;
    }

    protected function markPaidActive(Subscription $subscription): Subscription
    {
        $this->billingLifecycleService->markAsRecovered($subscription);

        $subscription->refresh();

        $subscription->fill([
            'status' => 'active',
            'trial_ends_at' => null,
            'grace_ends_at' => null,
            'last_payment_failed_at' => null,
            'past_due_started_at' => null,
            'suspended_at' => null,
            'cancelled_at' => null,
        ]);

        $subscription->save();

        return $subscription;
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

    protected function client(): object
    {
        return new StripeClient($this->stripeSecret());
    }

    protected function resolveMissingGatewaySubscriptionId(Subscription $subscription): array
    {
        $stripe = $this->client();

        $fromCheckout = $this->resolveFromCheckoutSession($stripe, $subscription);
        if (! empty($fromCheckout['gateway_subscription_id'])) {
            return $fromCheckout;
        }

        return $this->resolveFromCustomerSubscriptions($stripe, $subscription);
    }

    protected function resolveFromCheckoutSession(object $stripe, Subscription $subscription): array
    {
        $checkoutSessionId = (string) ($subscription->gateway_checkout_session_id ?? '');

        if ($checkoutSessionId === '') {
            return [];
        }

        $session = $stripe->checkout->sessions->retrieve($checkoutSessionId, []);

        $gatewaySubscriptionId = (string) ($session->subscription ?? '');
        $gatewayCustomerId = (string) ($session->customer ?? '');

        if ($gatewaySubscriptionId === '') {
            return [];
        }

        return [
            'gateway_subscription_id' => $gatewaySubscriptionId,
            'gateway_customer_id' => $gatewayCustomerId,
        ];
    }

    protected function resolveFromCustomerSubscriptions(object $stripe, Subscription $subscription): array
    {
        $gatewayCustomerId = (string) ($subscription->gateway_customer_id ?? '');

        if ($gatewayCustomerId === '') {
            return [];
        }

        /** @var Collection $subscriptions */
        $subscriptions = $stripe->subscriptions->all([
            'customer' => $gatewayCustomerId,
            'status' => 'all',
            'limit' => 3,
        ]);

        $items = $subscriptions->data ?? [];

        if (count($items) !== 1) {
            return [];
        }

        return [
            'gateway_subscription_id' => (string) ($items[0]->id ?? ''),
            'gateway_customer_id' => $gatewayCustomerId,
        ];
    }
}
