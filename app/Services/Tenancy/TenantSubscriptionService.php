<?php

namespace App\Services\Tenancy;

use Illuminate\Support\Facades\DB;
use App\Support\SubscriptionStatus;

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

        if (! is_string($status) || ! SubscriptionStatus::allowsAccess($status)) {
            return [
                'allowed' => false,
                'reason' => $status ?: 'unknown_status',
                'subscription' => $subscription,
            ];
        }

        return [
            'allowed' => true,
            'reason' => 'ok',
            'subscription' => $subscription,
        ];
    }
}
