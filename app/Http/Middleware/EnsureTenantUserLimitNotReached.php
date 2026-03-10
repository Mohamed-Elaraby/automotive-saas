<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\TenantLimitService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantUserLimitNotReached
{
    public function __construct(
        protected TenantLimitService $tenantLimitService
    ) {
    }

public function handle(Request $request, Closure $next): Response
{
    $tenant = tenant();

    if (! $tenant) {
        abort(404, 'Tenant not identified.');
    }

    $currentUsersCount = \App\Models\User::query()->count();

    $decision = $this->tenantLimitService->getDecision(
        $tenant->id,
        'max_users',
        $currentUsersCount
    );

    if (! $decision['allowed']) {
        return back()->withErrors([
            'limit' => 'Your current plan user limit has been reached.',
        ]);
    }

    return $next($request);
}
}
