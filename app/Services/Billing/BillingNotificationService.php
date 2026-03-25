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
        'trial_ends_at' => optional($subscription->trial_ends_at)?->format('Y-m-d H:i:s'),
            'grace_ends_at' => optional($subscription->grace_ends_at)?->format('Y-m-d H:i:s'),
            'last_payment_failed_at' => optional($subscription->last_payment_failed_at)?->format('Y-m-d H:i:s'),
            'past_due_started_at' => optional($subscription->past_due_started_at)?->format('Y-m-d H:i:s'),
            'suspended_at' => optional($subscription->suspended_at)?->format('Y-m-d H:i:s'),
            'ends_at' => optional($subscription->ends_at)?->format('Y-m-d H:i:s'),
        ];
    }
}
