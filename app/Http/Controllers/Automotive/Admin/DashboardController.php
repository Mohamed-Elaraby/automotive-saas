<?php

namespace App\Http\Controllers\Automotive\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockTransfer;
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
    $branchesCount = Branch::query()->count();
    $productsCount = Product::query()->count();
    $inventoriesCount = Inventory::query()->count();
    $stockTransfersCount = StockTransfer::query()->count();
    $stockMovementsCount = StockMovement::query()->count();

    $userLimit = $this->tenantLimitService->getDecision(
        $tenant->id,
        'max_users',
        $usersCount
    );

    $branchLimit = $this->tenantLimitService->getDecision(
        $tenant->id,
        'max_branches',
        $branchesCount
    );

    $productLimit = $this->tenantLimitService->getDecision(
        $tenant->id,
        'max_products',
        $productsCount
    );

    return view('automotive.admin.dashboard.index', [
        'tenant' => $tenant,
        'subscription' => $subscription,
        'plan' => $plan,
        'usersCount' => $usersCount,
        'branchesCount' => $branchesCount,
        'productsCount' => $productsCount,
        'inventoriesCount' => $inventoriesCount,
        'stockTransfersCount' => $stockTransfersCount,
        'stockMovementsCount' => $stockMovementsCount,
        'userLimit' => $userLimit,
        'branchLimit' => $branchLimit,
        'productLimit' => $productLimit,
    ]);
}
}
