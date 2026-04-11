<?php

namespace App\Http\Controllers\Automotive\Admin;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use App\Services\Automotive\WorkshopPartsIntegrationService;
use App\Services\Automotive\WorkshopWorkOrderService;
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
        protected WorkshopPartsIntegrationService $workshopPartsIntegrationService,
        protected WorkshopWorkOrderService $workshopWorkOrderService
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
                    'active_branches' => $this->workshopWorkOrderService->getActiveBranches(),
                    'customers' => $this->workshopWorkOrderService->getCustomers(),
                    'vehicles' => $this->workshopWorkOrderService->getVehicles(),
                    'open_work_orders' => $this->workshopWorkOrderService->getOpenWorkOrders(),
                    'recent_work_orders' => $this->workshopWorkOrderService->getRecentWorkOrders(),
                    'available_stock_items' => $this->workshopPartsIntegrationService->getAvailableStockSnapshot(),
                    'recent_workshop_consumptions' => $this->workshopPartsIntegrationService->getRecentWorkshopConsumptions(),
                ];
            }
        );
    }

    public function storeWorkOrder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->workshopWorkOrderService->createWorkOrder([
            'branch_id' => $validated['branch_id'],
            'customer_id' => $validated['customer_id'] ?? null,
            'vehicle_id' => $validated['vehicle_id'] ?? null,
            'title' => $validated['title'],
            'notes' => $validated['notes'] ?? null,
            'created_by' => auth('automotive_admin')->id(),
        ]);

        return redirect()
            ->route('automotive.admin.modules.workshop-operations', ['workspace_product' => $validated['workspace_product'] ?: 'automotive_service'])
            ->with('success', 'Work order created successfully.');
    }

    public function storeWorkshopCustomer(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $this->workshopWorkOrderService->createCustomer($validated);

        return redirect()
            ->route('automotive.admin.modules.workshop-operations', ['workspace_product' => $validated['workspace_product'] ?: 'automotive_service'])
            ->with('success', 'Workshop customer created successfully.');
    }

    public function storeWorkshopVehicle(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'make' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'plate_number' => ['nullable', 'string', 'max:255'],
            'vin' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->workshopWorkOrderService->createVehicle($validated);

        return redirect()
            ->route('automotive.admin.modules.workshop-operations', ['workspace_product' => $validated['workspace_product'] ?: 'automotive_service'])
            ->with('success', 'Workshop vehicle created successfully.');
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
            'work_order_id' => ['required', 'integer', 'exists:work_orders,id'],
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

    public function showWorkOrder(Request $request, WorkOrder $workOrder): View
    {
        $workspaceProducts = $this->tenantWorkspaceProductService->getWorkspaceProducts((string) tenant()->id);
        $focusedWorkspaceProduct = $this->tenantWorkspaceProductService->resolveFocusedProduct(
            $workspaceProducts,
            $request->query('workspace_product', 'automotive_service')
        );
        $workspaceQuery = $this->workspaceModuleCatalogService->workspaceQuery($focusedWorkspaceProduct);
        $workspaceIntegrations = $this->workspaceIntegrationCatalogService->getIntegrations($workspaceProducts, $focusedWorkspaceProduct);
        $consumptions = $this->workshopWorkOrderService->getWorkOrderConsumptions($workOrder);

        return view('automotive.admin.modules.work-order-show', compact(
            'workOrder',
            'workspaceProducts',
            'focusedWorkspaceProduct',
            'workspaceQuery',
            'workspaceIntegrations',
            'consumptions'
        ));
    }

    public function updateWorkOrderStatus(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:open,in_progress,completed'],
        ]);

        try {
            $this->workshopWorkOrderService->updateStatus($workOrder, $validated['status']);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('automotive.admin.modules.workshop-operations.work-orders.show', [
                    'workOrder' => $workOrder->id,
                    'workspace_product' => $validated['workspace_product'] ?: 'automotive_service',
                ])
                ->withErrors($exception->errors())
                ->withInput();
        }

        return redirect()
            ->route('automotive.admin.modules.workshop-operations.work-orders.show', [
                'workOrder' => $workOrder->id,
                'workspace_product' => $validated['workspace_product'] ?: 'automotive_service',
            ])
            ->with('success', 'Work order status updated successfully.');
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
