<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\WorkspaceProductFamilyResolver;
use App\Services\Tenancy\WorkspaceManifestService;
use App\Services\Tenancy\TenantWorkspaceProductService;
use Closure;
use Illuminate\Http\Request;

class EnsureTenantHasWorkspaceProduct
{
    public function __construct(
        protected TenantWorkspaceProductService $tenantWorkspaceProductService,
        protected WorkspaceProductFamilyResolver $workspaceProductFamilyResolver,
        protected WorkspaceManifestService $workspaceManifestService
    ) {
    }

    public function handle(Request $request, Closure $next, string $productFamilyOrModule)
    {
        $tenant = function_exists('tenant') ? tenant() : null;

        if (! $tenant) {
            abort(404);
        }

        $workspaceProducts = $this->tenantWorkspaceProductService->getWorkspaceProducts((string) $tenant->id);
        $productFamily = $this->workspaceManifestService->resolveFamilyOrModuleOwner($productFamilyOrModule);
        $product = $workspaceProducts->first(function (array $workspaceProduct) use ($productFamily) {
            return $this->workspaceProductFamilyResolver->resolveFromWorkspaceProduct($workspaceProduct) === $productFamily
                && ! empty($workspaceProduct['is_accessible']);
        });

        if (! $product) {
            return redirect()
                ->route('automotive.admin.dashboard', ['workspace_product' => $this->workspaceManifestService->focusCodeFor($productFamilyOrModule)])
                ->with('error', 'This module is not available for the current tenant workspace.');
        }

        $request->attributes->set('workspace_product_code', (string) ($product['product_code'] ?? $productFamily));

        return $next($request);
    }
}
