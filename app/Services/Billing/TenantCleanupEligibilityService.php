<?php

namespace App\Services\Billing;

use App\Support\Billing\SubscriptionStatuses;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TenantCleanupEligibilityService
{
    public function eligibleForAutomaticCleanup(string $tenantId, ?string $connection = null): bool
    {
        return $this->evaluateAutomaticCleanup($tenantId, $connection)['eligible'];
    }

    public function evaluateAutomaticCleanup(string $tenantId, ?string $connection = null): array
    {
        $connection ??= $this->centralConnection();

        $subscriptions = DB::connection($connection)
            ->table('subscriptions')
            ->where('tenant_id', $tenantId)
            ->get();

        if ($subscriptions->isEmpty()) {
            return [
                'eligible' => true,
                'reason' => 'no_subscriptions',
            ];
        }

        if ($this->hasStripeLinkage($subscriptions)) {
            return [
                'eligible' => false,
                'reason' => 'stripe_linked',
            ];
        }

        $trialExpiredOnly = $subscriptions->every(function ($subscription) {
            return ($subscription->status ?? null) === SubscriptionStatuses::EXPIRED
                && ! empty($subscription->trial_ends_at);
        });

        if (! $trialExpiredOnly) {
            return [
                'eligible' => false,
                'reason' => 'non_trial_or_non_terminal_subscription',
            ];
        }

        return [
            'eligible' => true,
            'reason' => 'expired_trials_only',
        ];
    }

    protected function hasStripeLinkage(Collection $subscriptions): bool
    {
        return $subscriptions->contains(function ($subscription) {
            return (string) ($subscription->gateway ?? '') === 'stripe'
                || filled($subscription->gateway_customer_id ?? null)
                || filled($subscription->gateway_subscription_id ?? null)
                || filled($subscription->gateway_checkout_session_id ?? null)
                || filled($subscription->gateway_price_id ?? null);
        });
    }

    protected function centralConnection(): string
    {
        return (string) (config('tenancy.database.central_connection') ?? config('database.default'));
    }
}
