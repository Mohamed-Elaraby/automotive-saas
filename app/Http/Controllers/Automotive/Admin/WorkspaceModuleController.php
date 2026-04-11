<?php

namespace App\Http\Controllers\Automotive\Admin;

use App\Http\Controllers\Controller;
use App\Services\Tenancy\TenantWorkspaceProductService;
use App\Services\Tenancy\WorkspaceModuleCatalogService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceModuleController extends Controller
{
    public function __construct(
        protected TenantWorkspaceProductService $tenantWorkspaceProductService,
        protected WorkspaceModuleCatalogService $workspaceModuleCatalogService
    ) {
    }

    public function workshopOperations(Request $request): View
    {
        return $this->showModule(
            $request,
            'automotive_service',
            'workshop-operations',
            'Workshop Operations',
            'Core maintenance and workshop execution flows should live here. This keeps the automotive product limited to service operations only.',
            [
                ['label' => 'Manage Users', 'route' => 'automotive.admin.users.index', 'icon' => 'isax-profile-2user'],
                ['label' => 'Manage Branches', 'route' => 'automotive.admin.branches.index', 'icon' => 'isax-buildings'],
                ['label' => 'Plans & Billing', 'route' => 'automotive.admin.billing.status', 'icon' => 'isax-crown5'],
            ]
        );
    }

    public function supplierCatalog(Request $request): View
    {
        return $this->showModule(
            $request,
            'parts_inventory',
            'supplier-catalog',
            'Supplier Catalog',
            'Spare parts purchasing, supplier references, inventory adjustments, and transfers belong to this product context.',
            [
                ['label' => 'Stock Items', 'route' => 'automotive.admin.products.index', 'icon' => 'isax-box'],
                ['label' => 'Inventory Report', 'route' => 'automotive.admin.inventory-report.index', 'icon' => 'isax-chart-35'],
                ['label' => 'Stock Transfers', 'route' => 'automotive.admin.stock-transfers.index', 'icon' => 'isax-arrow-right-3'],
            ]
        );
    }

    public function generalLedger(Request $request): View
    {
        return $this->showModule(
            $request,
            'accounting',
            'general-ledger',
            'General Ledger',
            'This is the accounting runtime entry point for ledgers, journals, and future finance modules inside the shared tenant workspace.',
            [
                ['label' => 'Dashboard', 'route' => 'automotive.admin.dashboard', 'icon' => 'isax-element-45'],
                ['label' => 'Plans & Billing', 'route' => 'automotive.admin.billing.status', 'icon' => 'isax-crown5'],
            ]
        );
    }

    protected function showModule(
        Request $request,
        string $productCode,
        string $page,
        string $title,
        string $description,
        array $links
    ): View {
        $tenant = tenant();
        $workspaceProducts = $this->tenantWorkspaceProductService->getWorkspaceProducts((string) $tenant->id);
        $focusedWorkspaceProduct = $this->tenantWorkspaceProductService->resolveFocusedProduct(
            $workspaceProducts,
            $request->query('workspace_product', $productCode)
        );

        $workspaceQuery = $this->workspaceModuleCatalogService->workspaceQuery($focusedWorkspaceProduct);

        return view('automotive.admin.modules.show', compact(
            'page',
            'title',
            'description',
            'links',
            'workspaceProducts',
            'focusedWorkspaceProduct',
            'workspaceQuery'
        ));
    }
}
