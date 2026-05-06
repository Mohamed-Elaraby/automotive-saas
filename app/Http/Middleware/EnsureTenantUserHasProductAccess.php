<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\ProductEntitlementService;
use App\Services\Tenancy\TenantUserProductAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantUserHasProductAccess
{
    public function __construct(
        protected ProductEntitlementService $entitlements,
        protected TenantUserProductAccessService $productAccess
    ) {
    }

    public function handle(Request $request, Closure $next, string $productKey): Response
    {
        $tenant = function_exists('tenant') ? tenant() : null;
        $user = $request->user('automotive_admin') ?: $request->user();

        if (! $tenant || ! $user) {
            abort(403);
        }

        if (! $this->entitlements->isSubscribed((string) $tenant->id, $productKey)) {
            abort(403, 'Product subscription is not active.');
        }

        if (! $this->productAccess->hasAccess($user, $productKey, (string) $tenant->id)) {
            abort(403, 'User does not have access to this product.');
        }

        return $next($request);
    }
}
