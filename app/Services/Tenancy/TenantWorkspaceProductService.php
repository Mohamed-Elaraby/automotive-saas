<?php

namespace App\Services\Tenancy;

use App\Support\Billing\SubscriptionStatuses;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantWorkspaceProductService
{
    protected function centralConnection(): string
    {
        return config('tenancy.database.central_connection') ?? config('database.default');
    }

    public function getWorkspaceProducts(string $tenantId): Collection
    {
        if ($tenantId === '') {
            return collect();
        }

        $connection = $this->centralConnection();

        if (
            ! Schema::connection($connection)->hasTable('tenant_product_subscriptions')
            || ! Schema::connection($connection)->hasTable('products')
        ) {
            return collect();
        }

        $rows = DB::connection($connection)
            ->table('tenant_product_subscriptions')
            ->join('products', 'products.id', '=', 'tenant_product_subscriptions.product_id')
            ->leftJoin('plans', 'plans.id', '=', 'tenant_product_subscriptions.plan_id')
            ->where('tenant_product_subscriptions.tenant_id', $tenantId)
            ->orderByDesc('tenant_product_subscriptions.id')
            ->get([
                'tenant_product_subscriptions.id',
                'tenant_product_subscriptions.tenant_id',
                'tenant_product_subscriptions.product_id',
                'tenant_product_subscriptions.plan_id',
                'tenant_product_subscriptions.status',
                'tenant_product_subscriptions.gateway',
                'tenant_product_subscriptions.gateway_subscription_id',
                'tenant_product_subscriptions.ends_at',
                'products.code as product_code',
                'products.name as product_name',
                'products.slug as product_slug',
                'plans.name as plan_name',
            ]);

        return $rows
            ->unique('product_id')
            ->map(function (object $row): array {
                $status = (string) ($row->status ?? '');
                $isAccessible = in_array($status, SubscriptionStatuses::accessAllowedStatuses(), true);

                return [
                    'subscription_id' => (int) $row->id,
                    'tenant_id' => (string) $row->tenant_id,
                    'product_id' => (int) $row->product_id,
                    'product_code' => (string) ($row->product_code ?? ''),
                    'product_name' => (string) ($row->product_name ?? ('Product #' . $row->product_id)),
                    'product_slug' => (string) ($row->product_slug ?? ''),
                    'plan_name' => (string) ($row->plan_name ?? ''),
                    'status' => $status,
                    'status_label' => strtoupper(str_replace('_', ' ', $status ?: 'unknown')),
                    'is_accessible' => $isAccessible,
                    'is_primary_workspace_product' => (string) ($row->product_code ?? '') === 'automotive_service',
                ];
            })
            ->values();
    }
}
