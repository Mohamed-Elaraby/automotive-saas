<?php

namespace App\Services\Tenancy;

use App\Models\Plan;
use Illuminate\Support\Facades\DB;

class TenantPlanService
{
    protected function centralConnection(): string
    {
        return config('tenancy.database.central_connection') ?? config('database.default');
    }

    public function getCurrentSubscription(string $tenantId): ?object
    {
        return DB::connection($this->centralConnection())
            ->table('subscriptions')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->first();
    }

    public function getCurrentPlan(string $tenantId): ?Plan
    {
        $subscription = $this->getCurrentSubscription($tenantId);

        if (! $subscription || empty($subscription->plan_id)) {
            return null;
        }

        return Plan::on($this->centralConnection())->find($subscription->plan_id);
    }

    public function getLimit(string $tenantId, string $field): int|null
    {
        $plan = $this->getCurrentPlan($tenantId);

        if (! $plan) {
            return null;
        }

return $plan->{$field} ?? null;
}

public function getTenantUsersCount(): int
{
    return \App\Models\User::query()->count();
}

public function getUserLimitDecision(string $tenantId): array
{
    $plan = $this->getCurrentPlan($tenantId);
    $limit = $this->getLimit($tenantId, 'max_users');
    $current = $this->getTenantUsersCount();

    if ($limit === null) {
        return [
            'allowed' => true,
            'reason' => 'no_limit',
            'current' => $current,
            'limit' => null,
            'plan' => $plan,
        ];
    }

    return [
        'allowed' => $current < $limit,
        'reason' => $current < $limit ? 'within_limit' : 'limit_reached',
        'current' => $current,
        'limit' => $limit,
        'plan' => $plan,
    ];
}

public function canCreateTenantUser(string $tenantId): bool
{
    return $this->getUserLimitDecision($tenantId)['allowed'];
}
}
