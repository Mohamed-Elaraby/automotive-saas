<?php

namespace App\Services\Billing;

use App\Support\Billing\SubscriptionStatuses;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StripeWebhookSyncService
{
    public function __construct(
        protected TenantBillingLifecycleService $tenantBillingLifecycleService
    ) {
    }

protected function centralConnection(): string
{
    return config('tenancy.database.central_connection') ?? config('database.default');
}

protected function subscriptionsTable()
{
    return DB::connection($this->centralConnection())->table('subscriptions');
}

protected function findSubscriptionById(int|string $subscriptionId): ?object
    {
        return $this->subscriptionsTable()
            ->where('id', $subscriptionId)
            ->first();
    }

    protected function findSubscriptionByGatewaySubscriptionId(string $gatewaySubscriptionId): ?object
{
    return $this->subscriptionsTable()
        ->where('gateway_subscription_id', $gatewaySubscriptionId)
        ->first();
}

    public function handleEvent(object $event): void
{
    $type = $event->type ?? null;
    $object = $event->data->object ?? null;

    if (! $type || ! $object) {
        return;
    }

    match ($type) {
    'checkout.session.completed' => $this->handleCheckoutSessionCompleted($object),
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted' => $this->handleSubscriptionChanged($object),
            'invoice.paid' => $this->handleInvoicePaid($object),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed($object),
            default => null,
        };
    }

    protected function handleCheckoutSessionCompleted(object $session): void
{
    $subscriptionRowId = $session->metadata->subscription_row_id ?? null;
    $targetPlanId = $session->metadata->plan_id ?? null;

    if (! $subscriptionRowId) {
        return;
    }

    $this->subscriptionsTable()
        ->where('id', $subscriptionRowId)
        ->update([
            'plan_id' => $targetPlanId ?: DB::raw('plan_id'),
            'gateway' => 'stripe',
            'gateway_customer_id' => $session->customer ?? null,
            'gateway_subscription_id' => $session->subscription ?? null,
            'gateway_checkout_session_id' => $session->id ?? null,
            'updated_at' => now(),
        ]);
}

    protected function handleSubscriptionChanged(object $stripeSubscription): void
{
    $subscriptionRowId = $stripeSubscription->metadata->subscription_row_id ?? null;
    $targetPlanId = $stripeSubscription->metadata->plan_id ?? null;

    $subscription = null;

    if ($subscriptionRowId) {
        $subscription = $this->findSubscriptionById($subscriptionRowId);
    }

    if (! $subscription && ! empty($stripeSubscription->id)) {
        $subscription = $this->findSubscriptionByGatewaySubscriptionId((string) $stripeSubscription->id);
    }

    if (! $subscription) {
        return;
    }

    $internalStatus = $this->mapStripeSubscriptionStatus($stripeSubscription->status ?? null);

    $endsAt = null;
    if (! empty($stripeSubscription->cancel_at)) {
        $endsAt = Carbon::createFromTimestamp($stripeSubscription->cancel_at);
    } elseif (! empty($stripeSubscription->current_period_end)) {
        $endsAt = Carbon::createFromTimestamp($stripeSubscription->current_period_end);
    }

    $cancelledAt = ! empty($stripeSubscription->canceled_at)
        ? Carbon::createFromTimestamp($stripeSubscription->canceled_at)
        : null;

    $priceId = $stripeSubscription->items->data[0]->price->id ?? null;

    $this->subscriptionsTable()
        ->where('id', $subscription->id)
        ->update([
            'plan_id' => $targetPlanId ?: DB::raw('plan_id'),
            'gateway' => 'stripe',
            'gateway_customer_id' => $stripeSubscription->customer ?? null,
            'gateway_subscription_id' => $stripeSubscription->id ?? null,
            'gateway_price_id' => $priceId,
            'ends_at' => $endsAt,
            'cancelled_at' => $cancelledAt,
            'updated_at' => now(),
        ]);

    $fresh = $this->findSubscriptionById($subscription->id);

    match ($internalStatus) {
    SubscriptionStatuses::TRIALING => $this->subscriptionsTable()
    ->where('id', $subscription->id)
    ->update([
        'status' => SubscriptionStatuses::TRIALING,
        'grace_ends_at' => null,
        'last_payment_failed_at' => null,
        'past_due_started_at' => null,
        'suspended_at' => null,
        'payment_failures_count' => 0,
        'updated_at' => now(),
    ]),

            SubscriptionStatuses::ACTIVE => $this->tenantBillingLifecycleService->markAsRecovered($fresh),

            SubscriptionStatuses::PAST_DUE => $this->tenantBillingLifecycleService->markAsPastDue($fresh),

            SubscriptionStatuses::SUSPENDED => $this->tenantBillingLifecycleService->markAsSuspended($fresh),

            SubscriptionStatuses::CANCELLED => $this->tenantBillingLifecycleService->markAsCancelled(
    $fresh,
    $cancelledAt,
    $endsAt
),

            SubscriptionStatuses::EXPIRED => $this->tenantBillingLifecycleService->markAsExpired($fresh),

            default => null,
        };
    }

    protected function handleInvoicePaid(object $invoice): void
{
    $gatewaySubscriptionId = $invoice->subscription ?? null;

    if (! $gatewaySubscriptionId) {
        return;
    }

    $subscription = $this->findSubscriptionByGatewaySubscriptionId((string) $gatewaySubscriptionId);

    if (! $subscription) {
        return;
    }

    $paidAt = ! empty($invoice->status_transitions->paid_at)
        ? Carbon::createFromTimestamp($invoice->status_transitions->paid_at)
        : now();

    $this->tenantBillingLifecycleService->markAsRecovered($subscription, $paidAt);
}

    protected function handleInvoicePaymentFailed(object $invoice): void
{
    $gatewaySubscriptionId = $invoice->subscription ?? null;

    if (! $gatewaySubscriptionId) {
        return;
    }

    $subscription = $this->findSubscriptionByGatewaySubscriptionId((string) $gatewaySubscriptionId);

    if (! $subscription) {
        return;
    }

    $failedAt = ! empty($invoice->created)
        ? Carbon::createFromTimestamp($invoice->created)
        : now();

    $this->tenantBillingLifecycleService->markAsPastDue($subscription, $failedAt);
}

    protected function mapStripeSubscriptionStatus(?string $stripeStatus): string
{
    return match ($stripeStatus) {
    'trialing' => SubscriptionStatuses::TRIALING,
            'active' => SubscriptionStatuses::ACTIVE,
            'past_due', 'unpaid', 'incomplete' => SubscriptionStatuses::PAST_DUE,
            'canceled' => SubscriptionStatuses::CANCELLED,
            'paused' => SubscriptionStatuses::SUSPENDED,
            'incomplete_expired' => SubscriptionStatuses::EXPIRED,
            default => SubscriptionStatuses::EXPIRED,
        };
    }
}
