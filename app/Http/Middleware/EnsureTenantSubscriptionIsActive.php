<?php

namespace App\Http\Middleware;

use App\Services\Billing\TenantBillingLifecycleService;
use App\Services\Tenancy\TenantPlanService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantSubscriptionIsActive
{
    public function __construct(
        protected TenantPlanService $tenantPlanService,
        protected TenantBillingLifecycleService $tenantBillingLifecycleService
    ) {
    }

public function handle(Request $request, Closure $next): Response
{
    $tenant = tenant();

    if (! $tenant) {
        abort(404, 'Tenant context is not available.');
    }

    $subscription = $this->tenantPlanService->getCurrentSubscription($tenant->id);
    $billingState = $this->tenantBillingLifecycleService->resolveState($subscription);

    $allowedRoutes = [
        'automotive.admin.subscription.expired',
        'automotive.admin.billing.status',
        'automotive.admin.logout',
    ];

    if (! $billingState['allow_access']) {
        if (! in_array(optional($request->route())->getName(), $allowedRoutes, true)) {
            return redirect()
                ->route('automotive.admin.billing.status')
                ->with('error', $billingState['message']);
        }
    }

    return $next($request);
}
}
