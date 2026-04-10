<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\TenantProductSubscription;
use App\Support\Billing\SubscriptionStatuses;
use Carbon\Carbon;
use RuntimeException;
use Stripe\Collection;
use Stripe\StripeClient;

class AdminTenantProductSubscriptionStripeSyncService
{
    public function __construct(
        protected StripeSubscriptionSyncService $stripeSubscriptionSyncService,
        protected TenantProductSubscriptionSyncService $tenantProductSubscriptionSyncService
    ) {
    }

    public function sync(TenantProductSubscription $subscription): TenantProductSubscription
    {
        if (! $this->isStripeLinked($subscription)) {
            throw new RuntimeException('This product subscription is not linked to the Stripe gateway.');
        }

        if ($subscription->legacy_subscription_id) {
            $legacySubscription = $subscription->legacySubscription;

            if (! $legacySubscription) {
                throw new RuntimeException('The linked legacy subscription record could not be found.');
            }

            $syncedLegacy = $this->stripeSubscriptionSyncService->syncLocalStripeSubscription($legacySubscription);

            if (! $syncedLegacy) {
                throw new RuntimeException('Unable to resolve a live Stripe subscription ID for this product subscription from the stored checkout/customer data.');
            }

            $this->tenantProductSubscriptionSyncService->syncFromLegacySubscription($syncedLegacy);

            return TenantProductSubscription::query()->findOrFail($subscription->id);
        }

        $gatewaySubscriptionId = $this->resolveGatewaySubscriptionId($subscription);

        if ($gatewaySubscriptionId === '') {
            throw new RuntimeException('No Stripe subscription ID could be resolved for this product subscription.');
        }

        $stripeSubscription = $this->client()
            ->subscriptions
            ->retrieve($gatewaySubscriptionId, []);

        return $this->syncFromStripePayload(
            $subscription->fresh(),
            is_array($stripeSubscription) ? $stripeSubscription : $stripeSubscription->toArray()
        );
    }

    protected function syncFromStripePayload(TenantProductSubscription $subscription, array $stripeSubscription): TenantProductSubscription
    {
        $mappedStatus = $this->mapStripeStatus((string) ($stripeSubscription['status'] ?? ''));
        $currentPeriodEnd = $this->timestampToCarbon($stripeSubscription['current_period_end'] ?? null);
        $trialEndsAt = $this->timestampToCarbon($stripeSubscription['trial_end'] ?? null);
        $cancelledAt = $this->timestampToCarbon($stripeSubscription['canceled_at'] ?? null);
        $cancelAtPeriodEnd = (bool) ($stripeSubscription['cancel_at_period_end'] ?? false);
        $gatewayPriceId = (string) ($stripeSubscription['items']['data'][0]['price']['id'] ?? '');
        $resolvedPlanId = $this->resolveLocalPlanId($stripeSubscription, $gatewayPriceId !== '' ? $gatewayPriceId : null);

        $subscription->fill([
            'gateway' => 'stripe',
            'gateway_customer_id' => (string) ($stripeSubscription['customer'] ?? '') ?: $subscription->gateway_customer_id,
            'gateway_subscription_id' => (string) ($stripeSubscription['id'] ?? '') ?: $subscription->gateway_subscription_id,
        ]);

        if ($gatewayPriceId !== '') {
            $subscription->gateway_price_id = $gatewayPriceId;
        }

        if ($resolvedPlanId !== null) {
            $subscription->plan_id = $resolvedPlanId;
        }

        if ($trialEndsAt) {
            $subscription->trial_ends_at = $trialEndsAt;
        }

        $subscription->save();

        if ($mappedStatus === SubscriptionStatuses::ACTIVE) {
            $subscription = $this->markRecovered($subscription);

            if ($cancelAtPeriodEnd && $currentPeriodEnd && $currentPeriodEnd->isFuture()) {
                return $this->markCancelled($subscription, $cancelledAt, $currentPeriodEnd);
            }

            return $subscription->fresh();
        }

        if ($mappedStatus === SubscriptionStatuses::TRIALING) {
            $subscription->update([
                'status' => SubscriptionStatuses::TRIALING,
                'trial_ends_at' => $trialEndsAt,
                'last_payment_failed_at' => null,
                'past_due_started_at' => null,
                'grace_ends_at' => null,
                'suspended_at' => null,
                'payment_failures_count' => 0,
            ]);

            return $subscription->fresh();
        }

        if ($mappedStatus === SubscriptionStatuses::PAST_DUE) {
            return $this->markPastDue($subscription);
        }

        if ($mappedStatus === SubscriptionStatuses::SUSPENDED) {
            return $this->markSuspended($subscription);
        }

        if ($mappedStatus === SubscriptionStatuses::EXPIRED) {
            if ($currentPeriodEnd && $currentPeriodEnd->isFuture()) {
                return $this->markCancelled($subscription, $cancelledAt, $currentPeriodEnd);
            }

            return $this->markExpired($subscription, $currentPeriodEnd ?? $cancelledAt);
        }

        return $subscription->fresh();
    }

    protected function isStripeLinked(TenantProductSubscription $subscription): bool
    {
        return ($subscription->gateway ?? null) === 'stripe'
            || filled($subscription->gateway_subscription_id)
            || filled($subscription->gateway_customer_id)
            || filled($subscription->gateway_checkout_session_id);
    }

