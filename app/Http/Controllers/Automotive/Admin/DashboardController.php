<?php

namespace App\Http\Controllers\Automotive\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\Tenancy\TenantPlanService;
use App\Services\Tenancy\TenantWorkspaceProductService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected TenantPlanService $tenantPlanService,
        protected TenantWorkspaceProductService $tenantWorkspaceProductService
    ) {
    }

public function index(Request $request): View
{
    $tenant = tenant();
    $tenantId = $tenant->id;

    $usersCount = User::query()->count();
    $branchesCount = Branch::query()->count();
    $productsCount = Product::query()->count();
    $inventoriesCount = Inventory::query()->count();
    $stockTransfersCount = StockTransfer::query()->count();
    $stockMovementsCount = StockMovement::query()->count();

    $userLimit = $this->tenantPlanService->getLimitSummary($tenantId, 'max_users', $usersCount);
    $branchLimit = $this->tenantPlanService->getLimitSummary($tenantId, 'max_branches', $branchesCount);
    $productLimit = $this->tenantPlanService->getLimitSummary($tenantId, 'max_products', $productsCount);

    $subscription = $this->tenantPlanService->getCurrentSubscription($tenantId);
    $plan = $this->tenantPlanService->getCurrentPlan($tenantId);
    $workspaceProducts = $this->tenantWorkspaceProductService->getWorkspaceProducts($tenantId);
    $focusedWorkspaceProduct = $this->tenantWorkspaceProductService->resolveFocusedProduct(
        $workspaceProducts,
        $request->query('workspace_product')
    );

    $lowStockItems = Inventory::query()
        ->with(['product', 'branch'])
        ->whereHas('product', function ($query) {
            $query->whereColumn('inventories.quantity', '<=', 'products.min_stock_alert');
        })
        ->orderBy('quantity')
        ->limit(5)
        ->get();

    $recentTransfers = StockTransfer::query()
        ->with(['fromBranch', 'toBranch'])
        ->latest()
        ->limit(5)
        ->get();

    $recentMovements = StockMovement::query()
        ->with(['branch', 'product'])
        ->latest()
        ->limit(6)
        ->get();

    return view('automotive.admin.dashboard.index', compact(
        'tenant',
        'usersCount',
        'branchesCount',
        'productsCount',
        'inventoriesCount',
        'stockTransfersCount',
        'stockMovementsCount',
        'userLimit',
        'branchLimit',
        'productLimit',
        'subscription',
        'plan',
        'workspaceProducts',
        'focusedWorkspaceProduct',
        'lowStockItems',
        'recentTransfers',
        'recentMovements'
    ));
}
}
