<?php

namespace App\Services\Billing;

use App\Support\Billing\SubscriptionStatuses;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StripeWebhookSyncService
{
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

        DB::table('subscriptions')
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

        $query = DB::table('subscriptions');

        if ($subscriptionRowId) {
            $query->where('id', $subscriptionRowId);
        } else {
            $query->where('gateway_subscription_id', $stripeSubscription->id ?? '');
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

        $query->update([
            'plan_id' => $targetPlanId ?: DB::raw('plan_id'),
            'status' => $internalStatus,
            'gateway' => 'stripe',
            'gateway_customer_id' => $stripeSubscription->customer ?? null,
            'gateway_subscription_id' => $stripeSubscription->id ?? null,
            'gateway_price_id' => $priceId,
            'ends_at' => $endsAt,
            'cancelled_at' => $cancelledAt,
            'updated_at' => now(),
        ]);
    }

    protected function handleInvoicePaid(object $invoice): void
    {
        $gatewaySubscriptionId = $invoice->subscription ?? null;

        if (! $gatewaySubscriptionId) {
            return;
        }

        DB::table('subscriptions')
            ->where('gateway_subscription_id', $gatewaySubscriptionId)
            ->update([
                'status' => SubscriptionStatuses::ACTIVE,
                'grace_ends_at' => null,
                'last_payment_failed_at' => null,
                'updated_at' => now(),
            ]);
    }

    protected function handleInvoicePaymentFailed(object $invoice): void
    {
        $gatewaySubscriptionId = $invoice->subscription ?? null;

        if (! $gatewaySubscriptionId) {
            return;
        }

        $graceDays = (int) config('billing.grace_period_days', 3);

        DB::table('subscriptions')
            ->where('gateway_subscription_id', $gatewaySubscriptionId)
            ->update([
                'status' => SubscriptionStatuses::PAST_DUE,
                'last_payment_failed_at' => now(),
                'grace_ends_at' => now()->addDays($graceDays),
                'updated_at' => now(),
            ]);
    }

    protected function mapStripeSubscriptionStatus(?string $stripeStatus): string
    {
        return match ($stripeStatus) {
        'trialing' => SubscriptionStatuses::TRIALING,
            'active' => SubscriptionStatuses::ACTIVE,
            'past_due', 'unpaid' => SubscriptionStatuses::PAST_DUE,
            'canceled' => SubscriptionStatuses::CANCELLED,
            'paused' => SubscriptionStatuses::SUSPENDED,
            'incomplete_expired' => SubscriptionStatuses::EXPIRED,
            default => SubscriptionStatuses::EXPIRED,
        };
    }
}
