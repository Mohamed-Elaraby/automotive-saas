<?php

namespace App\Services\Tenancy;

use App\Models\Plan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantPlanService
{
    protected const PRODUCT_CODE = 'automotive_service';

    protected function centralConnection(): string
    {
        return config('tenancy.database.central_connection') ?? config('database.default');
    }

    public function getCurrentSubscription(string $tenantId): ?object
    {
        $productSubscription = $this->currentProductSubscription($tenantId);

        if ($productSubscription) {
            return $productSubscription;
        }

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

    protected function currentProductSubscription(string $tenantId): ?object
    {
        $connection = $this->centralConnection();

        if (
            ! Schema::connection($connection)->hasTable('tenant_product_subscriptions')
            || ! Schema::connection($connection)->hasTable('products')
        ) {
            return null;
        }

        return DB::connection($connection)
            ->table('tenant_product_subscriptions')
            ->join('products', 'products.id', '=', 'tenant_product_subscriptions.product_id')
            ->where('tenant_product_subscriptions.tenant_id', $tenantId)
            ->where('products.code', self::PRODUCT_CODE)
            ->orderByDesc('tenant_product_subscriptions.id')
            ->select('tenant_product_subscriptions.*')
            ->first();
    }
}
