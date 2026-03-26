<?php

namespace App\Services\Admin;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AdminTenantLifecycleService
{
    public function centralConnectionName(): string
    {
        return (string) (Config::get('tenancy.database.central_connection') ?: Config::get('database.default'));
    }

    public function latestSubscriptionByTenantId(string $tenantId): ?object
    {
        return DB::connection($this->centralConnectionName())
            ->table('subscriptions')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->first();
    }

    public function suspendLatestSubscription(string $tenantId): void
    {
        $subscription = $this->latestSubscriptionByTenantId($tenantId);

        if (! $subscription) {
            throw new RuntimeException('No linked subscription was found for this tenant.');
        }

        DB::connection($this->centralConnectionName())
            ->table('subscriptions')
            ->where('id', $subscription->id)
            ->update([
                'status' => 'suspended',
                'suspended_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function activateLatestSubscription(string $tenantId): void
    {
        $subscription = $this->latestSubscriptionByTenantId($tenantId);

        if (! $subscription) {
            throw new RuntimeException('No linked subscription was found for this tenant.');
        }

        $currentStatus = (string) ($subscription->status ?? '');

        if (in_array($currentStatus, ['cancelled', 'expired'], true)) {
            throw new RuntimeException('Cancelled or expired subscriptions cannot be re-activated from this screen.');
        }

        $trialEndsAt = $this->nullableCarbon($subscription->trial_ends_at ?? null);

        $hasPaidGatewaySignals =
            filled($subscription->gateway ?? null) ||
            filled($subscription->gateway_customer_id ?? null) ||
            filled($subscription->gateway_subscription_id ?? null);

        if ($hasPaidGatewaySignals) {
            $restoredStatus = 'active';
        } elseif ($trialEndsAt && $trialEndsAt->isFuture()) {
            $restoredStatus = 'trialing';
        } else {
            $restoredStatus = 'active';
        }

        DB::connection($this->centralConnectionName())
            ->table('subscriptions')
            ->where('id', $subscription->id)
            ->update([
                'status' => $restoredStatus,
                'suspended_at' => null,
                'updated_at' => now(),
            ]);
    }

    public function extendLatestTrial(string $tenantId, int $days): void
    {
        $subscription = $this->latestSubscriptionByTenantId($tenantId);

        if (! $subscription) {
            throw new RuntimeException('No linked subscription was found for this tenant.');
        }

        $trialEndsAt = $this->nullableCarbon($subscription->trial_ends_at ?? null);

        if (! $trialEndsAt) {
            throw new RuntimeException('This tenant does not have a trial end date to extend.');
        }

        $baseDate = $trialEndsAt->isFuture() ? $trialEndsAt->copy() : now();

        $newTrialEndsAt = $baseDate->addDays($days);

        DB::connection($this->centralConnectionName())
            ->table('subscriptions')
            ->where('id', $subscription->id)
            ->update([
                'trial_ends_at' => $newTrialEndsAt,
                'status' => 'trialing',
                'updated_at' => now(),
            ]);
    }

    protected function nullableCarbon(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        return Carbon::parse($value);
    }
}
