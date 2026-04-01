<?php

namespace App\Services\Billing;

use App\Data\AdminNotificationData;
use App\Models\Subscription;
use App\Services\Notifications\AdminNotificationService;

class BillingNotificationService
{
    public function __construct(
        protected AdminNotificationService $adminNotificationService
    ) {
    }

public function manualSync(Subscription $subscription, array $context = []): void
{
    $this->create(
        event: 'manual_sync',
            title: 'Subscription Synced from Stripe',
            severity: 'info',
            subscription: $subscription,
            message: "Subscription #{$subscription->id} was manually synced from Stripe.",
            contextPayload: $context
        );
    }

public function manualRefreshState(Subscription $subscription, array $context = []): void
{
    $this->create(
        event: 'manual_refresh_state',
            title: 'Subscription State Refreshed',
            severity: 'info',
            subscription: $subscription,
            message: "Subscription #{$subscription->id} state was refreshed manually.",
            contextPayload: $context
        );
    }

public function manualNormalizeLifecycle(Subscription $subscription, bool $applied, array $context = []): void
{
    $this->create(
        event: 'manual_normalize_lifecycle',
            title: $applied ? 'Lifecycle Normalization Applied' : 'Lifecycle Normalization Checked',
            severity: $applied ? 'warning' : 'info',
            subscription: $subscription,
            message: $applied
    ? "Lifecycle normalization applied to subscription #{$subscription->id}."
    : "Lifecycle normalization check completed for subscription #{$subscription->id}. No changes were needed.",
            contextPayload: $context
        );
    }

public function manualLifecycleChange(Subscription $subscription, array $context = []): void
{
    $targetStatus = (string) ($context['target_status'] ?? $subscription->status ?? 'unknown');

    $this->create(
        event: 'manual_lifecycle_change',
            title: 'Subscription Lifecycle Changed',
            severity: 'warning',
            subscription: $subscription,
            message: "Subscription #{$subscription->id} lifecycle was changed manually to {$targetStatus}.",
            contextPayload: $context
        );
    }

public function manualTimestampUpdate(Subscription $subscription, array $context = []): void
{
    $this->create(
        event: 'manual_timestamp_update',
            title: 'Subscription Dates Updated',
            severity: 'info',
            subscription: $subscription,
            message: "Subscription #{$subscription->id} lifecycle timestamps were updated manually.",
            contextPayload: $context
        );
    }

public function trialEnding(Subscription $subscription, array $context = []): void
{
    $this->create(
        event: 'trial_ending',
            title: 'Trial Ending Soon',
            severity: 'warning',
            subscription: $subscription,
            message: "Tenant {$subscription->tenant_id} trial is ending soon for subscription #{$subscription->id}.",
            contextPayload: $context
        );
    }

public function suspended(Subscription $subscription, array $context = []): void
{
    $this->create(
        event: 'suspended',
            title: 'Subscription Suspended',
            severity: 'error',
            subscription: $subscription,
            message: "Tenant {$subscription->tenant_id} subscription #{$subscription->id} was suspended.",
            contextPayload: $context
        );
    }

public function paymentFailed(Subscription $subscription, array $context = []): void
{
    $this->create(
        event: 'payment_failed',
            title: 'Payment Failed',
            severity: 'error',
            subscription: $subscription,
            message: "A Stripe payment attempt failed for subscription #{$subscription->id}.",
            contextPayload: $context
        );
    }

public function invoicePaid(Subscription $subscription, array $context = []): void
{
    $this->create(
        event: 'invoice_paid',
            title: 'Invoice Paid',
            severity: 'success',
            subscription: $subscription,
            message: "A Stripe invoice was paid successfully for subscription #{$subscription->id}.",
            contextPayload: $context
        );
    }

public function renewalSucceeded(Subscription $subscription, array $context = []): void
{
    $this->create(
        event: 'renewal_succeeded',
            title: 'Renewal Succeeded',
            severity: 'success',
            subscription: $subscription,
            message: "Subscription #{$subscription->id} renewed successfully.",
            contextPayload: $context
        );
    }

public function renewalFailed(Subscription $subscription, array $context = []): void
{
    $this->create(
        event: 'renewal_failed',
            title: 'Renewal Failed',
            severity: 'error',
            subscription: $subscription,
            message: "Subscription #{$subscription->id} renewal failed.",
            contextPayload: $context
        );
    }

public function subscriptionCancelled(Subscription $subscription, array $context = []): void
{
    $this->create(
        event: 'subscription_cancelled',
            title: 'Subscription Cancelled',
            severity: 'warning',
            subscription: $subscription,
            message: "Subscription #{$subscription->id} was cancelled.",
            contextPayload: $context
        );
    }

public function subscriptionExpired(Subscription $subscription, array $context = []): void
{
    $this->create(
        event: 'subscription_expired',
            title: 'Subscription Expired',
            severity: 'error',
            subscription: $subscription,
            message: "Subscription #{$subscription->id} has expired.",
            contextPayload: $context
        );
    }

public function checkoutCompleted(Subscription $subscription, array $context = []): void
{
    $this->create(
        event: 'checkout_session_completed',
            title: 'Checkout Session Completed',
            severity: 'success',
            subscription: $subscription,
            message: "Checkout session completed successfully for subscription #{$subscription->id}.",
            contextPayload: $context
        );
    }

public function planChanged(Subscription $subscription, array $context = []): void
{
    $this->create(
        event: 'plan_changed',
            title: 'Subscription Plan Changed',
            severity: 'info',
            subscription: $subscription,
            message: "Subscription #{$subscription->id} plan mapping changed successfully.",
            contextPayload: $context
        );
    }

protected function create(
    string $event,
    string $title,
    string $severity,
    Subscription $subscription,
    string $message,
    array $contextPayload = []
): void {
    $this->adminNotificationService->create(new AdminNotificationData(
        type: 'billing',
            title: $title,
            message: $message,
            severity: $severity,
            sourceType: 'subscription',
            sourceId: (int) $subscription->id,
            routeName: 'admin.subscriptions.show',
            routeParams: ['subscription' => $subscription->id],
            targetUrl: null,
            tenantId: (string) $subscription->tenant_id,
            userId: null,
            userEmail: null,
            contextPayload: array_merge($this->baseContext($subscription), [
        'event' => $event,
    ], $contextPayload),
        ));
    }

protected function baseContext(Subscription $subscription): array
{
    return [
        'subscription_id' => $subscription->id,
        'tenant_id' => $subscription->tenant_id,
        'plan_id' => $subscription->plan_id,
        'status' => $subscription->status,
        'gateway' => $subscription->gateway,
        'gateway_subscription_id' => $subscription->gateway_subscription_id,
        'gateway_checkout_session_id' => $subscription->gateway_checkout_session_id,
        'gateway_customer_id' => $subscription->gateway_customer_id,
        'gateway_price_id' => $subscription->gateway_price_id,
        'trial_ends_at' => optional($subscription->trial_ends_at)?->format('Y-m-d H:i:s'),
            'grace_ends_at' => optional($subscription->grace_ends_at)?->format('Y-m-d H:i:s'),
            'last_payment_failed_at' => optional($subscription->last_payment_failed_at)?->format('Y-m-d H:i:s'),
            'past_due_started_at' => optional($subscription->past_due_started_at)?->format('Y-m-d H:i:s'),
            'suspended_at' => optional($subscription->suspended_at)?->format('Y-m-d H:i:s'),
            'cancelled_at' => optional($subscription->cancelled_at)?->format('Y-m-d H:i:s'),
            'ends_at' => optional($subscription->ends_at)?->format('Y-m-d H:i:s'),
        ];
    }
}
