<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\TenantSubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantSubscriptionIsActive
{
    public function __construct(
        protected TenantSubscriptionService $subscriptionService
    ) {
    }

public function handle(Request $request, Closure $next): Response
{
    $tenant = tenant();

    if (! $tenant) {
        abort(404, 'Tenant not identified.');
    }

    $decision = $this->subscriptionService->getAccessDecision($tenant->id);

    if ($decision['allowed']) {
        return $next($request);
    }

    if (Auth::guard('automotive_admin')->check()) {
        Auth::guard('automotive_admin')->logout();
    }

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/automotive/admin/subscription-expired');
}
}
