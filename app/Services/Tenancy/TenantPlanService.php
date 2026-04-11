<?php

namespace App\Services\Tenancy;

use App\Models\Plan;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantPlanService
{
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

        $manifest = app(WorkspaceManifestService::class);
        $preferredFamily = $manifest->defaultFamily();

        $preferredSubscription = $this->productSubscriptionBaseQuery($connection, $tenantId)
            ->get()
            ->first(function (object $subscription) use ($manifest, $preferredFamily): bool {
                return $manifest->resolveFamilyFromText(strtolower(implode(' ', array_filter([
                    (string) ($subscription->product_code ?? ''),
                    (string) ($subscription->product_slug ?? ''),
                    (string) ($subscription->product_name ?? ''),
                ])))) === $preferredFamily;
            });

        if ($preferredSubscription) {
            return $preferredSubscription;
        }

        return $this->productSubscriptionBaseQuery($connection, $tenantId)
            ->first();
    }

    protected function productSubscriptionBaseQuery(string $connection, string $tenantId): Builder
    {
        return DB::connection($connection)
            ->table('tenant_product_subscriptions')
            ->join('products', 'products.id', '=', 'tenant_product_subscriptions.product_id')
            ->where('tenant_product_subscriptions.tenant_id', $tenantId)
            ->whereIn('tenant_product_subscriptions.status', ['active', 'trialing', 'past_due', 'canceled'])
            ->orderByRaw("CASE WHEN tenant_product_subscriptions.status = 'active' THEN 0 WHEN tenant_product_subscriptions.status = 'trialing' THEN 1 WHEN tenant_product_subscriptions.status = 'past_due' THEN 2 ELSE 3 END")
            ->orderByDesc('tenant_product_subscriptions.id')
            ->select(
                'tenant_product_subscriptions.*',
                'products.code as product_code',
                'products.slug as product_slug',
                'products.name as product_name'
            );
    }
}
