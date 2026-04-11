<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\Product;
use App\Models\TenantProductSubscription;
use App\Support\Billing\SubscriptionStatuses;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Throwable;

class StripeTenantProductSubscriptionPlanChangeService
{
    public function __construct(
        protected StripeWebhookSyncService $stripeWebhookSyncService
    ) {
    }

    public function changePlan(TenantProductSubscription $subscription, Plan $targetPlan, ?int $prorationDate = null): array
    {
        if ($subscription->gateway !== 'stripe') {
            return [
                'ok' => false,
                'message' => 'This product subscription is not linked to the Stripe gateway.',
            ];
        }

        if (! $subscription->gateway_subscription_id) {
            return [
                'ok' => false,
                'message' => 'No Stripe subscription ID is linked to this product subscription.',
            ];
        }

        if (! $this->isEligibleForPlanChange($subscription)) {
            return [
                'ok' => false,
                'message' => 'This product subscription is not eligible for in-place plan change right now.',
            ];
        }

        if (! $targetPlan->is_active) {
            return [
                'ok' => false,
                'message' => 'The selected plan is inactive.',
            ];
        }

        if ($targetPlan->billing_period === 'trial') {
            return [
                'ok' => false,
                'message' => 'Trial plans cannot replace a live Stripe subscription.',
            ];
        }

        if (! $targetPlan->stripe_price_id) {
            return [
                'ok' => false,
                'message' => 'The selected plan is not linked to a Stripe price yet.',
            ];
        }

        if (
            (int) $subscription->plan_id === (int) $targetPlan->id
            && (string) $subscription->gateway_price_id === (string) $targetPlan->stripe_price_id
        ) {
            return [
                'ok' => false,
                'message' => 'The product subscription is already on this plan.',
            ];
        }

        $stripe = new StripeClient($this->stripeSecret());

        try {
            $stripeSubscription = $stripe->subscriptions->retrieve(
                $subscription->gateway_subscription_id,
                []
            );

            $items = $stripeSubscription->items->data ?? [];

            if (count($items) !== 1) {
                return [
                    'ok' => false,
                    'message' => 'This Stripe subscription does not have the expected single subscription item structure.',
                ];
            }

            $itemId = $items[0]->id ?? null;

            if (! $itemId) {
                return [
                    'ok' => false,
                    'message' => 'Unable to locate the current Stripe subscription item.',
                ];
            }

            $product = Product::query()->find($subscription->product_id);

            $updatePayload = [
                'items' => [[
                    'id' => $itemId,
                    'price' => $targetPlan->stripe_price_id,
                ]],
                'proration_behavior' => 'create_prorations',
                'metadata' => array_merge(
                    $this->normalizeMetadata($stripeSubscription->metadata ?? null),
                    [
                        'tenant_id' => (string) $subscription->tenant_id,
                        'tenant_product_subscription_id' => (string) $subscription->id,
                        'plan_id' => (string) $targetPlan->id,
                        'product_scope' => (string) ($product?->code ?? ''),
                    ]
                ),
            ];

            if (! empty($prorationDate)) {
                $updatePayload['proration_date'] = (int) $prorationDate;
            }

            $updatedStripeSubscription = $stripe->subscriptions->update(
                $subscription->gateway_subscription_id,
                $updatePayload
            );

            $subscription->fill([
                'plan_id' => $targetPlan->id,
                'gateway_price_id' => $targetPlan->stripe_price_id,
            ])->save();

            $event = (object) [
                'type' => 'customer.subscription.updated',
                'data' => (object) [
                    'object' => $updatedStripeSubscription,
                ],
            ];

            $this->stripeWebhookSyncService->handleEvent($event);

            return [
                'ok' => true,
                'message' => 'Product subscription plan changed successfully.',
                'subscription' => $subscription->fresh(),
                'stripe_subscription_id' => $updatedStripeSubscription->id ?? null,
                'new_price_id' => $targetPlan->stripe_price_id,
            ];
        } catch (ApiErrorException $e) {
            return [
                'ok' => false,
                'message' => 'Stripe rejected the plan change request: ' . $e->getMessage(),
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'ok' => false,
                'message' => 'Unable to change the product subscription plan right now.',
            ];
        }
    }

    protected function isEligibleForPlanChange(TenantProductSubscription $subscription): bool
    {
        $status = (string) $subscription->status;

        if ($status === SubscriptionStatuses::ACTIVE) {
            return true;
        }

        if ($status === SubscriptionStatuses::CANCELLED) {
            return $subscription->ends_at !== null && $subscription->ends_at->isFuture();
        }

        return false;
    }

    protected function normalizeMetadata(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $this->stringifyMetadataValues($metadata);
        }

        if (is_object($metadata) && method_exists($metadata, 'toArray')) {
            $array = $metadata->toArray();

            return is_array($array)
                ? $this->stringifyMetadataValues($array)
                : [];
        }

        return [];
    }

    protected function stringifyMetadataValues(array $metadata): array
    {
        $normalized = [];

        foreach ($metadata as $key => $value) {
            if (! is_scalar($value) && $value !== null) {
                continue;
            }

            $normalized[(string) $key] = $value === null ? '' : (string) $value;
        }

        return $normalized;
    }

    protected function stripeSecret(): string
    {
        return trim((string) config('billing.gateways.stripe.secret'));
    }
}
