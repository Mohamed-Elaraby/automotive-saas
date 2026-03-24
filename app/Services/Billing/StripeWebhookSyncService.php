<?php

namespace App\Services\Billing;

use App\Models\Subscription;
use Illuminate\Support\Arr;

class StripeWebhookSyncService
{
    public function __construct(
        protected TenantBillingLifecycleService $lifecycleService,
        protected BillingNotificationService $billingNotificationService
    ) {
    }

public function handleEvent(string $eventType, array $payload): void
{
    if ($eventType === 'invoice.payment_failed') {
        $this->handleInvoicePaymentFailed($payload);
        return;
    }

    if ($eventType === 'invoice.paid') {
        $this->handleInvoicePaid($payload);
        return;
    }
}

protected function handleInvoicePaymentFailed(array $payload): void
{
    $gatewaySubscriptionId = (string) Arr::get($payload, 'data.object.subscription', '');

    if ($gatewaySubscriptionId === '') {
        return;
    }

    $subscription = Subscription::query()
        ->where('gateway', 'stripe')
        ->where('gateway_subscription_id', $gatewaySubscriptionId)
        ->first();

    if (! $subscription) {
        return;
    }

    $this->lifecycleService->markAsPastDue($subscription, now());

    $this->billingNotificationService->paymentFailed($subscription->fresh(), [
        'stripe_event' => 'invoice.payment_failed',
        'invoice_id' => Arr::get($payload, 'data.object.id'),
        'billing_reason' => Arr::get($payload, 'data.object.billing_reason'),
        'attempt_count' => Arr::get($payload, 'data.object.attempt_count'),
    ]);
}

protected function handleInvoicePaid(array $payload): void
{
    $gatewaySubscriptionId = (string) Arr::get($payload, 'data.object.subscription', '');

    if ($gatewaySubscriptionId === '') {
        return;
    }

    $subscription = Subscription::query()
        ->where('gateway', 'stripe')
        ->where('gateway_subscription_id', $gatewaySubscriptionId)
        ->first();

    if (! $subscription) {
        return;
    }

    $this->lifecycleService->markAsRecovered($subscription, now());
}
}
