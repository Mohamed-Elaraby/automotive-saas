<?php

namespace App\Services\Billing;

use App\Support\Billing\SubscriptionStatuses;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TenantBillingLifecycleService
{
    protected function centralConnection(): string
    {
        return config('tenancy.database.central_connection') ?? config('database.default');
    }

    protected function subscriptionsTable()
    {
        return DB::connection($this->centralConnection())->table('subscriptions');
    }

    protected function findSubscriptionById(int|string $subscriptionId): ?object
    {
        return $this->subscriptionsTable()
            ->where('id', $subscriptionId)
            ->first();
    }

protected function toCarbon(mixed $value): ?Carbon
{
    if (! $value) {
        return null;
    }

    return $value instanceof Carbon ? $value : Carbon::parse($value);
}

protected function buildState(
    string $status,
    string $message,
    bool $allowAccess,
    bool $isTrial = false,
    ?Carbon $periodEndsAt = null,
    ?Carbon $graceEndsAt = null
): array {
    return [
        'status' => $status,
        'message' => $message,
        'allow_access' => $allowAccess,
        'is_trial' => $isTrial,
        'period_ends_at' => $periodEndsAt,
        'grace_ends_at' => $graceEndsAt,
    ];
}

public function resolveState(?object $subscription): array
{
    if (! $subscription) {
        return $this->buildState(
            SubscriptionStatuses::EXPIRED,
            'No active subscription record was found for this tenant.',
            false,
            false
        );
    }

    $status = (string) ($subscription->status ?? '');
    $trialEndsAt = $this->toCarbon($subscription->trial_ends_at ?? null);
    $graceEndsAt = $this->toCarbon($subscription->grace_ends_at ?? null);
    $endsAt = $this->toCarbon($subscription->ends_at ?? null);

    if ($status === SubscriptionStatuses::TRIALING) {
        if ($trialEndsAt && $trialEndsAt->isPast()) {
            if ($graceEndsAt && $graceEndsAt->isFuture()) {
                return $this->buildState(
                    SubscriptionStatuses::GRACE_PERIOD,
                    'Your free trial has ended. Access is temporarily restricted until billing is completed.',
                    false,
                    true,
                    $trialEndsAt,
                    $graceEndsAt
                );
            }

            return $this->buildState(
                SubscriptionStatuses::EXPIRED,
                'Your trial period has ended and no active paid subscription is available.',
                false,
                true,
                $trialEndsAt,
                $graceEndsAt
            );
        }

        return $this->buildState(
            SubscriptionStatuses::TRIALING,
            'Your tenant is currently on a free trial period.',
            true,
            true,
            $trialEndsAt,
            $graceEndsAt
        );
    }

    if ($status === SubscriptionStatuses::ACTIVE) {
        return $this->buildState(
            SubscriptionStatuses::ACTIVE,
            'Your subscription is active and tenant access is allowed.',
            true,
            false,
            $endsAt,
            $graceEndsAt
        );
    }

    if ($status === SubscriptionStatuses::PAST_DUE) {
        if ($graceEndsAt && $graceEndsAt->isFuture()) {
            return $this->buildState(
                SubscriptionStatuses::GRACE_PERIOD,
                'Your payment is overdue. The tenant is currently inside the grace period.',
                false,
                false,
                $endsAt,
                $graceEndsAt
            );
        }

        return $this->buildState(
            SubscriptionStatuses::PAST_DUE,
            'Your payment is overdue and billing action is required.',
            false,
            false,
            $endsAt,
            $graceEndsAt
        );
    }

    if ($status === SubscriptionStatuses::SUSPENDED) {
        return $this->buildState(
            SubscriptionStatuses::SUSPENDED,
            'This tenant is suspended until billing is resolved and the subscription is reactivated.',
            false,
            false,
            $endsAt,
            $graceEndsAt
        );
    }

    if ($status === SubscriptionStatuses::CANCELLED) {
        if ($endsAt && $endsAt->isFuture()) {
            return $this->buildState(
                SubscriptionStatuses::CANCELLED,
                'The subscription is cancelled but remains active until the current billing period ends.',
                true,
                false,
                $endsAt,
                $graceEndsAt
            );
        }

        return $this->buildState(
            SubscriptionStatuses::EXPIRED,
            'The subscription has been cancelled and tenant access has expired.',
            false,
            false,
            $endsAt,
            $graceEndsAt
        );
    }

    return $this->buildState(
        SubscriptionStatuses::EXPIRED,
        'The subscription state is not recognized as active.',
        false,
        false,
        $endsAt,
        $graceEndsAt
    );
}

public function shouldAllowAccess(?object $subscription): bool
{
    return (bool) $this->resolveState($subscription)['allow_access'];
}

public function shouldRedirectToBilling(?object $subscription): bool
{
    return ! $this->shouldAllowAccess($subscription);
}

public function markAsPastDue(?object $subscription, ?Carbon $failedAt = null): ?object
{
    if (! $subscription || empty($subscription->id)) {
        return $subscription;
    }

    $failedAt ??= now();
    $graceDays = (int) config('billing.grace_period_days', 3);
    $graceEndsAt = (clone $failedAt)->addDays($graceDays);

    DB::connection($this->centralConnection())->transaction(function () use ($subscription, $failedAt, $graceEndsAt) {
        $fresh = $this->findSubscriptionById($subscription->id);

        if (! $fresh) {
            return;
        }

        if (($fresh->status ?? null) === SubscriptionStatuses::TRIALING) {
            return;
        }

        $this->subscriptionsTable()
            ->where('id', $subscription->id)
            ->update([
                'status' => SubscriptionStatuses::PAST_DUE,
                'last_payment_failed_at' => $failedAt,
                'past_due_started_at' => $fresh->past_due_started_at ?? $failedAt,
                'grace_ends_at' => $graceEndsAt,
                'suspended_at' => null,
                'payment_failures_count' => ((int) ($fresh->payment_failures_count ?? 0)) + 1,
                'updated_at' => now(),
            ]);
    });

    return $this->findSubscriptionById($subscription->id);
}

public function markAsRecovered(?object $subscription, ?Carbon $recoveredAt = null): ?object
{
    if (! $subscription || empty($subscription->id)) {
        return $subscription;
    }

    $recoveredAt ??= now();

    DB::connection($this->centralConnection())->transaction(function () use ($subscription, $recoveredAt) {
        $fresh = $this->findSubscriptionById($subscription->id);

        if (! $fresh) {
            return;
        }

        $this->subscriptionsTable()
            ->where('id', $subscription->id)
            ->update([
                'status' => SubscriptionStatuses::ACTIVE,
                'trial_ends_at' => null,
                'last_payment_failed_at' => null,
                'past_due_started_at' => null,
                'grace_ends_at' => null,
                'suspended_at' => null,
                'payment_failures_count' => 0,
                'updated_at' => $recoveredAt,
            ]);
    });

    return $this->findSubscriptionById($subscription->id);
}

public function markAsSuspended(?object $subscription, ?Carbon $suspendedAt = null): ?object
{
    if (! $subscription || empty($subscription->id)) {
        return $subscription;
    }

    $suspendedAt ??= now();

    DB::connection($this->centralConnection())->transaction(function () use ($subscription, $suspendedAt) {
        $fresh = $this->findSubscriptionById($subscription->id);

        if (! $fresh) {
            return;
        }

        $this->subscriptionsTable()
            ->where('id', $subscription->id)
            ->update([
                'status' => SubscriptionStatuses::SUSPENDED,
                'suspended_at' => $fresh->suspended_at ?? $suspendedAt,
                'updated_at' => now(),
            ]);
    });

    return $this->findSubscriptionById($subscription->id);
}

public function markAsExpired(?object $subscription, ?Carbon $expiredAt = null): ?object
{
    if (! $subscription || empty($subscription->id)) {
        return $subscription;
    }

    $expiredAt ??= now();

    DB::connection($this->centralConnection())->transaction(function () use ($subscription, $expiredAt) {
        $fresh = $this->findSubscriptionById($subscription->id);

        if (! $fresh) {
            return;
        }

        $this->subscriptionsTable()
            ->where('id', $subscription->id)
            ->update([
                'status' => SubscriptionStatuses::EXPIRED,
                'ends_at' => $fresh->ends_at ?? $expiredAt,
                'suspended_at' => $fresh->suspended_at ?? $expiredAt,
                'updated_at' => now(),
            ]);
    });

    return $this->findSubscriptionById($subscription->id);
}

public function markAsCancelled(?object $subscription, ?Carbon $cancelledAt = null, ?Carbon $endsAt = null): ?object
{
    if (! $subscription || empty($subscription->id)) {
        return $subscription;
    }

    $cancelledAt ??= now();

    DB::connection($this->centralConnection())->transaction(function () use ($subscription, $cancelledAt, $endsAt) {
        $fresh = $this->findSubscriptionById($subscription->id);

        if (! $fresh) {
            return;
        }

        $this->subscriptionsTable()
            ->where('id', $subscription->id)
            ->update([
                'status' => SubscriptionStatuses::CANCELLED,
                'cancelled_at' => $cancelledAt,
                'ends_at' => $endsAt ?? $fresh->ends_at,
                'updated_at' => now(),
            ]);
    });

    return $this->findSubscriptionById($subscription->id);
}

public function runDailyLifecycle(): array
{
    $processed = [
        'suspended' => 0,
        'expired_cancelled' => 0,
    ];

    $this->subscriptionsTable()
        ->where('status', SubscriptionStatuses::PAST_DUE)
        ->whereNotNull('grace_ends_at')
        ->where('grace_ends_at', '<=', now())
        ->orderBy('id')
        ->chunk(100, function ($subscriptions) use (&$processed) {
            foreach ($subscriptions as $subscription) {
                $before = $subscription->status ?? null;

                $this->markAsSuspended($subscription);

                $fresh = $this->findSubscriptionById($subscription->id);

                if ($before !== SubscriptionStatuses::SUSPENDED
                    && ($fresh->status ?? null) === SubscriptionStatuses::SUSPENDED) {
                    $processed['suspended']++;
                }
            }
        });

    $this->subscriptionsTable()
        ->where('status', SubscriptionStatuses::CANCELLED)
        ->whereNotNull('ends_at')
        ->where('ends_at', '<=', now())
        ->orderBy('id')
        ->chunk(100, function ($subscriptions) use (&$processed) {
            foreach ($subscriptions as $subscription) {
                $before = $subscription->status ?? null;

                $this->markAsExpired($subscription);

                $fresh = $this->findSubscriptionById($subscription->id);

                if ($before !== SubscriptionStatuses::EXPIRED
                    && ($fresh->status ?? null) === SubscriptionStatuses::EXPIRED) {
                    $processed['expired_cancelled']++;
                }
            }
        });

    return $processed;
}
}
