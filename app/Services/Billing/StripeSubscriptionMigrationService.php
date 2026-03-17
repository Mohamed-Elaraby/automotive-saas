<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Collection;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Throwable;

class StripeSubscriptionMigrationService
{
    protected StripeClient $stripe;

    public function __construct(
        protected StripeSubscriptionSyncService $stripeSubscriptionSyncService
    ) {
        $secret = (string) config('billing.gateways.stripe.secret');

        if ($secret === '') {
            throw new \RuntimeException('Stripe secret key is not configured.');
        }

$this->stripe = new StripeClient($secret);
}

public function migrate(bool $apply = false, ?string $tenantId = null): Collection
{
    $query = Subscription::query()
        ->where('gateway', 'stripe')
        ->whereNotNull('plan_id')
        ->whereNotNull('gateway_subscription_id')
        ->orderBy('id');

    if ($tenantId) {
        $query->where('tenant_id', $tenantId);
    }

    return $query->get()->map(function (Subscription $subscription) use ($apply) {
        return $this->migrateSingleSubscription($subscription, $apply);
    });
}

protected function migrateSingleSubscription(Subscription $subscription, bool $apply): array
{
    $gatewaySubscriptionId = (string) $subscription->gateway_subscription_id;

    if ($this->isPlaceholderSubscriptionId($gatewaySubscriptionId)) {
        return [
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'plan_id' => $subscription->plan_id,
            'status' => $subscription->status,
            'old_price_id' => $subscription->gateway_price_id ?: '-',
            'expected_price_id' => $this->expectedPriceId($subscription),
            'new_price_id' => $subscription->gateway_price_id ?: '-',
            'stripe_subscription_id' => $gatewaySubscriptionId,
            'action' => 'SKIPPED_PLACEHOLDER',
            'migrated' => false,
            'message' => 'Skipped placeholder/test Stripe subscription id.',
        ];
    }

    $expectedPriceId = $this->expectedPriceId($subscription);

    if (! $expectedPriceId) {
        return [
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'plan_id' => $subscription->plan_id,
            'status' => $subscription->status,
            'old_price_id' => $subscription->gateway_price_id ?: '-',
            'expected_price_id' => '-',
            'new_price_id' => $subscription->gateway_price_id ?: '-',
            'stripe_subscription_id' => $gatewaySubscriptionId,
            'action' => 'SKIPPED_NO_EXPECTED_PRICE',
            'migrated' => false,
            'message' => 'The local plan does not have a Stripe price id.',
        ];
    }

    if ($subscription->gateway_price_id === $expectedPriceId) {
        return [
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'plan_id' => $subscription->plan_id,
            'status' => $subscription->status,
            'old_price_id' => $subscription->gateway_price_id ?: '-',
            'expected_price_id' => $expectedPriceId,
            'new_price_id' => $subscription->gateway_price_id ?: '-',
            'stripe_subscription_id' => $gatewaySubscriptionId,
            'action' => 'ALREADY_ALIGNED',
            'migrated' => false,
            'message' => 'Subscription already points to the correct Stripe price.',
        ];
    }

    try {
        $stripeSubscription = $this->stripe->subscriptions->retrieve($gatewaySubscriptionId, []);

        $currentItemId = $stripeSubscription->items->data[0]->id ?? null;
        $currentPriceId = $stripeSubscription->items->data[0]->price->id ?? null;

        if (! $currentItemId) {
            return [
                'subscription_id' => $subscription->id,
                'tenant_id' => $subscription->tenant_id,
                'plan_id' => $subscription->plan_id,
                'status' => $subscription->status,
                'old_price_id' => $subscription->gateway_price_id ?: '-',
                'expected_price_id' => $expectedPriceId,
                'new_price_id' => $subscription->gateway_price_id ?: '-',
                'stripe_subscription_id' => $gatewaySubscriptionId,
                'action' => 'FAILED_NO_ITEM',
                'migrated' => false,
                'message' => 'Stripe subscription item id could not be determined.',
            ];
        }

        if (! $apply) {
            return [
                'subscription_id' => $subscription->id,
                'tenant_id' => $subscription->tenant_id,
                'plan_id' => $subscription->plan_id,
                'status' => $subscription->status,
                'old_price_id' => $currentPriceId ?: ($subscription->gateway_price_id ?: '-'),
                'expected_price_id' => $expectedPriceId,
                'new_price_id' => $expectedPriceId,
                'stripe_subscription_id' => $gatewaySubscriptionId,
                'action' => 'WOULD_MIGRATE',
                'migrated' => false,
                'message' => 'Dry-run only. Subscription would be migrated to the expected Stripe price.',
            ];
        }

        $updated = $this->stripe->subscriptions->update($gatewaySubscriptionId, [
                'items' => [
                    [
                        'id' => $currentItemId,
                        'price' => $expectedPriceId,
                    ],
                ],
                'proration_behavior' => 'none',
                'metadata' => array_merge(
                    is_array($stripeSubscription->metadata?->toArray() ?? null)
                    ? $stripeSubscription->metadata->toArray()
                    : [],
                [
                    'local_subscription_id' => (string) $subscription->id,
                    'local_plan_id' => (string) $subscription->plan_id,
                    'migrated_by' => 'billing_step_7c',
                ]
                ),
            ]);

            $fresh = $this->stripeSubscriptionSyncService
                ->syncFromStripePayload($subscription->fresh(), $updated);

            return [
                'subscription_id' => $subscription->id,
                'tenant_id' => $subscription->tenant_id,
                'plan_id' => $subscription->plan_id,
                'status' => $fresh->status,
                'old_price_id' => $currentPriceId ?: ($subscription->gateway_price_id ?: '-'),
                'expected_price_id' => $expectedPriceId,
                'new_price_id' => $fresh->gateway_price_id ?: '-',
                'stripe_subscription_id' => $gatewaySubscriptionId,
                'action' => 'MIGRATED',
                'migrated' => true,
                'message' => 'Stripe subscription price migrated successfully and local subscription synced.',
            ];
        } catch (ApiErrorException $e) {
        return [
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'plan_id' => $subscription->plan_id,
            'status' => $subscription->status,
            'old_price_id' => $subscription->gateway_price_id ?: '-',
            'expected_price_id' => $expectedPriceId,
            'new_price_id' => $subscription->gateway_price_id ?: '-',
            'stripe_subscription_id' => $gatewaySubscriptionId,
            'action' => 'FAILED_STRIPE_API',
            'migrated' => false,
            'message' => 'Stripe API error: ' . $e->getMessage(),
        ];
    } catch (Throwable $e) {
        return [
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'plan_id' => $subscription->plan_id,
            'status' => $subscription->status,
            'old_price_id' => $subscription->gateway_price_id ?: '-',
            'expected_price_id' => $expectedPriceId,
            'new_price_id' => $subscription->gateway_price_id ?: '-',
            'stripe_subscription_id' => $gatewaySubscriptionId,
            'action' => 'FAILED_UNEXPECTED',
            'migrated' => false,
            'message' => 'Unexpected error: ' . $e->getMessage(),
        ];
    }
}

protected function expectedPriceId(Subscription $subscription): ?string
{
    $plan = Plan::query()->find($subscription->plan_id);

    return $plan?->stripe_price_id;
    }

protected function isPlaceholderSubscriptionId(string $subscriptionId): bool
{
    return str_starts_with($subscriptionId, 'sub_test_')
        || str_starts_with($subscriptionId, 'sub_webhook_test_');
}
}
