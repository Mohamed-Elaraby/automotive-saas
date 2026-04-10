<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Services\Automotive\ProvisionTenantWorkspaceService;
use App\Models\Subscription;
use App\Models\TenantProductSubscription;
use App\Support\Billing\SubscriptionStatuses;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Throwable;

class StripeWebhookSyncService
{
    public function __construct(
        protected StripeSubscriptionSyncService $stripeSubscriptionSyncService,
        protected BillingNotificationService $billingNotificationService,
        protected ProvisionTenantWorkspaceService $provisionTenantWorkspaceService,
        protected TenantProductSubscriptionSyncService $tenantProductSubscriptionSyncService
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
        $productSubscription = $this->findTenantProductSubscriptionByGatewaySubscriptionId($gatewaySubscriptionId);

        if ($productSubscription) {
            $this->markTenantProductSubscriptionPastDue($productSubscription);
        }

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
        $productSubscription = $this->findTenantProductSubscriptionByGatewaySubscriptionId($gatewaySubscriptionId);

        if ($productSubscription) {
            $this->markTenantProductSubscriptionRecovered($productSubscription);
        }

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
            $productSubscription = $this->findTenantProductSubscriptionByGatewaySubscriptionId($gatewaySubscriptionId);

            if ($productSubscription) {
                $this->syncTenantProductSubscriptionFromStripePayload($productSubscription, $stripeSubscription);
            }

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
        $productSubscription = $this->findTenantProductSubscriptionByGatewaySubscriptionId($gatewaySubscriptionId);

        if ($productSubscription) {
            $periodEndUnix = (int) ($stripeSubscription['current_period_end'] ?? 0);
            $periodEnd = $periodEndUnix > 0 ? Carbon::createFromTimestamp($periodEndUnix) : null;

            if ($periodEnd && $periodEnd->isFuture()) {
                $this->markTenantProductSubscriptionCancelled($productSubscription, null, $periodEnd);
            } else {
                $this->markTenantProductSubscriptionExpired($productSubscription, $periodEnd);
            }
        }

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
    $tenantProductSubscriptionId = (int) Arr::get($session, 'metadata.tenant_product_subscription_id', 0);
    $tenantIdFromMetadata = (string) Arr::get($session, 'metadata.tenant_id', '');
    $planIdFromMetadata = (int) Arr::get($session, 'metadata.plan_id', 0);

    if ($sessionId === '') {
        return;
    }

    if ($tenantProductSubscriptionId > 0) {
        $this->handleTenantProductCheckoutSessionCompleted(
            $tenantProductSubscriptionId,
            $sessionId,
            $sessionCustomerId,
            $sessionSubscriptionId,
            $planIdFromMetadata
        );

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
    $this->tenantProductSubscriptionSyncService->syncFromLegacySubscription($subscription);

    if ($sessionSubscriptionId !== '') {
        $subscription = $this->syncAndFindSubscriptionByGatewaySubscriptionId($sessionSubscriptionId)
            ?? $subscription->fresh();
    } else {
        $subscription = $subscription->fresh();
    }

    if ($subscription) {
        $this->tenantProductSubscriptionSyncService->syncFromLegacySubscription($subscription);
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

protected function handleTenantProductCheckoutSessionCompleted(
    int $tenantProductSubscriptionId,
    string $sessionId,
    string $sessionCustomerId,
    string $sessionSubscriptionId,
    int $planIdFromMetadata
): void {
    $productSubscription = TenantProductSubscription::query()->find($tenantProductSubscriptionId);

    if (! $productSubscription) {
        return;
    }

    $productSubscription->fill([
        'gateway' => 'stripe',
        'gateway_checkout_session_id' => $sessionId,
        'gateway_customer_id' => $sessionCustomerId !== '' ? $sessionCustomerId : $productSubscription->gateway_customer_id,
        'gateway_subscription_id' => $sessionSubscriptionId !== '' ? $sessionSubscriptionId : $productSubscription->gateway_subscription_id,
        'status' => $sessionSubscriptionId !== '' ? SubscriptionStatuses::ACTIVE : SubscriptionStatuses::PAST_DUE,
    ]);

    if ($planIdFromMetadata > 0) {
        $productSubscription->plan_id = $planIdFromMetadata;
    }

    $productSubscription->save();
}

protected function findTenantProductSubscriptionByGatewaySubscriptionId(string $gatewaySubscriptionId): ?TenantProductSubscription
{
    return TenantProductSubscription::query()
        ->where('gateway', 'stripe')
        ->where('gateway_subscription_id', $gatewaySubscriptionId)
        ->first();
}

protected function syncTenantProductSubscriptionFromStripePayload(
    TenantProductSubscription $subscription,
    array $stripeSubscription
): TenantProductSubscription {
    $mappedStatus = $this->mapStripeStatus((string) ($stripeSubscription['status'] ?? ''));
    $currentPeriodEnd = $this->timestampToCarbon($stripeSubscription['current_period_end'] ?? null);
    $trialEndsAt = $this->timestampToCarbon($stripeSubscription['trial_end'] ?? null);
    $cancelledAt = $this->timestampToCarbon($stripeSubscription['canceled_at'] ?? null);
    $cancelAtPeriodEnd = (bool) ($stripeSubscription['cancel_at_period_end'] ?? false);
    $gatewayPriceId = (string) ($stripeSubscription['items']['data'][0]['price']['id'] ?? '');
    $resolvedPlanId = $this->resolveLocalPlanId((array) $stripeSubscription, $gatewayPriceId !== '' ? $gatewayPriceId : null);

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
        $this->markTenantProductSubscriptionRecovered($subscription);

        if ($cancelAtPeriodEnd && $currentPeriodEnd && $currentPeriodEnd->isFuture()) {
            return $this->markTenantProductSubscriptionCancelled($subscription, $cancelledAt, $currentPeriodEnd);
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
        return $this->markTenantProductSubscriptionPastDue($subscription);
    }

    if ($mappedStatus === SubscriptionStatuses::SUSPENDED) {
        return $this->markTenantProductSubscriptionSuspended($subscription);
    }

    if ($mappedStatus === SubscriptionStatuses::EXPIRED) {
        if ($currentPeriodEnd && $currentPeriodEnd->isFuture()) {
            return $this->markTenantProductSubscriptionCancelled($subscription, $cancelledAt, $currentPeriodEnd);
        }

        return $this->markTenantProductSubscriptionExpired($subscription, $currentPeriodEnd ?? $cancelledAt);
    }

    return $subscription->fresh();
}

protected function markTenantProductSubscriptionPastDue(TenantProductSubscription $subscription, ?Carbon $failedAt = null): TenantProductSubscription
{
    $failedAt ??= now();
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

protected function markTenantProductSubscriptionRecovered(TenantProductSubscription $subscription, ?Carbon $recoveredAt = null): TenantProductSubscription
{
    $subscription->update([
        'status' => SubscriptionStatuses::ACTIVE,
        'last_payment_failed_at' => null,
        'past_due_started_at' => null,
        'grace_ends_at' => null,
        'suspended_at' => null,
        'payment_failures_count' => 0,
        'updated_at' => $recoveredAt ?? now(),
    ]);

    return $subscription->fresh();
}

protected function markTenantProductSubscriptionSuspended(TenantProductSubscription $subscription, ?Carbon $suspendedAt = null): TenantProductSubscription
{
    $subscription->update([
        'status' => SubscriptionStatuses::SUSPENDED,
        'suspended_at' => $subscription->suspended_at ?? ($suspendedAt ?? now()),
    ]);

    return $subscription->fresh();
}

protected function markTenantProductSubscriptionExpired(TenantProductSubscription $subscription, ?Carbon $expiredAt = null): TenantProductSubscription
{
    $expiredAt ??= now();

    $subscription->update([
        'status' => SubscriptionStatuses::EXPIRED,
        'ends_at' => $subscription->ends_at ?? $expiredAt,
        'suspended_at' => $subscription->suspended_at ?? $expiredAt,
    ]);

    return $subscription->fresh();
}

protected function markTenantProductSubscriptionCancelled(
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

protected function timestampToCarbon(mixed $value): ?Carbon
{
    if (! $value) {
        return null;
    }

    return Carbon::createFromTimestamp((int) $value);
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
