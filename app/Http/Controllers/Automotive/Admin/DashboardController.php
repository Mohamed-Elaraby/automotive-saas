<?php

namespace App\Http\Controllers\Automotive\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\Tenancy\WorkspaceIntegrationCatalogService;
use App\Services\Tenancy\WorkspaceProductFamilyResolver;
use App\Services\Tenancy\AccessVisibilityService;
use App\Services\Tenancy\BranchScopeService;
use App\Services\Tenancy\TenantPlanService;
use App\Services\Tenancy\TenantWorkspaceProductService;
use App\Services\Tenancy\WorkspaceModuleCatalogService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected TenantPlanService $tenantPlanService,
        protected TenantWorkspaceProductService $tenantWorkspaceProductService,
        protected WorkspaceModuleCatalogService $workspaceModuleCatalogService,
        protected WorkspaceIntegrationCatalogService $workspaceIntegrationCatalogService,
        protected WorkspaceProductFamilyResolver $workspaceProductFamilyResolver,
        protected AccessVisibilityService $accessVisibility,
        protected BranchScopeService $branchScope
    ) {
    }

public function index(Request $request): View
{
    $tenant = tenant();
    $tenantId = $tenant->id;

    $usersCount = User::query()->count();
    $branchesCount = Branch::query()->count();
    $productsCount = StockItem::query()->count();
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
    $workspaceQuery = $this->workspaceModuleCatalogService->workspaceQuery($focusedWorkspaceProduct);
    $focusedWorkspaceProductFamily = $this->workspaceModuleCatalogService->getFocusedProductFamily($focusedWorkspaceProduct);
    $dashboardActions = $this->workspaceModuleCatalogService->getDashboardActions($focusedWorkspaceProduct);
    $adminUser = $request->user('automotive_admin');

    if ($adminUser) {
        $dashboardActions = $this->accessVisibility->filterQuickCreateActions($dashboardActions, $adminUser, $focusedWorkspaceProduct);
    }
    $focusedExperience = $this->workspaceModuleCatalogService->getFocusedProductExperience($focusedWorkspaceProduct);
    $workspaceIntegrations = $this->workspaceIntegrationCatalogService->getIntegrations($workspaceProducts, $focusedWorkspaceProduct);

    $inventoriesCount = 0;
    $stockTransfersCount = 0;
    $stockMovementsCount = 0;
    $lowStockItems = collect();
    $recentTransfers = collect();
    $recentMovements = collect();

    if ($focusedWorkspaceProductFamily === 'parts_inventory') {
        $inventoryCountQuery = Inventory::query();
        $stockMovementCountQuery = StockMovement::query();

        if ($adminUser) {
            $this->branchScope->applyCurrentBranch($inventoryCountQuery, $adminUser, 'automotive_service');
            $this->branchScope->applyCurrentBranch($stockMovementCountQuery, $adminUser, 'automotive_service');
        }

        $inventoriesCount = $inventoryCountQuery->count();
        $stockTransfersCount = $adminUser
            ? StockTransfer::query()
                ->where(function ($query) use ($adminUser): void {
                    $branchIds = $this->branchScope->visibleBranchIds($adminUser, 'automotive_service');
                    $query->whereIn('from_branch_id', $branchIds)->orWhereIn('to_branch_id', $branchIds);
                })
                ->count()
            : StockTransfer::query()->count();
        $stockMovementsCount = $stockMovementCountQuery->count();

        $lowStockItems = Inventory::query()
            ->with(['product', 'branch'])
            ->whereHas('product', function ($query) {
                $query->whereColumn('inventories.quantity', '<=', 'products.min_stock_alert');
            })
            ->orderBy('quantity');

        if ($adminUser) {
            $this->branchScope->applyCurrentBranch($lowStockItems, $adminUser, 'automotive_service');
        }

        $lowStockItems = $lowStockItems->limit(5)->get();

        $recentTransfersQuery = StockTransfer::query()
            ->with(['fromBranch', 'toBranch'])
            ->latest();

        if ($adminUser) {
            $branchIds = $this->branchScope->visibleBranchIds($adminUser, 'automotive_service');
            $recentTransfersQuery->where(function ($query) use ($branchIds): void {
                $query->whereIn('from_branch_id', $branchIds)->orWhereIn('to_branch_id', $branchIds);
            });
        }

        $recentTransfers = $recentTransfersQuery->limit(5)->get();

        $recentMovements = StockMovement::query()
            ->with(['branch', 'product'])
            ->latest();

        if ($adminUser) {
            $this->branchScope->applyCurrentBranch($recentMovements, $adminUser, 'automotive_service');
        }

        $recentMovements = $recentMovements->limit(6)->get();
    }

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
        'focusedWorkspaceProductFamily',
        'workspaceQuery',
        'dashboardActions',
        'focusedExperience',
        'workspaceIntegrations',
        'lowStockItems',
        'recentTransfers',
        'recentMovements'
    ));
}
}
