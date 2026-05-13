<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\ProductPermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantUserCanManageAccess
{
    public function __construct(
        protected ProductPermissionService $permissions
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = function_exists('tenant') ? tenant() : null;
        $user = $request->user('automotive_admin');

        if (! $tenant || ! $user) {
            abort(403);
        }

        if ($this->isPrimaryWorkspaceOwner((int) $user->id)) {
            return $next($request);
        }

        foreach (['automotive.access.manage', 'automotive_service.access.manage', 'automotive_service.access.roles.manage'] as $permissionKey) {
            if ($this->permissions->can($user, 'automotive_service', $permissionKey, null, (string) $tenant->id)) {
                return $next($request);
            }
        }

        abort(403, 'User does not have access-control management permission.');
    }

    private function isPrimaryWorkspaceOwner(int $userId): bool
    {
        return $userId === 1;
    }
}
