<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\ProductPermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantUserHasProductPermission
{
    public function __construct(
        protected ProductPermissionService $permissions
    ) {
    }

    public function handle(Request $request, Closure $next, string $productKey, string $permissionKey): Response
    {
        $tenant = function_exists('tenant') ? tenant() : null;
        $user = $request->user('automotive_admin') ?: $request->user();
        $branchId = $request->integer('branch_id') ?: null;

        if (! $tenant || ! $user) {
            abort(403);
        }

        if (! $this->permissions->can($user, $productKey, $permissionKey, $branchId, (string) $tenant->id)) {
            abort(403, 'User does not have the required product permission.');
        }

        return $next($request);
    }
}
