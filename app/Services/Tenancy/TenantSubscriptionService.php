<?php

namespace App\Services\Tenancy;


use App\Support\Billing\SubscriptionStatuses;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantSubscriptionService
{
    public function getCurrentSubscription(string $tenantId): ?object
    {
        $centralConnection = config('tenancy.database.central_connection') ?? config('database.default');

        if (
            Schema::connection($centralConnection)->hasTable('tenant_product_subscriptions')
            && Schema::connection($centralConnection)->hasTable('products')
        ) {
            $manifest = app(WorkspaceManifestService::class);
            $preferredFamily = $manifest->defaultFamily();
            $productSubscription = $this->productSubscriptionBaseQuery($centralConnection, $tenantId)
                ->get()
                ->first(function (object $subscription) use ($manifest, $preferredFamily): bool {
                    return $manifest->resolveFamilyFromText(strtolower(implode(' ', array_filter([
                        (string) ($subscription->product_code ?? ''),
                        (string) ($subscription->product_slug ?? ''),
                        (string) ($subscription->product_name ?? ''),
                    ])))) === $preferredFamily;
                });

            if ($productSubscription) {
                return $productSubscription;
            }

            $fallbackProductSubscription = $this->productSubscriptionBaseQuery($centralConnection, $tenantId)
                ->first();

            if ($fallbackProductSubscription) {
                return $fallbackProductSubscription;
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
