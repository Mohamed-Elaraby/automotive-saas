<?php

namespace App\Services\Billing;

use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Throwable;
use App\Support\Billing\SubscriptionStatuses;

class StripeSubscriptionManagementService
{
    protected StripeClient $stripe;

    public function __construct(
        protected StripeWebhookSyncService $stripeWebhookSyncService
    ) {
        $secret = (string) config('billing.gateways.stripe.secret');

        if ($secret === '') {
            throw new \RuntimeException('Stripe secret key is not configured.');
        }

$this->stripe = new StripeClient($secret);
}

public function cancelAtPeriodEnd(?object $subscription): array
{
    if (! $subscription || empty($subscription->gateway_subscription_id)) {
        return [
            'success' => false,
            'message' => 'No live Stripe subscription is linked to this tenant.',
        ];
    }

    try {
        $updated = $this->stripe->subscriptions->update(
            (string) $subscription->gateway_subscription_id,
            [
                'cancel_at_period_end' => true,
            ]
        );

        $event = (object) [
            'type' => 'customer.subscription.updated',
            'data' => (object) [
                'object' => $updated,
            ],
        ];

        $this->stripeWebhookSyncService->handleEvent($event);

        return [
            'success' => true,
            'message' => 'Subscription cancellation has been scheduled for the end of the current billing period.',
        ];
    } catch (ApiErrorException $e) {
        return [
            'success' => false,
            'message' => 'Stripe API error while scheduling cancellation: ' . $e->getMessage(),
        ];
    } catch (Throwable $e) {
        return [
            'success' => false,
            'message' => 'Unable to schedule cancellation right now. Please try again later.',
        ];
    }
}

public function resume(?object $subscription): array
{
    if (! $subscription || empty($subscription->gateway_subscription_id)) {
        return [
            'success' => false,
            'message' => 'No live Stripe subscription is linked to this tenant.',
        ];
    }

    if (! $this->canResume($subscription)) {
        return [
            'success' => false,
            'message' => 'This Stripe subscription can no longer be resumed. Only subscriptions still active for the current period can be resumed.',
        ];
    }

    try {
        $updated = $this->stripe->subscriptions->update(
            (string) $subscription->gateway_subscription_id,
            [
                'cancel_at_period_end' => false,
            ]
        );

        $event = (object) [
            'type' => 'customer.subscription.updated',
            'data' => (object) [
                'object' => $updated,
            ],
        ];

        $this->stripeWebhookSyncService->handleEvent($event);

        return [
            'success' => true,
            'message' => 'Subscription cancellation was removed and the subscription remains active.',
        ];
    } catch (ApiErrorException $e) {
        $message = $e->getMessage();

        if (str_contains(strtolower($message), 'a canceled subscription can only update its cancellation_details and metadata')) {
            $message = 'This Stripe subscription has already been cancelled permanently and can no longer be resumed.';
        }

        return [
            'success' => false,
            'message' => 'Stripe API error while resuming subscription: ' . $message,
        ];
    } catch (Throwable $e) {
        return [
            'success' => false,
            'message' => 'Unable to resume the subscription right now. Please try again later.',
        ];
    }
}

public function cancelImmediately(?object $subscription): array
{
    if (! $subscription || empty($subscription->gateway_subscription_id)) {
        return [
            'success' => false,
            'message' => 'No live Stripe subscription is linked to this tenant.',
        ];
    }

    if (! $this->canCancelImmediately($subscription)) {
        return [
            'success' => false,
            'message' => 'This Stripe subscription is already in a terminal cancelled state and cannot be cancelled again.',
        ];
    }

    try {
        $cancelled = $this->stripe->subscriptions->cancel(
            (string) $subscription->gateway_subscription_id,
            []
        );

        $event = (object) [
            'type' => 'customer.subscription.deleted',
            'data' => (object) [
                'object' => $cancelled,
            ],
        ];

        $this->stripeWebhookSyncService->handleEvent($event);

        return [
            'success' => true,
            'message' => 'Subscription was cancelled immediately on Stripe.',
        ];
    } catch (ApiErrorException $e) {
        $message = $e->getMessage();

        if (str_contains(strtolower($message), 'no such subscription')) {
            $message = 'This Stripe subscription no longer exists on Stripe. It may have already been cancelled permanently.';
        }

        return [
            'success' => false,
            'message' => 'Stripe API error while cancelling subscription immediately: ' . $message,
        ];
    } catch (Throwable $e) {
        return [
            'success' => false,
            'message' => 'Unable to cancel the subscription immediately right now. Please try again later.',
        ];
    }
}

protected function canResume(object $subscription): bool
{
    $status = (string) ($subscription->status ?? '');

    if ($status === SubscriptionStatuses::ACTIVE) {
        return true;
    }

    if ($status !== SubscriptionStatuses::CANCELLED) {
        return false;
    }

    if (empty($subscription->ends_at)) {
        return false;
    }

    return now()->lt(\Carbon\Carbon::parse((string) $subscription->ends_at));
}

protected function canCancelImmediately(object $subscription): bool
{
    return ! in_array((string) ($subscription->status ?? ''), [
        SubscriptionStatuses::EXPIRED,
        SubscriptionStatuses::CANCELLED,
    ], true);
}
}
