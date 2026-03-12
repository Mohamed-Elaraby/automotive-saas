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

    public function getLimit(string $tenantId, string $field): ?int
    {
        $plan = $this->getCurrentPlan($tenantId);

        if (! $plan) {
            return null;
        }

        return $plan->{$field} ?? null;
    }

    public function getLimitSummary(string $tenantId, string $field, int $currentCount): array
    {
        $limit = $this->getLimit($tenantId, $field);

        return [
            'limit' => $limit,
            'current' => $currentCount,
            'remaining' => is_null($limit) ? null : max($limit - $currentCount, 0),
            'unlimited' => is_null($limit),
        ];
    }
}
