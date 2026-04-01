<?php

namespace App\Services\Admin;

use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\TenantBillingLifecycleService;
use App\Support\Billing\SubscriptionStatuses;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AdminSubscriptionControlService
{
    public function __construct(
        protected TenantBillingLifecycleService $tenantBillingLifecycleService
    ) {
    }

    public function forceLifecycle(int $subscriptionId, string $targetStatus): array
    {
        $subscription = $this->findSubscriptionOrFail($subscriptionId);

        if ($this->isStripeLinked($subscription)) {
            throw new RuntimeException(
                'This subscription is linked to Stripe. Use Stripe sync or Stripe-side lifecycle actions instead of forcing local lifecycle changes.'
            );
        }

        $before = $subscription->fresh();

        $fresh = match ($targetStatus) {
            SubscriptionStatuses::TRIALING => $this->markAsTrialing($subscription),
            SubscriptionStatuses::ACTIVE => $this->markAsActive($subscription),
            SubscriptionStatuses::PAST_DUE => $this->tenantBillingLifecycleService->markAsPastDue($subscription),
            SubscriptionStatuses::SUSPENDED => $this->tenantBillingLifecycleService->markAsSuspended($subscription),
            SubscriptionStatuses::CANCELLED => $this->tenantBillingLifecycleService->markAsCancelled(
                $subscription,
                now(),
                $subscription->ends_at ? Carbon::parse($subscription->ends_at) : now()
            ),
            SubscriptionStatuses::EXPIRED => $this->tenantBillingLifecycleService->markAsExpired($subscription),
            default => throw new RuntimeException('Unsupported lifecycle state requested.'),
        };

        return [
            'before' => $before?->fresh(),
            'after' => $fresh?->fresh(),
            'message' => 'Subscription lifecycle state was updated successfully.',
            'action' => 'force_lifecycle',
            'target_status' => $targetStatus,
        ];
    }

    public function applyQuickAction(int $subscriptionId, string $action): array
    {
        $subscription = $this->findSubscriptionOrFail($subscriptionId);

        if ($this->isStripeLinked($subscription)) {
            throw new RuntimeException(
                'This subscription is linked to Stripe. Use Stripe sync or Stripe-side lifecycle actions instead of manual local actions.'
            );
        }

        $before = $subscription->fresh();

        $fresh = match ($action) {
            'cancel' => $this->tenantBillingLifecycleService->markAsCancelled(
                $subscription,
                now(),
                $subscription->ends_at ? Carbon::parse($subscription->ends_at) : now()
            ),
            'resume' => $this->markAsActive($subscription),
            'renew' => $this->renewLocally($subscription),
            default => throw new RuntimeException('Unsupported manual subscription action requested.'),
        };

        return [
            'before' => $before?->fresh(),
            'after' => $fresh?->fresh(),
            'message' => match ($action) {
                'cancel' => 'Subscription was cancelled locally.',
                'resume' => 'Subscription was resumed locally.',
                'renew' => 'Subscription was renewed locally.',
                default => 'Subscription action completed.',
            },
            'action' => $action,
        ];
    }

    public function updateTimestamps(int $subscriptionId, array $timestamps): array
    {
        $subscription = $this->findSubscriptionOrFail($subscriptionId);

        if ($this->isStripeLinked($subscription)) {
            throw new RuntimeException(
                'This subscription is linked to Stripe. Timestamp overrides are blocked to avoid lifecycle drift against Stripe.'
            );
        }

        $before = $subscription->fresh();

        $payload = [
            'trial_ends_at' => $this->normalizeTimestamp($timestamps['trial_ends_at'] ?? null),
            'grace_ends_at' => $this->normalizeTimestamp($timestamps['grace_ends_at'] ?? null),
            'ends_at' => $this->normalizeTimestamp($timestamps['ends_at'] ?? null),
            'updated_at' => now(),
        ];

        DB::connection($subscription->getConnectionName())
            ->table($subscription->getTable())
            ->where('id', $subscription->id)
            ->update($payload);

        $after = $subscription->fresh();

        return [
            'before' => $before?->fresh(),
            'after' => $after?->fresh(),
            'message' => 'Subscription lifecycle timestamps were updated successfully.',
            'action' => 'update_timestamps',
        ];
    }

    protected function findSubscriptionOrFail(int $subscriptionId): Subscription
    {
        $subscription = Subscription::query()->find($subscriptionId);

        if (! $subscription) {
            throw new RuntimeException('The subscription record was not found.');
        }

        return $subscription;
    }

    protected function isStripeLinked(Subscription $subscription): bool
    {
        return ($subscription->gateway ?? null) === 'stripe'
            || filled($subscription->gateway_subscription_id)
            || filled($subscription->gateway_customer_id);
    }

    protected function markAsTrialing(Subscription $subscription): Subscription
    {
        $trialEndsAt = $subscription->trial_ends_at
            ? Carbon::parse($subscription->trial_ends_at)
            : now()->addDays((int) config('billing.trial_days', 14));

        DB::connection($subscription->getConnectionName())
            ->table($subscription->getTable())
            ->where('id', $subscription->id)
            ->update([
                'status' => SubscriptionStatuses::TRIALING,
                'trial_ends_at' => $trialEndsAt,
                'grace_ends_at' => null,
                'last_payment_failed_at' => null,
                'past_due_started_at' => null,
                'suspended_at' => null,
                'cancelled_at' => null,
                'ends_at' => null,
                'payment_failures_count' => 0,
                'updated_at' => now(),
            ]);

        return $subscription->fresh();
    }

    protected function markAsActive(Subscription $subscription): Subscription
    {
        DB::connection($subscription->getConnectionName())
            ->table($subscription->getTable())
            ->where('id', $subscription->id)
            ->update([
                'status' => SubscriptionStatuses::ACTIVE,
                'grace_ends_at' => null,
                'last_payment_failed_at' => null,
                'past_due_started_at' => null,
                'suspended_at' => null,
                'cancelled_at' => null,
                'payment_failures_count' => 0,
                'updated_at' => now(),
            ]);

        return $subscription->fresh();
    }

    protected function renewLocally(Subscription $subscription): Subscription
    {
        $periodEndsAt = $this->renewalEndsAt($subscription);

        DB::connection($subscription->getConnectionName())
            ->table($subscription->getTable())
            ->where('id', $subscription->id)
            ->update([
                'status' => SubscriptionStatuses::ACTIVE,
                'grace_ends_at' => null,
                'last_payment_failed_at' => null,
                'past_due_started_at' => null,
                'suspended_at' => null,
                'cancelled_at' => null,
                'ends_at' => $periodEndsAt,
                'payment_failures_count' => 0,
                'updated_at' => now(),
            ]);

        return $subscription->fresh();
    }

    protected function renewalEndsAt(Subscription $subscription): Carbon
    {
        $plan = $subscription->plan_id ? Plan::query()->find($subscription->plan_id) : null;
        $base = now();

        return match ($plan->billing_period ?? null) {
            'yearly', 'annual' => $base->copy()->addYear(),
            default => $base->copy()->addMonth(),
        };
    }

    protected function normalizeTimestamp(mixed $value): ?Carbon
    {
        if (! filled($value)) {
            return null;
        }

        return $value instanceof Carbon ? $value : Carbon::parse((string) $value);
    }
}
