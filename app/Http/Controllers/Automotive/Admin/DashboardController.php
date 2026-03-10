<?php

namespace App\Http\Controllers\Automotive\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Tenancy\TenantLimitService;
use App\Services\Tenancy\TenantPlanService;
use App\Services\Tenancy\TenantSubscriptionService;

class DashboardController extends Controller
{
    public function __construct(
        protected TenantSubscriptionService $tenantSubscriptionService,
        protected TenantPlanService $tenantPlanService,
        protected TenantLimitService $tenantLimitService
    ) {
    }

public function index()
{
    $tenant = tenant();

    if (! $tenant) {
        abort(404, 'Tenant not identified.');
    }

    $subscription = $this->tenantSubscriptionService->getCurrentSubscription($tenant->id);
    $plan = $this->tenantPlanService->getCurrentPlan($tenant->id);
    $usersCount = User::query()->count();

    $userLimit = $this->tenantLimitService->getDecision(
        $tenant->id,
        'max_users',
        $usersCount
    );

    return view('automotive.admin.dashboard.index', [
        'tenant' => $tenant,
        'subscription' => $subscription,
        'plan' => $plan,
        'usersCount' => $usersCount,
        'userLimit' => $userLimit,
    ]);
}
}
