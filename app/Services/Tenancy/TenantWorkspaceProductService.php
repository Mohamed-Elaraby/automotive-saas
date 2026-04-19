<?php

namespace App\Services\Tenancy;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantWorkspaceProductService
{
    public function __construct(
        protected WorkspaceProductFamilyResolver $workspaceProductFamilyResolver,
        protected WorkspaceManifestService $workspaceManifestService,
        protected WorkspaceProductActivationService $workspaceProductActivationService
    ) {
    }

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

        $selectColumns = [
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
        ];

        foreach ([
            'activation_status',
            'provisioning_status',
            'provisioning_started_at',
            'provisioning_completed_at',
            'provisioning_failed_at',
            'activated_at',
            'activation_error',
            'activation_source',
        ] as $column) {
            if (Schema::connection($connection)->hasColumn('tenant_product_subscriptions', $column)) {
                $selectColumns[] = "tenant_product_subscriptions.{$column}";
            }
        }

        $rows = DB::connection($connection)
            ->table('tenant_product_subscriptions')
            ->join('products', 'products.id', '=', 'tenant_product_subscriptions.product_id')
            ->leftJoin('plans', 'plans.id', '=', 'tenant_product_subscriptions.plan_id')
            ->where('tenant_product_subscriptions.tenant_id', $tenantId)
            ->orderByDesc('tenant_product_subscriptions.id')
            ->get($selectColumns);

        $capabilitiesByProductId = collect();

        if (Schema::connection($connection)->hasTable('product_capabilities')) {
            $capabilitiesByProductId = DB::connection($connection)
                ->table('product_capabilities')
                ->whereIn('product_id', $rows->pluck('product_id')->filter()->unique()->all())
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['product_id', 'name'])
                ->groupBy('product_id')
                ->map(fn (Collection $items) => $items->pluck('name')->values()->all());
        }

        return $rows
            ->unique('product_id')
            ->map(function (object $row) use ($capabilitiesByProductId): array {
                $status = (string) ($row->status ?? '');
                $isAccessible = $this->workspaceProductActivationService->allowsRuntimeAccess($row);
                $workspaceProduct = [
                    'product_code' => (string) ($row->product_code ?? ''),
                    'product_name' => (string) ($row->product_name ?? ('Product #' . $row->product_id)),
                    'product_slug' => (string) ($row->product_slug ?? ''),
                ];
                $family = $this->workspaceProductFamilyResolver->resolveFromWorkspaceProduct($workspaceProduct);

                return [
                    'subscription_id' => (int) $row->id,
                    'tenant_id' => (string) $row->tenant_id,
                    'product_id' => (int) $row->product_id,
                    'product_code' => $workspaceProduct['product_code'],
                    'product_name' => $workspaceProduct['product_name'],
                    'product_slug' => $workspaceProduct['product_slug'],
                    'product_family' => $family,
                    'plan_name' => (string) ($row->plan_name ?? ''),
                    'capabilities' => $capabilitiesByProductId->get($row->product_id, []),
                    'status' => $status,
                    'status_label' => strtoupper(str_replace('_', ' ', $status ?: 'unknown')),
                    'activation_status' => (string) ($row->activation_status ?? ''),
                    'provisioning_status' => (string) ($row->provisioning_status ?? ''),
                    'provisioning_error' => (string) ($row->activation_error ?? ''),
                    'is_accessible' => $isAccessible,
                    'is_primary_workspace_product' => $family === $this->workspaceManifestService->defaultFamily(),
                ];
            })
            ->values();
    }

    public function resolveFocusedProduct(Collection $workspaceProducts, mixed $selection = null): ?array
    {
        $selected = trim((string) $selection);

        if ($selected !== '') {
            $matched = $workspaceProducts->first(function (array $product) use ($selected) {
                return (string) $product['product_code'] === $selected
                    || (string) $product['product_slug'] === $selected
                    || (string) $product['product_id'] === $selected;
            });

            if ($matched) {
                return $matched;
            }
        }

        $primary = $workspaceProducts->firstWhere('is_primary_workspace_product', true);

        if ($primary) {
            return $primary;
        }

        $accessible = $workspaceProducts->firstWhere('is_accessible', true);

        if ($accessible) {
            return $accessible;
        }

        return $workspaceProducts->first();
    }
}
