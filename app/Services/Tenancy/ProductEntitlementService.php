<?php

namespace App\Services\Tenancy;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductEntitlementService
{
    protected function centralConnection(): string
    {
        return config('tenancy.database.central_connection') ?? config('database.default');
    }

    public function subscriptionFor(string $tenantId, string $productKey): ?object
    {
        $tenantId = trim($tenantId);
        $productKey = trim($productKey);
        $connection = $this->centralConnection();

        if (
            $tenantId === ''
            || $productKey === ''
            || ! Schema::connection($connection)->hasTable('tenant_product_subscriptions')
            || ! Schema::connection($connection)->hasTable('products')
        ) {
            return null;
        }

        $query = DB::connection($connection)
            ->table('tenant_product_subscriptions')
            ->join('products', 'products.id', '=', 'tenant_product_subscriptions.product_id')
            ->leftJoin('plans', 'plans.id', '=', 'tenant_product_subscriptions.plan_id')
            ->where('tenant_product_subscriptions.tenant_id', $tenantId)
            ->where(function ($query) use ($connection, $productKey): void {
                $query->where('products.code', $productKey);

                if (Schema::connection($connection)->hasColumn('tenant_product_subscriptions', 'product_key')) {
                    $query->orWhere('tenant_product_subscriptions.product_key', $productKey);
                }
            });

        return $query
            ->orderByRaw("CASE WHEN tenant_product_subscriptions.status = 'active' THEN 0 WHEN tenant_product_subscriptions.status = 'trialing' THEN 1 ELSE 2 END")
            ->orderByDesc('tenant_product_subscriptions.id')
            ->select($this->subscriptionSelectColumns($connection))
            ->first();
    }

    public function isSubscribed(string $tenantId, string $productKey): bool
    {
        $subscription = $this->subscriptionFor($tenantId, $productKey);

        if (! $subscription) {
            return false;
        }

        return in_array((string) ($subscription->status ?? ''), ['active', 'trialing'], true);
    }

    public function includedSeats(string $tenantId, string $productKey): ?int
    {
        return $this->integerLimit($tenantId, $productKey, 'included_seats');
    }

    public function seatLimit(string $tenantId, string $productKey): ?int
    {
        $subscription = $this->subscriptionFor($tenantId, $productKey);

        if (! $subscription) {
            return null;
        }

        $includedSeats = $this->nullableInteger($subscription->included_seats ?? null)
            ?? $this->integerLimitFromPlan((int) ($subscription->plan_id ?? 0), $productKey, 'included_seats');

        if ($includedSeats === null) {
            return null;
        }

        $subscriptionExtraSeats = $this->nullableInteger($subscription->extra_seats ?? null) ?? 0;

        return $includedSeats
            + $subscriptionExtraSeats
            + $this->activeAddonQuantity($tenantId, $productKey, 'extra_user_seat');
    }

    public function branchLimit(string $tenantId, string $productKey): ?int
    {
        $subscription = $this->subscriptionFor($tenantId, $productKey);

        if (! $subscription) {
            return null;
        }

        $subscriptionLimit = $this->nullableInteger($subscription->branch_limit ?? null);

        if ($subscriptionLimit !== null) {
            return $subscriptionLimit + $this->activeAddonQuantity($tenantId, $productKey, 'extra_branch');
        }

        return $this->integerLimitFromPlan((int) ($subscription->plan_id ?? 0), $productKey, 'branch_limit');
    }

    public function integerLimit(string $tenantId, string $productKey, string $limitKey): ?int
    {
        $subscription = $this->subscriptionFor($tenantId, $productKey);

        if (! $subscription) {
            return null;
        }

        if ($limitKey === 'included_seats') {
            $subscriptionLimit = $this->nullableInteger($subscription->included_seats ?? null);

            if ($subscriptionLimit !== null) {
                return $subscriptionLimit;
            }
        }

        if ($limitKey === 'branch_limit') {
            $subscriptionLimit = $this->nullableInteger($subscription->branch_limit ?? null);

            if ($subscriptionLimit !== null) {
                return $subscriptionLimit;
            }
        }

        return $this->integerLimitFromPlan((int) ($subscription->plan_id ?? 0), $productKey, $limitKey);
    }

    public function activeAddonQuantity(string $tenantId, string $productKey, string $addonKey): int
    {
        $connection = $this->centralConnection();

        if (
            trim($tenantId) === ''
            || trim($productKey) === ''
            || trim($addonKey) === ''
            || ! Schema::connection($connection)->hasTable('subscription_addons')
        ) {
            return 0;
        }

        return (int) DB::connection($connection)
            ->table('subscription_addons')
            ->where('tenant_id', $tenantId)
            ->where('product_key', $productKey)
            ->where('addon_key', $addonKey)
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->sum('quantity');
    }

    public function featureEnabled(string $tenantId, string $productKey, string $featureKey): bool
    {
        return $this->activeAddonQuantity($tenantId, $productKey, $featureKey) > 0
            || $this->planHasBillingFeature($tenantId, $productKey, $featureKey);
    }

    protected function integerLimitFromPlan(int $planId, string $productKey, string $limitKey): ?int
    {
        $connection = $this->centralConnection();

        if (
            $planId <= 0
            || ! Schema::connection($connection)->hasTable('plan_limits')
        ) {
            return null;
        }

        $value = DB::connection($connection)
            ->table('plan_limits')
            ->where('plan_id', $planId)
            ->where('product_key', $productKey)
            ->where('limit_key', $limitKey)
            ->value('limit_value');

        return $this->nullableInteger($value);
    }

    protected function planHasBillingFeature(string $tenantId, string $productKey, string $featureKey): bool
    {
        $subscription = $this->subscriptionFor($tenantId, $productKey);
        $connection = $this->centralConnection();

        if (
            ! $subscription
            || ! Schema::connection($connection)->hasTable('billing_feature_plan')
            || ! Schema::connection($connection)->hasTable('billing_features')
        ) {
            return false;
        }

        return DB::connection($connection)
            ->table('billing_feature_plan')
            ->join('billing_features', 'billing_features.id', '=', 'billing_feature_plan.billing_feature_id')
            ->where('billing_feature_plan.plan_id', (int) $subscription->plan_id)
            ->where(function ($query) use ($featureKey): void {
                $query->where('billing_features.slug', $featureKey)
                    ->orWhere('billing_features.name', $featureKey);
            })
            ->where('billing_features.is_active', true)
            ->exists();
    }

    protected function subscriptionSelectColumns(string $connection): array
    {
        $columns = [
            'tenant_product_subscriptions.id',
            'tenant_product_subscriptions.tenant_id',
            'tenant_product_subscriptions.product_id',
            'tenant_product_subscriptions.plan_id',
            'tenant_product_subscriptions.status',
            'products.code as resolved_product_key',
            'plans.max_users as plan_max_users',
            'plans.max_branches as plan_max_branches',
        ];

        foreach ([
            'product_key',
            'included_seats',
            'extra_seats',
            'branch_limit',
            'usage_limits',
            'current_period_start',
            'current_period_end',
            'activation_status',
            'provisioning_status',
        ] as $column) {
            if (Schema::connection($connection)->hasColumn('tenant_product_subscriptions', $column)) {
                $columns[] = "tenant_product_subscriptions.{$column}";
            }
        }

        return $columns;
    }

    protected function nullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
