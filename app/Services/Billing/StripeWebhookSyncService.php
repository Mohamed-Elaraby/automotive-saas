<?php

namespace App\Services\Billing;

use App\Services\Automotive\ProvisionTenantWorkspaceService;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Throwable;

class StripeWebhookSyncService
{
    public function __construct(
        protected StripeSubscriptionSyncService $stripeSubscriptionSyncService,
        protected BillingNotificationService $billingNotificationService,
        protected ProvisionTenantWorkspaceService $provisionTenantWorkspaceService
    ) {
    }

public function handleEvent(object $event): void
{
    $payload = $this->eventToArray($event);
    $eventType = (string) ($payload['type'] ?? '');

    if ($eventType === '') {
        return;
    }

    try {
        match ($eventType) {
        'invoice.payment_failed' => $this->handleInvoicePaymentFailed($payload),
                'invoice.paid' => $this->handleInvoicePaid($payload),
                'customer.subscription.updated' => $this->handleCustomerSubscriptionUpdated($payload),
                'customer.subscription.deleted' => $this->handleCustomerSubscriptionDeleted($payload),
                'checkout.session.completed' => $this->handleCheckoutSessionCompleted($payload),
                default => null,
            };
        } catch (Throwable $e) {
        report($e);
    }
}

protected function handleInvoicePaymentFailed(array $payload): void
{
    $invoice = (array) Arr::get($payload, 'data.object', []);
    $gatewaySubscriptionId = (string) ($invoice['subscription'] ?? '');

    if ($gatewaySubscriptionId === '') {
        return;
    }

    $subscription = $this->syncAndFindSubscriptionByGatewaySubscriptionId($gatewaySubscriptionId);

    if (! $subscription) {
        return;
    }

    $billingReason = (string) ($invoice['billing_reason'] ?? '');
    $attemptCount = (int) ($invoice['attempt_count'] ?? 0);

    $this->billingNotificationService->paymentFailed($subscription, [
        'stripe_event' => 'invoice.payment_failed',
        'invoice_id' => $invoice['id'] ?? null,
        'billing_reason' => $billingReason,
        'attempt_count' => $attemptCount,
        'amount_due' => $invoice['amount_due'] ?? null,
        'amount_paid' => $invoice['amount_paid'] ?? null,
        'currency' => $invoice['currency'] ?? null,
    ]);

    if (in_array($billingReason, ['subscription_cycle', 'subscription_update'], true)) {
        $this->billingNotificationService->renewalFailed($subscription, [
            'stripe_event' => 'invoice.payment_failed',
            'invoice_id' => $invoice['id'] ?? null,
            'billing_reason' => $billingReason,
            'attempt_count' => $attemptCount,
        ]);
    }
}

protected function handleInvoicePaid(array $payload): void
{
    $invoice = (array) Arr::get($payload, 'data.object', []);
    $gatewaySubscriptionId = (string) ($invoice['subscription'] ?? '');

    if ($gatewaySubscriptionId === '') {
        return;
    }

    $subscription = $this->syncAndFindSubscriptionByGatewaySubscriptionId($gatewaySubscriptionId);

    if (! $subscription) {
        return;
    }

    $billingReason = (string) ($invoice['billing_reason'] ?? '');

    $this->billingNotificationService->invoicePaid($subscription, [
        'stripe_event' => 'invoice.paid',
        'invoice_id' => $invoice['id'] ?? null,
        'billing_reason' => $billingReason,
        'amount_due' => $invoice['amount_due'] ?? null,
        'amount_paid' => $invoice['amount_paid'] ?? null,
        'currency' => $invoice['currency'] ?? null,
    ]);

    if (in_array($billingReason, ['subscription_cycle', 'subscription_update'], true)) {
        $this->billingNotificationService->renewalSucceeded($subscription, [
            'stripe_event' => 'invoice.paid',
            'invoice_id' => $invoice['id'] ?? null,
            'billing_reason' => $billingReason,
        ]);
    }
}

protected function handleCustomerSubscriptionUpdated(array $payload): void
{
    $stripeSubscription = (array) Arr::get($payload, 'data.object', []);
    $gatewaySubscriptionId = (string) ($stripeSubscription['id'] ?? '');

    if ($gatewaySubscriptionId === '') {
        return;
    }

    $before = $this->findSubscriptionByGatewaySubscriptionId($gatewaySubscriptionId);

    $beforePlanId = $before?->plan_id;
        $beforeGatewayPriceId = $before?->gateway_price_id;
        $beforeStatus = $before?->status;
        $beforeCancelledAt = $before?->cancelled_at;
        $beforeEndsAt = $before?->ends_at;

        $subscription = $this->syncAndFindSubscriptionByGatewaySubscriptionId($gatewaySubscriptionId);

        if (! $subscription) {
            return;
        }

        $afterPlanId = $subscription->plan_id;
        $afterGatewayPriceId = $subscription->gateway_price_id;
        $afterStatus = $subscription->status;
        $afterCancelledAt = $subscription->cancelled_at;
        $afterEndsAt = $subscription->ends_at;

        if (
            (string) $beforePlanId !== (string) $afterPlanId
            || (string) $beforeGatewayPriceId !== (string) $afterGatewayPriceId
        ) {
            $this->billingNotificationService->planChanged($subscription, [
                'stripe_event' => 'customer.subscription.updated',
                'old_plan_id' => $beforePlanId,
                'new_plan_id' => $afterPlanId,
                'old_gateway_price_id' => $beforeGatewayPriceId,
                'new_gateway_price_id' => $afterGatewayPriceId,
            ]);
        }

        $cancelAtPeriodEnd = (bool) ($stripeSubscription['cancel_at_period_end'] ?? false);
        $stripeStatus = (string) ($stripeSubscription['status'] ?? '');

        if (
            $cancelAtPeriodEnd
            && (string) $beforeCancelledAt !== (string) $afterCancelledAt
        ) {
            $this->billingNotificationService->subscriptionCancelled($subscription, [
                'stripe_event' => 'customer.subscription.updated',
                'cancel_at_period_end' => true,
                'stripe_status' => $stripeStatus,
                'old_status' => $beforeStatus,
                'new_status' => $afterStatus,
                'old_ends_at' => optional($beforeEndsAt)?->format('Y-m-d H:i:s'),
                'new_ends_at' => optional($afterEndsAt)?->format('Y-m-d H:i:s'),
            ]);
        }
    }

protected function handleCustomerSubscriptionDeleted(array $payload): void
{
    $stripeSubscription = (array) Arr::get($payload, 'data.object', []);
    $gatewaySubscriptionId = (string) ($stripeSubscription['id'] ?? '');

    if ($gatewaySubscriptionId === '') {
        return;
    }

    $subscription = $this->syncAndFindSubscriptionByGatewaySubscriptionId($gatewaySubscriptionId);

    if (! $subscription) {
        return;
    }

    $periodEndUnix = (int) ($stripeSubscription['current_period_end'] ?? 0);
    $periodEnd = $periodEndUnix > 0 ? Carbon::createFromTimestamp($periodEndUnix) : null;

    if ($periodEnd && $periodEnd->isPast()) {
        $this->billingNotificationService->subscriptionExpired($subscription, [
            'stripe_event' => 'customer.subscription.deleted',
            'current_period_end' => $periodEnd->format('Y-m-d H:i:s'),
        ]);

        return;
    }

    $this->billingNotificationService->subscriptionCancelled($subscription, [
        'stripe_event' => 'customer.subscription.deleted',
        'current_period_end' => $periodEnd?->format('Y-m-d H:i:s'),
        ]);
    }

protected function handleCheckoutSessionCompleted(array $payload): void
{
    $session = (array) Arr::get($payload, 'data.object', []);
    $sessionId = (string) ($session['id'] ?? '');
    $sessionSubscriptionId = (string) ($session['subscription'] ?? '');
    $sessionCustomerId = (string) ($session['customer'] ?? '');
    $subscriptionRowId = (int) Arr::get($session, 'metadata.subscription_row_id', 0);
    $tenantIdFromMetadata = (string) Arr::get($session, 'metadata.tenant_id', '');
    $planIdFromMetadata = (int) Arr::get($session, 'metadata.plan_id', 0);

    if ($sessionId === '') {
        return;
    }

    $subscription = Subscription::query()
        ->where('gateway', 'stripe')
        ->where('gateway_checkout_session_id', $sessionId)
        ->first();

    if (! $subscription && $subscriptionRowId > 0) {
        $subscription = Subscription::query()->find($subscriptionRowId);
    }

    if (! $subscription && $tenantIdFromMetadata !== '') {
        $subscription = Subscription::query()->create([
            'tenant_id' => $tenantIdFromMetadata,
            'plan_id' => $planIdFromMetadata > 0 ? $planIdFromMetadata : null,
            'status' => 'past_due',
            'trial_ends_at' => null,
            'grace_ends_at' => null,
            'last_payment_failed_at' => null,
            'past_due_started_at' => null,
            'suspended_at' => null,
            'cancelled_at' => null,
            'payment_failures_count' => 0,
            'ends_at' => null,
            'external_id' => null,
            'gateway' => 'stripe',
            'gateway_customer_id' => $sessionCustomerId !== '' ? $sessionCustomerId : null,
            'gateway_subscription_id' => $sessionSubscriptionId !== '' ? $sessionSubscriptionId : null,
            'gateway_checkout_session_id' => $sessionId,
            'gateway_price_id' => null,
        ]);
    }

    if (! $subscription) {
        return;
    }

    $subscription->fill([
        'gateway' => 'stripe',
        'gateway_checkout_session_id' => $sessionId,
    ]);

    if ($sessionCustomerId !== '') {
        $subscription->gateway_customer_id = $sessionCustomerId;
    }

    if ($sessionSubscriptionId !== '') {
        $subscription->gateway_subscription_id = $sessionSubscriptionId;
    }

    $subscription->save();

    if ($sessionSubscriptionId !== '') {
        $subscription = $this->syncAndFindSubscriptionByGatewaySubscriptionId($sessionSubscriptionId)
            ?? $subscription->fresh();
    } else {
        $subscription = $subscription->fresh();
    }

    $this->provisionWorkspaceAfterSuccessfulCheckout($subscription);

    $this->billingNotificationService->checkoutCompleted($subscription, [
        'stripe_event' => 'checkout.session.completed',
        'checkout_session_id' => $sessionId,
        'checkout_mode' => $session['mode'] ?? null,
        'customer_id' => $sessionCustomerId !== '' ? $sessionCustomerId : null,
        'payment_status' => $session['payment_status'] ?? null,
        'subscription_id_from_session' => $sessionSubscriptionId !== '' ? $sessionSubscriptionId : null,
        'subscription_row_id_from_metadata' => $subscriptionRowId > 0 ? $subscriptionRowId : null,
    ]);
}

protected function syncAndFindSubscriptionByGatewaySubscriptionId(string $gatewaySubscriptionId): ?Subscription
{
    if (method_exists($this->stripeSubscriptionSyncService, 'syncByGatewaySubscriptionId')) {
        try {
            $this->stripeSubscriptionSyncService->syncByGatewaySubscriptionId($gatewaySubscriptionId);
        } catch (Throwable $e) {
            report($e);
        }
    }

    return $this->findSubscriptionByGatewaySubscriptionId($gatewaySubscriptionId);
}

protected function findSubscriptionByGatewaySubscriptionId(string $gatewaySubscriptionId): ?Subscription
{
    return Subscription::query()
        ->where('gateway', 'stripe')
        ->where('gateway_subscription_id', $gatewaySubscriptionId)
        ->first();
}

protected function eventToArray(object $event): array
{
    return json_decode(json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true) ?: [];
}

protected function provisionWorkspaceAfterSuccessfulCheckout(Subscription $subscription): void
{
    if (blank($subscription->tenant_id)) {
        return;
    }

    try {
        $this->provisionTenantWorkspaceService->ensureProvisioned((string) $subscription->tenant_id);
    } catch (Throwable $e) {
        report($e);
    }
}
}
