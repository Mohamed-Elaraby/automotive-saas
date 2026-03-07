<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Services\Tenancy\TenantSubscriptionService;

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

    Auth::guard('automotive_admin')->logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()
        ->route('automotive.admin.login')
        ->withErrors([
            'subscription' => 'Your trial or subscription is no longer active.',
        ]);
}
}
