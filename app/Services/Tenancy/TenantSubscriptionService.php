<?php

namespace App\Services\Tenancy;

use App\Support\SubscriptionStatus;
use Illuminate\Support\Facades\DB;

class TenantSubscriptionService
{
    public function getCurrentSubscription(string $tenantId): ?object
    {
        $centralConnection = config('tenancy.database.central_connection') ?? config('database.default');

        return DB::connection($centralConnection)
            ->table('subscriptions')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->first();
    }

    public function getAccessDecision(string $tenantId): array
    {
        $subscription = $this->getCurrentSubscription($tenantId);

        if (! $subscription) {
            return [
                'allowed' => false,
                'reason' => 'missing_subscription',
                'subscription' => null,
            ];
        }

        $status = $subscription->status ?? null;
        $trialEndsAt = $subscription->trial_ends_at ?? null;

        // Active subscription always allowed
        if ($status === SubscriptionStatus::ACTIVE) {
            return [
                'allowed' => true,
                'reason' => 'active',
                'subscription' => $subscription,
            ];
        }

        // Trialing is allowed only if still within trial period
        if ($status === SubscriptionStatus::TRIALING) {
            if ($trialEndsAt && now()->greaterThan($trialEndsAt)) {
                return [
                    'allowed' => false,
                    'reason' => SubscriptionStatus::EXPIRED,
                    'subscription' => $subscription,
                ];
            }

            return [
                'allowed' => true,
                'reason' => 'trialing',
                'subscription' => $subscription,
            ];
        }

        return [
            'allowed' => false,
            'reason' => $status ?: 'unknown_status',
            'subscription' => $subscription,
        ];
    }
}
