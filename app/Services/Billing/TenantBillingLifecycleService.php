<?php

namespace App\Services\Billing;

use App\Models\Subscription;
use App\Support\Billing\SubscriptionStatuses;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class TenantBillingLifecycleService
{
    public function __construct(
        protected BillingNotificationService $billingNotificationService
    ) {
    }

public function markAsPastDue(Subscription $subscription, ?CarbonInterface $at = null): Subscription
{
    $timestamp = $at ? Carbon::instance($at) : now();

    $previousStatus = (string) $subscription->status;

    $subscription->forceFill([
        'status' => SubscriptionStatuses::PAST_DUE,
        'past_due_started_at' => $subscription->past_due_started_at ?: $timestamp,
        'last_payment_failed_at' => $timestamp,
        'payment_failures_count' => (int) $subscription->payment_failures_count + 1,
    ])->save();

    if ($previousStatus !== SubscriptionStatuses::PAST_DUE) {
        $this->billingNotificationService->pastDue($subscription->fresh(), [
            'previous_status' => $previousStatus,
            'at' => $timestamp->format('Y-m-d H:i:s'),
        ]);
    }

    return $subscription->fresh();
}

public function markAsSuspended(Subscription $subscription, ?CarbonInterface $at = null): Subscription
{
    $timestamp = $at ? Carbon::instance($at) : now();

    $previousStatus = (string) $subscription->status;

    $subscription->forceFill([
        'status' => SubscriptionStatuses::SUSPENDED,
        'suspended_at' => $timestamp,
    ])->save();

    if ($previousStatus !== SubscriptionStatuses::SUSPENDED) {
        $this->billingNotificationService->suspended($subscription->fresh(), [
            'previous_status' => $previousStatus,
            'at' => $timestamp->format('Y-m-d H:i:s'),
        ]);
    }

    return $subscription->fresh();
}

public function markAsRecovered(Subscription $subscription, ?CarbonInterface $at = null): Subscription
{
    $timestamp = $at ? Carbon::instance($at) : now();

    $previousStatus = (string) $subscription->status;

    $subscription->forceFill([
        'status' => SubscriptionStatuses::ACTIVE,
        'last_payment_failed_at' => null,
        'past_due_started_at' => null,
        'suspended_at' => null,
        'payment_failures_count' => 0,
    ])->save();

    if ($previousStatus !== SubscriptionStatuses::ACTIVE) {
        $this->billingNotificationService->recovered($subscription->fresh(), [
            'previous_status' => $previousStatus,
            'at' => $timestamp->format('Y-m-d H:i:s'),
        ]);
    }

    return $subscription->fresh();
}

public function emitTrialEndingSoon(Subscription $subscription, ?CarbonInterface $at = null): void
{
    $timestamp = $at ? Carbon::instance($at) : now();

    $this->billingNotificationService->trialEnding($subscription->fresh(), [
        'at' => $timestamp->format('Y-m-d H:i:s'),
        'trial_ends_at' => optional($subscription->trial_ends_at)?->format('Y-m-d H:i:s'),
        ]);
    }
}
