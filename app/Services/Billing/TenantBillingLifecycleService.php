<?php

namespace App\Services\Billing;

use App\Support\Billing\SubscriptionStatuses;
use Carbon\Carbon;
use Stancl\Tenancy\Contracts\Tenant;

class TenantBillingLifecycleService
{
    public function resolveState(?object $subscription): array
    {
        $now = now();

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
                        'Your trial has ended and the tenant is currently inside the grace period.',
                        false,
                        true,
                        $trialEndsAt,
                        $graceEndsAt
                    );
                }

                return $this->buildState(
                    SubscriptionStatuses::EXPIRED,
                    'Your trial period has ended.',
                    false,
                    true,
                    $trialEndsAt,
                    $graceEndsAt
                );
            }

            return $this->buildState(
                SubscriptionStatuses::TRIALING,
                'Your tenant is currently on a trial subscription.',
                true,
                true,
                $trialEndsAt,
                $graceEndsAt
            );
        }

        if ($status === SubscriptionStatuses::ACTIVE) {
            return $this->buildState(
                SubscriptionStatuses::ACTIVE,
                'Your subscription is active.',
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
                    'Payment is overdue and the tenant is currently inside the grace period.',
                    false,
                    false,
                    $endsAt,
                    $graceEndsAt
                );
            }

            return $this->buildState(
                SubscriptionStatuses::PAST_DUE,
                'Payment is overdue.',
                false,
                false,
                $endsAt,
                $graceEndsAt
            );
        }

        if ($status === SubscriptionStatuses::SUSPENDED) {
            return $this->buildState(
                SubscriptionStatuses::SUSPENDED,
                'This tenant is suspended until billing is resolved.',
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
                    'The subscription is cancelled and will remain accessible until its end date.',
                    true,
                    false,
                    $endsAt,
                    $graceEndsAt
                );
            }

            return $this->buildState(
                SubscriptionStatuses::EXPIRED,
                'The subscription has been cancelled and access has expired.',
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

    protected function toCarbon(mixed $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        return $value instanceof Carbon ? $value : Carbon::parse($value);
    }
}
