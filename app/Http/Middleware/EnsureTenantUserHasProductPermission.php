<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\BranchContextService;
use App\Services\Tenancy\ProductPermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantUserHasProductPermission
{
    public function __construct(
        protected ProductPermissionService $permissions,
        protected BranchContextService $branchContext
    ) {
    }

    public function handle(Request $request, Closure $next, string $productKey, string $permissionKey, string $branchMode = 'optional'): Response
    {
        $tenant = function_exists('tenant') ? tenant() : null;
        $user = $request->user('automotive_admin');

        if (! $tenant || ! $user) {
            abort(403, 'Authenticated tenant workspace user is required.');
        }

        $branchId = $this->resolveBranchId($request, $user, $productKey, $branchMode);

        if ($this->requiresBranch($branchMode) && $branchId === null) {
            abort(403, 'A valid branch context is required for this action.');
        }

        foreach ($this->permissionKeys($permissionKey) as $candidatePermission) {
            if ($this->permissions->can($user, $productKey, $candidatePermission, $branchId, (string) $tenant->id)) {
                return $next($request);
            }
        }

        abort(403, 'User does not have the required product permission.');
    }

    protected function permissionKeys(string $permissionKey): array
    {
        return collect(explode('|', $permissionKey))
            ->map(fn (string $key): string => trim($key))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function resolveBranchId(Request $request, mixed $user, string $productKey, string $branchMode): ?int
    {
        $branchId = $request->integer('branch_id') ?: null;

        if ($branchId !== null || ! in_array($branchMode, ['current_branch', 'branch_required'], true)) {
            return $branchId;
        }

        try {
            $context = $this->branchContext->contextForUser($user, $productKey);

            return isset($context['current_branch_id']) ? (int) $context['current_branch_id'] : null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function requiresBranch(string $branchMode): bool
    {
        return in_array($branchMode, ['current_branch', 'branch_required'], true);
    }
}
