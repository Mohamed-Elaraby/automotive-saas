<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Throwable;

class StripeSubscriptionPlanChangeService
{
    public function __construct(
        protected StripeSubscriptionSyncService $stripeSubscriptionSyncService
    ) {
    }

public function changePlan(Subscription $subscription, Plan $targetPlan): array
{
    if ($subscription->gateway !== 'stripe') {
        return [
            'ok' => false,
            'message' => 'This subscription is not linked to the Stripe gateway.',
        ];
    }

    if (! $subscription->gateway_subscription_id) {
        return [
            'ok' => false,
            'message' => 'No Stripe subscription ID is linked to this subscription.',
        ];
    }

    if (! $targetPlan->stripe_price_id) {
        return [
            'ok' => false,
            'message' => 'The selected plan is not linked to a Stripe price yet.',
        ];
    }

    if ((int) $subscription->plan_id === (int) $targetPlan->id
        && $subscription->gateway_price_id === $targetPlan->stripe_price_id) {
        return [
            'ok' => false,
            'message' => 'The subscription is already on this plan.',
        ];
    }

    $stripe = new StripeClient(config('services.stripe.secret'));

    try {
        $stripeSubscription = $stripe->subscriptions->retrieve(
            $subscription->gateway_subscription_id,
            []
        );

        $itemId = $stripeSubscription->items->data[0]->id ?? null;

        if (! $itemId) {
            return [
                'ok' => false,
                'message' => 'Unable to locate the current Stripe subscription item.',
            ];
        }

        $updatedStripeSubscription = $stripe->subscriptions->update(
            $subscription->gateway_subscription_id,
            [
                'items' => [
                    [
                        'id' => $itemId,
                        'price' => $targetPlan->stripe_price_id,
                    ],
                ],
                'proration_behavior' => 'create_prorations',
                'metadata' => array_merge(
                    (array) ($stripeSubscription->metadata ?? []),
                    [
                        'plan_id' => (string) $targetPlan->id,
                    ]
                ),
            ]
        );

        DB::connection($this->centralConnection())->table('subscriptions')
            ->where('id', $subscription->id)
            ->update([
                'plan_id' => $targetPlan->id,
                'gateway_price_id' => $targetPlan->stripe_price_id,
                'updated_at' => now(),
            ]);

        $fresh = Subscription::query()->findOrFail($subscription->id);

        $fresh = $this->stripeSubscriptionSyncService->syncFromStripePayload(
            $fresh,
            $updatedStripeSubscription
        );

        return [
            'ok' => true,
            'message' => 'Subscription plan changed successfully.',
            'subscription' => $fresh,
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
            'message' => 'Unable to change the subscription plan right now.',
        ];
    }
}

protected function centralConnection(): string
{
    return (string) (config('tenancy.database.central_connection') ?? config('database.default'));
}
}
