<?php

namespace App\Services\Billing;

use App\Models\Subscription;
use App\Services\Notifications\AdminNotificationService;

class BillingNotificationService
{
    public function __construct(
        protected AdminNotificationService $adminNotificationService
    ) {
    }

public function paymentFailed(Subscription $subscription, array $context = []): void
{
    $this->adminNotificationService->createBillingNotification(
        title: 'Payment Failed',
            message: $this->buildSubscriptionMessage(
    subscription: $subscription,
                fallback: 'A payment attempt failed for this subscription.'
            ),
            severity: 'error',
            tenantId: (string) $subscription->tenant_id,
            contextPayload: array_merge($this->baseContext($subscription), $context, [
    'event' => 'payment_failed',
]),
            routeName: 'admin.subscriptions.show',
            routeParams: ['subscription' => $subscription->id],
        );
    }

public function pastDue(Subscription $subscription, array $context = []): void
{
    $this->adminNotificationService->createBillingNotification(
        title: 'Subscription Marked Past Due',
            message: $this->buildSubscriptionMessage(
    subscription: $subscription,
                fallback: 'The subscription has entered past due status.'
            ),
            severity: 'warning',
            tenantId: (string) $subscription->tenant_id,
            contextPayload: array_merge($this->baseContext($subscription), $context, [
    'event' => 'past_due',
]),
            routeName: 'admin.subscriptions.show',
            routeParams: ['subscription' => $subscription->id],
        );
    }

public function suspended(Subscription $subscription, array $context = []): void
{
    $this->adminNotificationService->createBillingNotification(
        title: 'Subscription Suspended',
            message: $this->buildSubscriptionMessage(
    subscription: $subscription,
                fallback: 'The subscription has been suspended.'
            ),
            severity: 'error',
            tenantId: (string) $subscription->tenant_id,
            contextPayload: array_merge($this->baseContext($subscription), $context, [
    'event' => 'suspended',
]),
            routeName: 'admin.subscriptions.show',
            routeParams: ['subscription' => $subscription->id],
        );
    }

public function recovered(Subscription $subscription, array $context = []): void
{
    $this->adminNotificationService->createBillingNotification(
        title: 'Subscription Recovered',
            message: $this->buildSubscriptionMessage(
    subscription: $subscription,
                fallback: 'The subscription recovered successfully and is active again.'
            ),
            severity: 'success',
            tenantId: (string) $subscription->tenant_id,
            contextPayload: array_merge($this->baseContext($subscription), $context, [
    'event' => 'recovered',
]),
            routeName: 'admin.subscriptions.show',
            routeParams: ['subscription' => $subscription->id],
        );
    }

public function trialEnding(Subscription $subscription, array $context = []): void
{
    $this->adminNotificationService->createBillingNotification(
        title: 'Trial Ending Soon',
            message: $this->buildSubscriptionMessage(
    subscription: $subscription,
                fallback: 'The subscription trial is ending soon.'
            ),
            severity: 'warning',
            tenantId: (string) $subscription->tenant_id,
            contextPayload: array_merge($this->baseContext($subscription), $context, [
    'event' => 'trial_ending',
]),
            routeName: 'admin.subscriptions.show',
            routeParams: ['subscription' => $subscription->id],
        );
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
            'suspended_at' => optional($subscription->suspended_at)?->format('Y-m-d H:i:s'),
            'ends_at' => optional($subscription->ends_at)?->format('Y-m-d H:i:s'),
        ];
    }

protected function buildSubscriptionMessage(Subscription $subscription, string $fallback): string
{
    $tenantId = (string) $subscription->tenant_id;
    $planId = (string) $subscription->plan_id;
    $status = (string) $subscription->status;

    return "Tenant {$tenantId} / Subscription #{$subscription->id} / Plan {$planId} / Status {$status}. {$fallback}";
}
}
