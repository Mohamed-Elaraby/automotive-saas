<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\TenantProductSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantProductSubscriptionLimitSyncService
{
    public function sync(TenantProductSubscription $subscription): TenantProductSubscription
    {
        $plan = $subscription->plan_id ? Plan::query()->find((int) $subscription->plan_id) : null;

        if (! $plan) {
            return $subscription;
        }

        $productKey = (string) ($subscription->product_key ?: $subscription->product?->code);

        $subscription->forceFill([
            'included_seats' => $this->planLimit($plan, $productKey, 'included_seats') ?? $plan->max_users,
            'branch_limit' => $this->planLimit($plan, $productKey, 'branch_limit') ?? $plan->max_branches,
        ])->save();

        return $subscription->refresh();
    }

    public function syncTenantProduct(string $tenantId, int $planId): void
    {
        TenantProductSubscription::query()
            ->where('tenant_id', $tenantId)
            ->where('plan_id', $planId)
            ->get()
            ->each(fn (TenantProductSubscription $subscription) => $this->sync($subscription));
    }

    protected function planLimit(Plan $plan, string $productKey, string $limitKey): ?int
    {
        $connection = config('tenancy.database.central_connection') ?? config('database.default');

        if (! Schema::connection($connection)->hasTable('plan_limits')) {
            return null;
        }

        $value = DB::connection($connection)
            ->table('plan_limits')
            ->where('plan_id', $plan->id)
            ->where('product_key', $productKey)
            ->where('limit_key', $limitKey)
            ->value('limit_value');

        return $value === null || $value === '' ? null : (int) $value;
    }
}
