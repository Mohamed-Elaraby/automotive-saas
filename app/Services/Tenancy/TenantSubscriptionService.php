<?php

namespace App\Services\Tenancy;


use App\Support\Billing\SubscriptionStatuses;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantSubscriptionService
{
    protected const PRODUCT_CODE = 'automotive_service';

    public function getCurrentSubscription(string $tenantId): ?object
    {
        $centralConnection = config('tenancy.database.central_connection') ?? config('database.default');

        if (
            Schema::connection($centralConnection)->hasTable('tenant_product_subscriptions')
            && Schema::connection($centralConnection)->hasTable('products')
        ) {
            $productSubscription = DB::connection($centralConnection)
                ->table('tenant_product_subscriptions')
                ->join('products', 'products.id', '=', 'tenant_product_subscriptions.product_id')
                ->where('tenant_product_subscriptions.tenant_id', $tenantId)
                ->where('products.code', self::PRODUCT_CODE)
                ->orderByDesc('tenant_product_subscriptions.id')
                ->select('tenant_product_subscriptions.*')
                ->first();

            if ($productSubscription) {
                return $productSubscription;
            }
        }

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

        if ($status === SubscriptionStatuses::ACTIVE) {
            return [
                'allowed' => true,
                'reason' => 'active',
                'subscription' => $subscription,
            ];
        }

        if ($status === SubscriptionStatuses::TRIALING) {
            if ($trialEndsAt && now()->greaterThan($trialEndsAt)) {
                return [
                    'allowed' => false,
                    'reason' => SubscriptionStatuses::EXPIRED,
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
