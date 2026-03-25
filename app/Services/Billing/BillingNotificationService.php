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

public function create(
    string $event,
    string $title,
    string $severity,
    Subscription $subscription,
    ?string $message = null,
    array $contextPayload = []
): void {
    $this->adminNotificationService->create(new AdminNotificationData(
        type: 'billing',
            title: $title,
            message: $message ?: $this->defaultMessage($subscription, $event),
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

protected function defaultMessage(Subscription $subscription, string $event): string
{
    return "Tenant {$subscription->tenant_id} / Subscription #{$subscription->id} / Event {$event}.";
}
}
