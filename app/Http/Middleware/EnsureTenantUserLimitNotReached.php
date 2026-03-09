<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\TenantPlanService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantUserLimitNotReached
{
    public function __construct(
        protected TenantPlanService $tenantPlanService
    ) {
    }

public function handle(Request $request, Closure $next): Response
{
    $tenant = tenant();

    if (! $tenant) {
        abort(404, 'Tenant not identified.');
    }

    if (! $this->tenantPlanService->canCreateTenantUser($tenant->id)) {
        return back()->withErrors([
            'limit' => 'Your current plan user limit has been reached.',
        ]);
    }

    return $next($request);
}
}
