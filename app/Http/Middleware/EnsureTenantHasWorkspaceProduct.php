<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\TenantWorkspaceProductService;
use Closure;
use Illuminate\Http\Request;

class EnsureTenantHasWorkspaceProduct
{
    public function __construct(
        protected TenantWorkspaceProductService $tenantWorkspaceProductService
    ) {
    }

    public function handle(Request $request, Closure $next, string $productCode)
    {
        $tenant = function_exists('tenant') ? tenant() : null;

        if (! $tenant) {
            abort(404);
        }

        $workspaceProducts = $this->tenantWorkspaceProductService->getWorkspaceProducts((string) $tenant->id);
        $product = $workspaceProducts->first(function (array $workspaceProduct) use ($productCode) {
            return (string) ($workspaceProduct['product_code'] ?? '') === $productCode
                && ! empty($workspaceProduct['is_accessible']);
        });

        if (! $product) {
            return redirect()
                ->route('automotive.admin.dashboard', ['workspace_product' => $productCode])
                ->with('error', 'This module is not available for the current tenant workspace.');
        }

        $request->attributes->set('workspace_product_code', $productCode);

        return $next($request);
    }
}
