<?php

namespace App\Http\Controllers\Automotive\Admin;

use App\Http\Controllers\Controller;
use App\Services\Automotive\WorkshopPartsIntegrationService;
use App\Services\Tenancy\WorkspaceIntegrationCatalogService;
use App\Services\Tenancy\TenantWorkspaceProductService;
use App\Services\Tenancy\WorkspaceModuleCatalogService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WorkspaceModuleController extends Controller
{
    public function __construct(
        protected TenantWorkspaceProductService $tenantWorkspaceProductService,
        protected WorkspaceModuleCatalogService $workspaceModuleCatalogService,
        protected WorkspaceIntegrationCatalogService $workspaceIntegrationCatalogService,
        protected WorkshopPartsIntegrationService $workshopPartsIntegrationService
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
            ],
            function () {
                $tenantId = (string) tenant()->id;

                return [
                    'has_connected_parts_workspace' => $this->workshopPartsIntegrationService->hasConnectedPartsWorkspace($tenantId),
                    'available_stock_items' => $this->workshopPartsIntegrationService->getAvailableStockSnapshot(),
                    'recent_workshop_consumptions' => $this->workshopPartsIntegrationService->getRecentWorkshopConsumptions(),
                ];
            }
        );
    }

    public function consumeWorkshopPart(Request $request): RedirectResponse
    {
        $tenantId = (string) tenant()->id;

        if (! $this->workshopPartsIntegrationService->hasConnectedPartsWorkspace($tenantId)) {
            return redirect()
                ->route('automotive.admin.modules.workshop-operations', ['workspace_product' => $request->input('workspace_product', 'automotive_service')])
                ->with('error', 'Spare Parts must be connected before workshop stock can be consumed.');
        }

        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->workshopPartsIntegrationService->consumePart($validated + [
                'created_by' => auth('automotive_admin')->id(),
            ]);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('automotive.admin.modules.workshop-operations', ['workspace_product' => $validated['workspace_product'] ?: 'automotive_service'])
                ->withErrors($exception->errors())
                ->withInput();
        }

        return redirect()
            ->route('automotive.admin.modules.workshop-operations', ['workspace_product' => $validated['workspace_product'] ?: 'automotive_service'])
            ->with('success', 'Workshop stock consumption saved successfully.');
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
        array $links,
        ?callable $dataResolver = null
    ): View {
        $tenant = tenant();
        $workspaceProducts = $this->tenantWorkspaceProductService->getWorkspaceProducts((string) $tenant->id);
        $focusedWorkspaceProduct = $this->tenantWorkspaceProductService->resolveFocusedProduct(
            $workspaceProducts,
            $request->query('workspace_product', $productCode)
        );

        $workspaceQuery = $this->workspaceModuleCatalogService->workspaceQuery($focusedWorkspaceProduct);
        $workspaceIntegrations = $this->workspaceIntegrationCatalogService->getIntegrations($workspaceProducts, $focusedWorkspaceProduct);
        $moduleData = $dataResolver ? $dataResolver() : [];

        return view('automotive.admin.modules.show', compact(
            'page',
            'title',
            'description',
            'links',
            'workspaceProducts',
            'focusedWorkspaceProduct',
            'workspaceQuery',
            'workspaceIntegrations',
            'moduleData'
        ));
    }
}