    protected function resolveGatewaySubscriptionId(TenantProductSubscription $subscription): string
    {
        $gatewaySubscriptionId = (string) ($subscription->gateway_subscription_id ?? '');

        if ($gatewaySubscriptionId !== '') {
            return $gatewaySubscriptionId;
        }

        $resolved = $this->resolveFromCheckoutSession($subscription);

        if (($resolved['gateway_subscription_id'] ?? '') === '') {
            $resolved = $this->resolveFromCustomerSubscriptions($subscription);
        }

        if (($resolved['gateway_subscription_id'] ?? '') === '') {
            return '';
        }

        $subscription->fill([
            'gateway' => 'stripe',
            'gateway_subscription_id' => $resolved['gateway_subscription_id'],
            'gateway_customer_id' => $resolved['gateway_customer_id'] ?: $subscription->gateway_customer_id,
        ])->save();

        return (string) $subscription->gateway_subscription_id;
    }

    protected function resolveFromCheckoutSession(TenantProductSubscription $subscription): array
    {
        $checkoutSessionId = (string) ($subscription->gateway_checkout_session_id ?? '');

        if ($checkoutSessionId === '') {
            return [];
        }

        $session = $this->client()->checkout->sessions->retrieve($checkoutSessionId, []);

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

    protected function resolveFromCustomerSubscriptions(TenantProductSubscription $subscription): array
    {
        $gatewayCustomerId = (string) ($subscription->gateway_customer_id ?? '');

        if ($gatewayCustomerId === '') {
            return [];
        }

        /** @var Collection $subscriptions */
        $subscriptions = $this->client()->subscriptions->all([
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

    protected function resolveLocalPlanId(array $payload, ?string $gatewayPriceId): ?int
    {
        $metadataPlanId = (int) ($payload['metadata']['plan_id'] ?? 0);

        if ($metadataPlanId > 0 && Plan::query()->whereKey($metadataPlanId)->exists()) {
            return $metadataPlanId;
        }

        if ($gatewayPriceId !== null && $gatewayPriceId !== '') {
            $plan = Plan::query()->where('stripe_price_id', $gatewayPriceId)->first();

            if ($plan) {
                return (int) $plan->id;
            }
        }

        return null;
    }

    protected function mapStripeStatus(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'active' => SubscriptionStatuses::ACTIVE,
            'trialing' => SubscriptionStatuses::TRIALING,
            'past_due', 'unpaid', 'incomplete' => SubscriptionStatuses::PAST_DUE,
            'paused' => SubscriptionStatuses::SUSPENDED,
            'canceled', 'incomplete_expired' => SubscriptionStatuses::EXPIRED,
            default => SubscriptionStatuses::PAST_DUE,
        };
    }

    protected function markPastDue(TenantProductSubscription $subscription): TenantProductSubscription
    {
        $failedAt = now();
        $graceDays = (int) config('billing.grace_period_days', 3);

        $subscription->update([
            'status' => SubscriptionStatuses::PAST_DUE,
            'last_payment_failed_at' => $failedAt,
            'past_due_started_at' => $subscription->past_due_started_at ?? $failedAt,
            'grace_ends_at' => (clone $failedAt)->addDays($graceDays),
            'suspended_at' => null,
            'payment_failures_count' => ((int) ($subscription->payment_failures_count ?? 0)) + 1,
        ]);

        return $subscription->fresh();
    }

    protected function markRecovered(TenantProductSubscription $subscription): TenantProductSubscription
    {
        $subscription->update([
            'status' => SubscriptionStatuses::ACTIVE,
            'last_payment_failed_at' => null,
            'past_due_started_at' => null,
            'grace_ends_at' => null,
            'suspended_at' => null,
            'payment_failures_count' => 0,
            'cancelled_at' => null,
        ]);

        return $subscription->fresh();
    }

    protected function markSuspended(TenantProductSubscription $subscription): TenantProductSubscription
    {
        $subscription->update([
            'status' => SubscriptionStatuses::SUSPENDED,
            'suspended_at' => $subscription->suspended_at ?? now(),
        ]);

        return $subscription->fresh();
    }

    protected function markExpired(TenantProductSubscription $subscription, ?Carbon $expiredAt = null): TenantProductSubscription
    {
        $expiredAt ??= now();

        $subscription->update([
            'status' => SubscriptionStatuses::EXPIRED,
            'ends_at' => $subscription->ends_at ?? $expiredAt,
            'suspended_at' => $subscription->suspended_at ?? $expiredAt,
        ]);

        return $subscription->fresh();
    }

    protected function markCancelled(
        TenantProductSubscription $subscription,
        ?Carbon $cancelledAt = null,
        ?Carbon $endsAt = null
    ): TenantProductSubscription {
        $subscription->update([
            'status' => SubscriptionStatuses::CANCELLED,
            'cancelled_at' => $cancelledAt ?? now(),
            'ends_at' => $endsAt ?? $subscription->ends_at,
        ]);

        return $subscription->fresh();
    }

    protected function timestampToCarbon(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp((int) $value);
        }

        return Carbon::parse((string) $value);
    }

    protected function stripeSecret(): string
    {
        $secret = trim((string) config('billing.gateways.stripe.secret'));

        if ($secret === '') {
            throw new RuntimeException('Stripe secret key is not configured.');
        }

        return $secret;
    }

    protected function client(): StripeClient
    {
        return new StripeClient($this->stripeSecret());
    }
}
