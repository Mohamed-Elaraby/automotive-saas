<?php

namespace App\Http\Controllers\Automotive\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountingDepositBatch;
use App\Models\AccountingAccount;
use App\Models\AccountingEvent;
use App\Models\AccountingInvoice;
use App\Models\AccountingPayment;
use App\Models\AccountingPeriodLock;
use App\Models\AccountingVendorBill;
use App\Models\AccountingVendorBillPayment;
use App\Models\JournalEntry;
use App\Models\Customer;
use App\Models\StockMovement;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Models\WorkspaceIntegrationHandoff;
use App\Services\Automotive\AccountingRuntimeService;
use App\Services\Automotive\AccountingPermissionService;
use App\Services\Automotive\SupplierCatalogService;
use App\Services\Automotive\WorkOrderAccountingHandoffService;
use App\Services\Automotive\WorkshopPartsIntegrationService;
use App\Services\Automotive\WorkshopWorkOrderService;
use App\Services\Tenancy\WorkspaceIntegrationCatalogService;
use App\Services\Tenancy\WorkspaceIntegrationContractService;
use App\Services\Tenancy\WorkspaceIntegrationHandoffService;
use App\Services\Tenancy\WorkspaceManifestService;
use App\Services\Tenancy\TenantWorkspaceProductService;
use App\Services\Tenancy\WorkspaceModuleCatalogService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkspaceModuleController extends Controller
{
    public function __construct(
        protected TenantWorkspaceProductService $tenantWorkspaceProductService,
        protected WorkspaceModuleCatalogService $workspaceModuleCatalogService,
        protected WorkspaceManifestService $workspaceManifestService,
        protected WorkspaceIntegrationCatalogService $workspaceIntegrationCatalogService,
        protected WorkspaceIntegrationContractService $workspaceIntegrationContractService,
        protected WorkspaceIntegrationHandoffService $workspaceIntegrationHandoffService,
        protected WorkshopPartsIntegrationService $workshopPartsIntegrationService,
        protected SupplierCatalogService $supplierCatalogService,
        protected WorkshopWorkOrderService $workshopWorkOrderService,
        protected WorkOrderAccountingHandoffService $workOrderAccountingHandoffService,
        protected AccountingRuntimeService $accountingRuntimeService,
        protected AccountingPermissionService $accountingPermissionService
    ) {
    }

    public function workshopOperations(Request $request): View
    {
        return $this->showManifestModule(
            $request,
            'workshop-operations',
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
                    'recent_accounting_events' => $this->workshopWorkOrderService->getRecentAccountingEvents(6),
                ];
            }
        );
    }

    public function workshopCustomers(Request $request): View
    {
        return $this->showManifestModule(
            $request,
            'workshop-customers',
            fn () => [
                'customers' => $this->workshopWorkOrderService->getCustomers(),
            ]
        );
    }

    public function workshopVehicles(Request $request): View
    {
        return $this->showManifestModule(
            $request,
            'workshop-vehicles',
            fn () => [
                'vehicles' => $this->workshopWorkOrderService->getVehicles(),
            ]
        );
    }

    public function workshopWorkOrders(Request $request): View
    {
        return $this->showManifestModule(
            $request,
            'workshop-work-orders',
            fn () => [
                'recent_work_orders' => $this->workshopWorkOrderService->getRecentWorkOrders(25),
            ]
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
        $lines = $this->workshopWorkOrderService->getWorkOrderLines($workOrder);
        $summary = $this->workshopWorkOrderService->summarize($workOrder);
        $accountingEvent = $this->workshopWorkOrderService->getWorkOrderAccountingEvent($workOrder);

        return view('automotive.admin.modules.work-order-show', compact(
            'workOrder',
            'workspaceProducts',
            'focusedWorkspaceProduct',
            'workspaceQuery',
            'workspaceIntegrations',
            'consumptions',
            'lines',
            'summary',
            'accountingEvent'
        ));
    }

    public function storeWorkOrderLaborLine(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->workshopWorkOrderService->addLaborLine($workOrder, $validated + [
            'created_by' => auth('automotive_admin')->id(),
        ]);

        if ($workOrder->status === 'open') {
            $this->workshopWorkOrderService->updateStatus($workOrder, 'in_progress');
        }

        return redirect()
            ->route('automotive.admin.modules.workshop-operations.work-orders.show', [
                'workOrder' => $workOrder->id,
                'workspace_product' => $validated['workspace_product'] ?: 'automotive_service',
            ])
            ->with('success', 'Labor line added successfully.');
    }

    public function updateWorkOrderStatus(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:open,in_progress,completed'],
        ]);

        try {
            $updatedWorkOrder = $this->workshopWorkOrderService->updateStatus($workOrder, $validated['status']);

            if ($validated['status'] === 'completed') {
                $this->workOrderAccountingHandoffService->postCompletedWorkOrder($updatedWorkOrder, auth('automotive_admin')->id());
            }
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
        return $this->showManifestModule(
            $request,
            'supplier-catalog',
            fn () => [
                'suppliers' => $this->supplierCatalogService->getSuppliers(),
                'active_suppliers_count' => $this->supplierCatalogService->getActiveSuppliersCount(),
            ]
        );
    }

    public function storeSupplier(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $this->supplierCatalogService->createSupplier($validated);

        return redirect()
            ->route('automotive.admin.modules.supplier-catalog', [
                'workspace_product' => $validated['workspace_product'] ?: 'parts_inventory',
            ])
            ->with('success', 'Supplier created successfully.');
    }

    public function generalLedger(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:pending_approval,approved,posted,reversed,rejected,void'],
            'reconciliation_status' => ['nullable', 'in:pending,deposited,reconciled'],
            'invoice_status' => ['nullable', 'in:draft,posted,paid,void'],
            'vendor_bill_status' => ['nullable', 'in:draft,posted,partial,paid,void'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'due_status' => ['nullable', 'in:overdue,due_soon'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:120'],
            'account_search' => ['nullable', 'string', 'max:120'],
            'account_type' => ['nullable', 'in:asset,liability,equity,revenue,expense'],
            'account_status' => ['nullable', 'in:active,inactive'],
            'workspace_product' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->showManifestModule(
            $request,
            'general-ledger',
            fn () => [
                'recent_accounting_events' => $this->workshopWorkOrderService->getRecentAccountingEvents(25),
                'reviewable_accounting_events' => $this->accountingRuntimeService->getReviewableAccountingEvents(25),
                'reviewable_inventory_movements' => $this->accountingRuntimeService->getReviewableInventoryMovements(25),
                'posting_groups' => $this->accountingRuntimeService->getPostingGroups(),
                'accounting_accounts' => $this->accountingRuntimeService->getAccounts($filters),
                'active_accounting_accounts' => $this->accountingRuntimeService->getAccounts(['account_status' => 'active']),
                'accounting_period_locks' => $this->accountingRuntimeService->getPeriodLocks(),
                'accounting_period_lock_summary' => $this->accountingRuntimeService->periodLockSummary(),
                'accounting_close_checklist' => $this->accountingRuntimeService->periodCloseChecklist(),
                'accounting_policies' => $this->accountingRuntimeService->getPolicies(),
                'accounting_tax_rates' => $this->accountingRuntimeService->getTaxRates(),
                'accounting_bank_accounts' => $this->accountingRuntimeService->getBankAccounts(),
                'receivable_events' => $this->accountingRuntimeService->getReceivableEvents(25),
                'accounting_invoices' => $this->accountingRuntimeService->getInvoices($filters, 15),
                'recent_accounting_payments' => $this->accountingRuntimeService->getPayments($filters, 15),
                'reconcilable_payments' => $this->accountingRuntimeService->getReconcilablePayments(25),
                'recent_deposit_batches' => $this->accountingRuntimeService->getDepositBatches(10),
                'payment_reconciliation_summary' => $this->accountingRuntimeService->paymentReconciliationSummary(),
                'vendor_bills' => $this->accountingRuntimeService->getVendorBills($filters, 15),
                'accounting_suppliers' => $this->supplierCatalogService->getSuppliers(100),
                'payables_summary' => $this->accountingRuntimeService->payablesSummary(),
                'open_vendor_bills' => $this->accountingRuntimeService->getOpenVendorBills(25),
                'recent_vendor_bill_payments' => $this->accountingRuntimeService->getVendorBillPayments($filters, 15),
                'payables_aging' => $this->accountingRuntimeService->payablesAging(),
                'receivables_aging' => $this->accountingRuntimeService->receivablesAging(),
                'statement_customers' => $this->accountingRuntimeService->statementCustomerNames(),
                'journal_filters' => $filters,
                'recent_journal_entries' => $this->accountingRuntimeService->getJournalEntries($filters, 25),
                'trial_balance' => $this->accountingRuntimeService->trialBalance($filters),
                'revenue_summary' => $this->accountingRuntimeService->revenueSummary($filters),
                'accounting_audit_entries' => $this->accountingRuntimeService->getAuditEntries(30),
                'pending_manual_journal_approvals' => $this->accountingRuntimeService->getPendingManualJournalApprovals(25),
                'accounting_permissions' => $this->accountingPermissionMatrix(),
                'integration_contracts' => $this->workspaceIntegrationContractService->contracts(),
                'recent_integration_handoffs' => $this->workspaceIntegrationHandoffService->recent(25),
            ]
        );
    }

    public function showJournalEntry(Request $request, JournalEntry $journalEntry): View
    {
        $workspaceProducts = $this->tenantWorkspaceProductService->getWorkspaceProducts((string) tenant()->id);
        $focusedWorkspaceProduct = $this->tenantWorkspaceProductService->resolveFocusedProduct(
            $workspaceProducts,
            $request->query('workspace_product', 'accounting')
        );
        $workspaceQuery = $this->workspaceModuleCatalogService->workspaceQuery($focusedWorkspaceProduct);
        $workspaceIntegrations = $this->workspaceIntegrationCatalogService->getIntegrations($workspaceProducts, $focusedWorkspaceProduct);
        $journalEntry->load(['lines', 'postingGroup', 'creator', 'accountingEvent']);
        $accountingPermissions = $this->accountingPermissionMatrix();

        return view('automotive.admin.modules.journal-entry-show', compact(
            'journalEntry',
            'workspaceProducts',
            'focusedWorkspaceProduct',
            'workspaceQuery',
            'workspaceIntegrations',
            'accountingPermissions'
        ));
    }

    public function storeAccountingPostingGroup(Request $request): RedirectResponse
    {
        $this->authorizeAccounting(AccountingPermissionService::ACCOUNTS_MANAGE);

        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:255'],
            'receivable_account' => ['required', 'string', 'max:255'],
            'labor_revenue_account' => ['required', 'string', 'max:255'],
            'parts_revenue_account' => ['required', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'size:3'],
            'is_default' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->accountingRuntimeService->createPostingGroup($validated);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
                ->withErrors($exception->errors())
                ->withInput();
        } catch (\Illuminate\Database\QueryException $exception) {
            return redirect()
                ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
                ->withErrors(['code' => 'A posting group with this code already exists.'])
                ->withInput();
        }

        return redirect()
            ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
            ->with('success', 'Posting group created successfully.');
    }

    public function storeAccountingAccount(Request $request): RedirectResponse
    {
        $this->authorizeAccounting(AccountingPermissionService::ACCOUNTS_MANAGE);

        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:asset,liability,equity,revenue,expense'],
            'normal_balance' => ['required', 'in:debit,credit'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->accountingRuntimeService->createAccount($validated);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
                ->withErrors($exception->errors())
                ->withInput();
        }

        return redirect()
            ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
            ->with('success', 'Accounting account saved successfully.');
    }

    public function deactivateAccountingAccount(Request $request, AccountingAccount $account): RedirectResponse
    {
        $this->authorizeAccounting(AccountingPermissionService::ACCOUNTS_MANAGE);

        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
        ]);

        $this->accountingRuntimeService->deactivateAccount($account, auth('automotive_admin')->id());

        return redirect()
            ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
            ->with('success', "Accounting account {$account->code} deactivated successfully.");
    }

    public function destroyAccountingAccount(Request $request, AccountingAccount $account): RedirectResponse
    {
        $this->authorizeAccounting(AccountingPermissionService::ACCOUNTS_MANAGE);

        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->accountingRuntimeService->deleteAccount($account, auth('automotive_admin')->id());
        } catch (ValidationException $exception) {
            return redirect()
                ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
            ->with('success', 'Unused accounting account deleted successfully.');
    }

    public function storeAccountingPeriodLock(Request $request): RedirectResponse
    {
        $this->authorizeAccounting(AccountingPermissionService::PERIODS_LOCK);

        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date'],
            'allow_lock_override' => ['nullable', 'boolean'],
            'lock_override_reason' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->accountingRuntimeService->createPeriodLock($validated, auth('automotive_admin')->id());
        } catch (ValidationException $exception) {
            return redirect()
                ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
                ->withErrors($exception->errors())
                ->withInput();
        }

        return redirect()
            ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
            ->with('success', 'Accounting period locked successfully.');
    }

    public function startAccountingPeriodClose(Request $request): RedirectResponse
    {
        $this->authorizeAccounting(AccountingPermissionService::PERIODS_LOCK);

        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->accountingRuntimeService->beginPeriodClose($validated, auth('automotive_admin')->id());
        } catch (ValidationException $exception) {
            return redirect()
                ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
                ->withErrors($exception->errors())
                ->withInput();
        }

        return redirect()
            ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
            ->with('success', 'Accounting period close review started successfully.');
    }

    public function archiveAccountingPeriod(Request $request, AccountingPeriodLock $period): RedirectResponse
    {
        $this->authorizeAccounting(AccountingPermissionService::PERIODS_LOCK);

        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->accountingRuntimeService->archivePeriod($period, auth('automotive_admin')->id());
        } catch (ValidationException $exception) {
            return redirect()
                ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
            ->with('success', 'Accounting period archived successfully.');
    }

    public function storeAccountingPolicy(Request $request): RedirectResponse
    {
        $this->authorizeAccounting(AccountingPermissionService::ACCOUNTS_MANAGE);

        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'size:3'],
            'inventory_asset_account' => ['required', 'string', 'max:120'],
            'inventory_adjustment_offset_account' => ['required', 'string', 'max:120'],
            'inventory_adjustment_expense_account' => ['required', 'string', 'max:120'],
            'cogs_account' => ['required', 'string', 'max:120'],
            'is_default' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->accountingRuntimeService->createPolicy($validated);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
                ->withErrors($exception->errors())
                ->withInput();
        }

        return redirect()
            ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
            ->with('success', 'Accounting policy saved successfully.');
    }

    public function storeAccountingTaxRate(Request $request): RedirectResponse
    {
        $this->authorizeAccounting(AccountingPermissionService::TAX_RATES_MANAGE);

        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:255'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'input_tax_account' => ['required', 'string', 'max:120'],
            'output_tax_account' => ['required', 'string', 'max:120'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->accountingRuntimeService->createTaxRate($validated);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
                ->withErrors($exception->errors())
                ->withInput();
        }

        return redirect()
            ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
            ->with('success', 'Tax rate saved successfully.');
    }

    public function storeAccountingBankAccount(Request $request): RedirectResponse
    {
        $this->authorizeAccounting(AccountingPermissionService::ACCOUNTS_MANAGE);

        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:cash,bank,wallet,card_processor'],
            'account_code' => ['required', 'string', 'max:120'],
            'currency' => ['nullable', 'string', 'size:3'],
            'reference' => ['nullable', 'string', 'max:120'],
            'is_default_receipt' => ['nullable', 'boolean'],
            'is_default_payment' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->accountingRuntimeService->createBankAccount($validated);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
                ->withErrors($exception->errors())
                ->withInput();
        }

        return redirect()
            ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
            ->with('success', 'Bank or cash account saved successfully.');
    }

    public function exportAccountingReport(Request $request, string $report): View|StreamedResponse
    {
        $this->authorizeAccounting(AccountingPermissionService::REPORTS_EXPORT);

        abort_unless(in_array($report, [
            'journal-entries',
            'trial-balance',
            'revenue-summary',
            'payments',
            'bank-reconciliation',
            'reconciliation-summary',
            'profit-and-loss',
            'balance-sheet',
            'tax-summary',
            'receivables-aging',
            'payables-aging',
        ], true), 404);

        $filters = $request->validate([
            'status' => ['nullable', 'in:posted,reversed,void,corrected'],
            'reconciliation_status' => ['nullable', 'in:pending,deposited,reconciled'],
            'deposit_account' => ['nullable', 'string', 'max:120'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:120'],
            'workspace_product' => ['nullable', 'string', 'max:255'],
            'format' => ['nullable', 'in:csv,print'],
        ]);
        $format = $filters['format'] ?? 'csv';

        $headers = [];
        $rows = [];

        if ($report === 'bank-reconciliation') {
            $reportData = $this->accountingRuntimeService->bankReconciliationReport($filters);

            if ($format === 'print') {
                return view('automotive.admin.modules.accounting-bank-reconciliation-print', compact('reportData'));
            }

            [$headers, $rows] = $this->bankReconciliationExportRows($reportData);
        } elseif ($report === 'reconciliation-summary') {
            $summary = $this->accountingRuntimeService->paymentReconciliationSummary();
            $headers = ['Metric', 'Count', 'Amount', 'Period Start', 'Period End'];
            $rows = [
                ['Unreconciled Receipts', (string) $summary['pending_count'], (string) $summary['pending_amount'], $summary['period_start'], $summary['period_end']],
                ['Unreconciled Deposits', (string) $summary['deposited_count'], (string) $summary['deposited_amount'], $summary['period_start'], $summary['period_end']],
                ['Unreconciled Vendor Payments', (string) $summary['vendor_payment_count'], (string) $summary['vendor_payment_amount'], $summary['period_start'], $summary['period_end']],
                ['Reconciled Period Net Amount', '', (string) $summary['reconciled_period_amount'], $summary['period_start'], $summary['period_end']],
            ];
        } elseif ($report === 'receivables-aging') {
            $aging = $this->accountingRuntimeService->receivablesAging();
            [$headers, $rows] = $this->agingExportRows($aging);
        } elseif ($report === 'payables-aging') {
            $aging = $this->accountingRuntimeService->payablesAging();
            [$headers, $rows] = $this->agingExportRows($aging);
        } elseif (in_array($report, ['profit-and-loss', 'balance-sheet'], true)) {
            $statement = $report === 'profit-and-loss'
                ? $this->accountingRuntimeService->profitAndLoss($filters)
                : $this->accountingRuntimeService->balanceSheet($filters);

            if ($format === 'print') {
                return view('automotive.admin.modules.accounting-financial-statement-print', compact('statement'));
            }

            $headers = ['Section', 'Account Code', 'Account Name', 'Amount'];
            $rows = collect($statement['sections'])
                ->flatMap(fn (array $section) => $section['rows']->map(fn ($row): array => [
                    $section['label'],
                    $row->account_code,
                    $row->account_name,
                    (string) $row->amount,
                ]))
                ->merge(collect($statement['summary'])->map(fn ($amount, $label): array => ['Summary', '', $label, (string) $amount]))
                ->all();
        } elseif ($report === 'tax-summary') {
            $taxSummary = $this->accountingRuntimeService->taxSummary($filters);
            $headers = ['Tax Type', 'Account Code', 'Account Name', 'Debit Total', 'Credit Total', 'Tax Amount'];
            $rows = $taxSummary['rows']
                ->map(fn ($row): array => [
                    ucfirst($row->tax_type),
                    $row->account_code,
                    $row->account_name,
                    (string) $row->debit_total,
                    (string) $row->credit_total,
                    (string) $row->amount,
                ])
                ->push(['Summary', '', 'Input Tax Total', '', '', (string) $taxSummary['input_total']])
                ->push(['Summary', '', 'Output Tax Total', '', '', (string) $taxSummary['output_total']])
                ->push(['Summary', '', 'Net Tax Payable', '', '', (string) $taxSummary['net_payable']])
                ->all();
        } elseif ($report === 'journal-entries') {
            $headers = ['Journal Number', 'Entry Date', 'Status', 'Memo', 'Currency', 'Debit Total', 'Credit Total'];
            $rows = $this->accountingRuntimeService->getJournalEntries($filters, 500)
                ->map(fn (JournalEntry $entry): array => [
                    $entry->journal_number,
                    optional($entry->entry_date)->format('Y-m-d'),
                    $entry->status,
                    $entry->memo,
                    $entry->currency,
                    (string) $entry->debit_total,
                    (string) $entry->credit_total,
                ])
                ->all();
        } elseif ($report === 'trial-balance') {
            $headers = ['Account Code', 'Account Name', 'Debit Total', 'Credit Total', 'Balance'];
            $rows = $this->accountingRuntimeService->trialBalance($filters)
                ->map(fn ($row): array => [
                    $row->account_code,
                    $row->account_name,
                    (string) $row->debit_total,
                    (string) $row->credit_total,
                    (string) $row->balance,
                ])
                ->all();
        } elseif ($report === 'revenue-summary') {
            $headers = ['Account Code', 'Account Name', 'Revenue Total'];
            $rows = $this->accountingRuntimeService->revenueSummary($filters)
                ->map(fn ($row): array => [
                    $row->account_code,
                    $row->account_name,
                    (string) $row->revenue_total,
                ])
                ->all();
        } else {
            $headers = ['Payment Number', 'Payment Date', 'Status', 'Reconciliation', 'Payer', 'Method', 'Reference', 'Amount', 'Currency', 'Deposit Batch', 'Journal Entry'];
            $rows = $this->accountingRuntimeService->getPayments($filters, 500)
                ->map(fn (AccountingPayment $payment): array => [
                    $payment->payment_number,
                    optional($payment->payment_date)->format('Y-m-d'),
                    $payment->status,
                    $payment->reconciliation_status,
                    $payment->payer_name,
                    $payment->method,
                    $payment->reference,
                    (string) $payment->amount,
                    $payment->currency,
                    $payment->depositBatch?->deposit_number,
                    $payment->journalEntry?->journal_number,
                ])
                ->all();
        }

        if ($format === 'print') {
            $title = $this->accountingReportTitle($report);

            return view('automotive.admin.modules.accounting-report-print', compact('title', 'headers', 'rows', 'filters'));
        }

        $filename = $report . '-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($headers, $rows): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);

            foreach ($rows as $row) {
                fputcsv($output, $row);
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    protected function accountingReportTitle(string $report): string
    {
        return match ($report) {
            'profit-and-loss' => 'Profit And Loss',
            'tax-summary' => 'Tax Summary',
            'receivables-aging' => 'Receivables Aging',
            'payables-aging' => 'Payables Aging',
            'reconciliation-summary' => 'Reconciliation Summary',
            default => str($report)->replace('-', ' ')->title()->toString(),
        };
    }

    protected function agingExportRows(array $aging): array
    {
        $headers = ['Bucket', 'Open Count', 'Open Amount', 'Total Open', 'Overdue Total'];
        $rows = collect($aging['buckets'] ?? [])
            ->map(fn (array $bucket): array => [
                $bucket['label'] ?? '',
                (string) ($bucket['count'] ?? 0),
                (string) ($bucket['amount'] ?? 0),
                (string) ($aging['total_open'] ?? 0),
                (string) ($aging['overdue_total'] ?? 0),
            ])
            ->values()
            ->all();

        return [$headers, $rows];
    }

    protected function bankReconciliationExportRows(array $reportData): array
    {
        $headers = ['Section', 'Number', 'Date', 'Account', 'Status', 'Reference', 'Bank Match', 'Count', 'Amount', 'Currency'];
        $rows = [];

        foreach ($reportData['batches'] as $batch) {
            $rows[] = [
                'Deposit Batch',
                $batch->deposit_number,
                optional($batch->deposit_date)->format('Y-m-d'),
                $batch->deposit_account,
                trim(strtoupper((string) $batch->status) . ' / ' . strtoupper((string) ($batch->reconciliation_status ?: 'pending'))),
                $batch->reference,
                trim((optional($batch->bank_reconciliation_date)->format('Y-m-d') ?: '-') . ($batch->bank_reference ? ' ' . $batch->bank_reference : '')),
                (string) $batch->payments_count,
                (string) $batch->total_amount,
                $batch->currency,
            ];
        }

        foreach ($reportData['direct_receipts'] as $payment) {
            $rows[] = [
                'Direct Receipt',
                $payment->payment_number,
                optional($payment->payment_date)->format('Y-m-d'),
                $payment->cash_account,
                strtoupper((string) ($payment->reconciliation_status ?: 'pending')),
                $payment->reference,
                trim((optional($payment->bank_reconciliation_date)->format('Y-m-d') ?: '-') . ($payment->bank_reference ? ' ' . $payment->bank_reference : '')),
                '',
                (string) $payment->amount,
                $payment->currency,
            ];
        }

        foreach ($reportData['vendor_payments'] as $payment) {
            $rows[] = [
                'Vendor Payment',
                $payment->payment_number,
                optional($payment->payment_date)->format('Y-m-d'),
                $payment->cash_account,
                strtoupper((string) ($payment->reconciliation_status ?: 'pending')),
                $payment->reference,
                trim((optional($payment->bank_reconciliation_date)->format('Y-m-d') ?: '-') . ($payment->bank_reference ? ' ' . $payment->bank_reference : '')),
                '',
                (string) $payment->amount,
                $payment->currency,
            ];
        }

        $rows[] = ['Summary', 'Posted Batches', '', '', '', '', '', (string) $reportData['posted_count'], (string) $reportData['posted_total'], ''];
        $rows[] = ['Summary', 'Reconciled Batches', '', '', '', '', '', (string) $reportData['reconciled_count'], (string) $reportData['reconciled_total'], ''];
        $rows[] = ['Summary', 'Direct Receipts Total', '', '', '', '', '', '', (string) $reportData['direct_receipts_total'], ''];
        $rows[] = ['Summary', 'Vendor Payments Total', '', '', '', '', '', '', (string) $reportData['vendor_payments_total'], ''];

        return [$headers, $rows];
    }

    public function postAccountingEvent(Request $request, AccountingEvent $accountingEvent): RedirectResponse
    {
        $this->authorizeAccounting(AccountingPermissionService::SOURCE_EVENTS_POST);

        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
            'posting_group_id' => ['nullable', 'integer', 'exists:accounting_posting_groups,id'],
        ]);

        try {
            $entry = $this->accountingRuntimeService->postAccountingEvent(
                $accountingEvent,
                isset($validated['posting_group_id']) ? (int) $validated['posting_group_id'] : null,
                auth('automotive_admin')->id()
            );
        } catch (ValidationException $exception) {
            return redirect()
                ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
                ->withErrors($exception->errors())
                ->withInput();
        }

        return redirect()
            ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
            ->with('success', "Journal entry {$entry->journal_number} posted successfully.");
    }

    public function showAccountingInvoice(Request $request, AccountingEvent $accountingEvent): View
    {
        $workspaceProducts = $this->tenantWorkspaceProductService->getWorkspaceProducts((string) tenant()->id);
        $focusedWorkspaceProduct = $this->tenantWorkspaceProductService->resolveFocusedProduct(
            $workspaceProducts,
            $request->query('workspace_product', 'accounting')
        );
        $workspaceQuery = $this->workspaceModuleCatalogService->workspaceQuery($focusedWorkspaceProduct);
        $document = $this->accountingRuntimeService->invoiceDocument($accountingEvent);

        return view('automotive.admin.modules.accounting-invoice-print', compact(
            'accountingEvent',
            'document',
            'workspaceQuery'
        ));
    }

    public function showCustomerStatement(Request $request): View
    {
        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
            'customer' => ['required', 'string', 'max:255'],
        ]);

        $statement = $this->accountingRuntimeService->customerStatement($validated['customer']);

        return view('automotive.admin.modules.accounting-statement-print', compact('statement'));
    }

    public function storeAccountingInvoice(Request $request): RedirectResponse
    {
        $this->authorizeAccounting(AccountingPermissionService::AR_INVOICES_MANAGE);

        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
            'customer_name' => ['required', 'string', 'max:255'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'receivable_account' => ['nullable', 'string', 'max:120'],
            'tax_account' => ['nullable', 'string', 'max:120'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.account_code' => ['nullable', 'string', 'max:120'],
            'lines.*.quantity' => ['nullable', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $invoice = $this->accountingRuntimeService->createInvoice(
                $validated,
                auth('automotive_admin')->id()
            );
        } catch (ValidationException $exception) {
            return redirect()
                ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
                ->withErrors($exception->errors())
                ->withInput();
        }

        return redirect()
            ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
            ->with('success', "Invoice {$invoice->invoice_number} created successfully.");
    }

    public function postAccountingInvoice(Request $request, AccountingInvoice $invoice): RedirectResponse
    {
        $this->authorizeAccounting(AccountingPermissionService::AR_INVOICES_POST);

        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $entry = $this->accountingRuntimeService->postInvoice(
                $invoice,
                auth('automotive_admin')->id()
            );
        } catch (ValidationException $exception) {
            return redirect()
                ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
                ->withErrors($exception->errors())
                ->withInput();
        }

        return redirect()
            ->route('automotive.admin.modules.general-ledger.journal-entries.show', [
                'journalEntry' => $entry->id,
                'workspace_product' => $validated['workspace_product'] ?: 'accounting',
            ])
            ->with('success', "Invoice journal {$entry->journal_number} posted successfully.");
    }

    public function postInventoryMovement(Request $request, StockMovement $stockMovement): RedirectResponse
    {
        $this->authorizeAccounting(AccountingPermissionService::INVENTORY_MOVEMENTS_POST);

        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $entry = $this->accountingRuntimeService->postInventoryMovement(
                $stockMovement,
                auth('automotive_admin')->id()
            );
        } catch (ValidationException $exception) {
            return redirect()
                ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
                ->withErrors($exception->errors())
                ->withInput();
        }

        return redirect()
            ->route('automotive.admin.modules.general-ledger.journal-entries.show', [
                'journalEntry' => $entry->id,
                'workspace_product' => $validated['workspace_product'] ?: 'accounting',
            ])
            ->with('success', "Inventory valuation journal {$entry->journal_number} posted successfully.");
    }

    public function storeAccountingPayment(Request $request): RedirectResponse
    {
        $this->authorizeAccounting(AccountingPermissionService::CUSTOMER_PAYMENTS_RECORD);

        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
            'accounting_event_id' => ['required', 'integer', 'exists:accounting_events,id'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'in:cash,bank_transfer,card,check,other'],
            'payer_name' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:120'],
            'currency' => ['nullable', 'string', 'size:3'],
            'accounting_bank_account_id' => ['nullable', 'integer', 'exists:accounting_bank_accounts,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $payment = $this->accountingRuntimeService->recordCustomerPayment(
                $validated,
                auth('automotive_admin')->id()
            );
        } catch (ValidationException $exception) {
            return redirect()
                ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
                ->withErrors($exception->errors())
                ->withInput();
        }

        return redirect()
            ->route('automotive.admin.modules.general-ledger.journal-entries.show', [
                'journalEntry' => $payment->journal_entry_id,
                'workspace_product' => $validated['workspace_product'] ?: 'accounting',
            ])
            ->with('success', "Payment {$payment->payment_number} recorded successfully.");
    }

    public function storeAccountingDepositBatch(Request $request): RedirectResponse
    {
        $this->authorizeAccounting(AccountingPermissionService::DEPOSIT_BATCHES_CREATE);

        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
            'payment_ids' => ['required', 'array', 'min:1'],
            'payment_ids.*' => ['integer', 'exists:accounting_payments,id'],
            'deposit_date' => ['required', 'date'],
            'accounting_bank_account_id' => ['nullable', 'integer', 'exists:accounting_bank_accounts,id'],
            'currency' => ['nullable', 'string', 'size:3'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $batch = $this->accountingRuntimeService->createDepositBatch(
                $validated,
                auth('automotive_admin')->id()
            );
        } catch (ValidationException $exception) {
            return redirect()
                ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
                ->withErrors($exception->errors())
                ->withInput();
        }

        return redirect()
            ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
            ->with('success', "Deposit batch {$batch->deposit_number} posted successfully.");
    }

    public function storeAccountingVendorBill(Request $request): RedirectResponse
    {
        $this->authorizeAccounting(AccountingPermissionService::VENDOR_BILLS_POST);

        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'bill_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:120'],
            'currency' => ['nullable', 'string', 'size:3'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'accounting_tax_rate_id' => ['nullable', 'integer', 'exists:accounting_tax_rates,id'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'expense_account' => ['nullable', 'string', 'max:120'],
            'payable_account' => ['nullable', 'string', 'max:120'],
            'tax_account' => ['nullable', 'string', 'max:120'],
            'attachment_name' => ['nullable', 'string', 'max:255'],
            'attachment_reference' => ['nullable', 'string', 'max:160'],
            'attachment_url' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $bill = $this->accountingRuntimeService->createVendorBill(
                $validated,
                auth('automotive_admin')->id()
            );
        } catch (ValidationException $exception) {
            return redirect()
                ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
                ->withErrors($exception->errors())
                ->withInput();
        }

        return redirect()
            ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
            ->with('success', "Vendor bill {$bill->bill_number} created successfully.");
    }

    public function storeAccountingVendorBillCreditNote(Request $request, AccountingVendorBill $vendorBill): RedirectResponse
    {
        $this->authorizeAccounting(AccountingPermissionService::VENDOR_BILLS_ADJUST);

        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
            'adjustment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:120'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $adjustment = $this->accountingRuntimeService->createVendorBillCreditNote(
                $vendorBill,
                $validated,
                auth('automotive_admin')->id()
            );
        } catch (ValidationException $exception) {
            return redirect()
                ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
                ->withErrors($exception->errors())
                ->withInput();
        }

        return redirect()
            ->route('automotive.admin.modules.general-ledger.journal-entries.show', [
                'journalEntry' => $adjustment->journal_entry_id,
                'workspace_product' => $validated['workspace_product'] ?: 'accounting',
            ])
            ->with('success', "Vendor credit note {$adjustment->adjustment_number} posted successfully.");
    }

    public function postAccountingVendorBill(Request $request, AccountingVendorBill $vendorBill): RedirectResponse
    {
        $this->authorizeAccounting(AccountingPermissionService::VENDOR_BILLS_POST);

        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $entry = $this->accountingRuntimeService->postVendorBill(
                $vendorBill,
                auth('automotive_admin')->id()
            );
        } catch (ValidationException $exception) {
            return redirect()
                ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
                ->withErrors($exception->errors())
                ->withInput();
        }

        return redirect()
            ->route('automotive.admin.modules.general-ledger.journal-entries.show', [
                'journalEntry' => $entry->id,
                'workspace_product' => $validated['workspace_product'] ?: 'accounting',
            ])
            ->with('success', "Vendor bill journal {$entry->journal_number} posted successfully.");
    }

    public function storeAccountingVendorBillPayment(Request $request): RedirectResponse
    {
        $this->authorizeAccounting(AccountingPermissionService::VENDOR_BILL_PAYMENTS_RECORD);

        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
            'accounting_vendor_bill_id' => ['required', 'integer', 'exists:accounting_vendor_bills,id'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'in:cash,bank_transfer,card,check,other'],
            'reference' => ['nullable', 'string', 'max:120'],
            'currency' => ['nullable', 'string', 'size:3'],
            'accounting_bank_account_id' => ['nullable', 'integer', 'exists:accounting_bank_accounts,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $payment = $this->accountingRuntimeService->recordVendorBillPayment(
                $validated,
                auth('automotive_admin')->id()
            );
        } catch (ValidationException $exception) {
            return redirect()
                ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
                ->withErrors($exception->errors())
                ->withInput();
        }

        return redirect()
            ->route('automotive.admin.modules.general-ledger.journal-entries.show', [
                'journalEntry' => $payment->journal_entry_id,
                'workspace_product' => $validated['workspace_product'] ?: 'accounting',
            ])
            ->with('success', "Vendor payment {$payment->payment_number} recorded successfully.");
    }

    public function showAccountingDepositBatch(Request $request, AccountingDepositBatch $depositBatch): View
    {
        $workspaceProducts = $this->tenantWorkspaceProductService->getWorkspaceProducts((string) tenant()->id);
        $focusedWorkspaceProduct = $this->tenantWorkspaceProductService->resolveFocusedProduct(
            $workspaceProducts,
            $request->query('workspace_product', 'accounting')
        );
        $workspaceQuery = $this->workspaceModuleCatalogService->workspaceQuery($focusedWorkspaceProduct);
        $workspaceIntegrations = $this->workspaceIntegrationCatalogService->getIntegrations($workspaceProducts, $focusedWorkspaceProduct);
        $depositBatch = $this->accountingRuntimeService->depositBatchDetail($depositBatch);

        return view('automotive.admin.modules.accounting-deposit-batch-show', compact(
            'depositBatch',
            'workspaceProducts',
            'focusedWorkspaceProduct',
            'workspaceQuery',
            'workspaceIntegrations'
        ));
    }

    public function correctAccountingDepositBatch(Request $request, AccountingDepositBatch $depositBatch): RedirectResponse
    {
        $this->authorizeAccounting(AccountingPermissionService::DEPOSIT_BATCHES_CORRECT);

        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
            'correction_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $correctedBatch = $this->accountingRuntimeService->correctDepositBatch(
                $depositBatch,
                $validated,
                auth('automotive_admin')->id()
            );
        } catch (ValidationException $exception) {
            return redirect()
                ->route('automotive.admin.modules.general-ledger.deposit-batches.show', [
                    'depositBatch' => $depositBatch->id,
                    'workspace_product' => $validated['workspace_product'] ?: 'accounting',
                ])
                ->withErrors($exception->errors())
                ->withInput();
        }

        return redirect()
            ->route('automotive.admin.modules.general-ledger.deposit-batches.show', [
                'depositBatch' => $correctedBatch->id,
                'workspace_product' => $validated['workspace_product'] ?: 'accounting',
            ])
            ->with('success', "Deposit batch {$correctedBatch->deposit_number} corrected successfully.");
    }

    public function reconcileAccountingDepositBatch(Request $request, AccountingDepositBatch $depositBatch): RedirectResponse
    {
        $this->authorizeAccounting(AccountingPermissionService::RECONCILIATION_MANAGE);

        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
            'bank_reconciliation_date' => ['required', 'date'],
            'bank_reference' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $reconciledBatch = $this->accountingRuntimeService->reconcileDepositBatch(
                $depositBatch,
                $validated,
                auth('automotive_admin')->id()
            );
        } catch (ValidationException $exception) {
            return redirect()
                ->route('automotive.admin.modules.general-ledger.deposit-batches.show', [
                    'depositBatch' => $depositBatch->id,
                    'workspace_product' => $validated['workspace_product'] ?: 'accounting',
                ])
                ->withErrors($exception->errors())
                ->withInput();
        }

        return redirect()
            ->route('automotive.admin.modules.general-ledger.deposit-batches.show', [
                'depositBatch' => $reconciledBatch->id,
                'workspace_product' => $validated['workspace_product'] ?: 'accounting',
            ])
            ->with('success', "Deposit batch {$reconciledBatch->deposit_number} reconciled successfully.");
    }

    public function reconcileAccountingPayment(Request $request, AccountingPayment $payment): RedirectResponse
    {
        $this->authorizeAccounting(AccountingPermissionService::RECONCILIATION_MANAGE);

        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
            'bank_reconciliation_date' => ['required', 'date'],
            'bank_reference' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $this->accountingRuntimeService->reconcileCustomerPayment(
                $payment,
                $validated,
                auth('automotive_admin')->id()
            );
        } catch (ValidationException $exception) {
            return redirect()
                ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
                ->withErrors($exception->errors())
                ->withInput();
        }

        return redirect()
            ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
            ->with('success', "Payment {$payment->payment_number} reconciled successfully.");
    }

    public function reconcileAccountingVendorBillPayment(Request $request, AccountingVendorBillPayment $payment): RedirectResponse
    {
        $this->authorizeAccounting(AccountingPermissionService::RECONCILIATION_MANAGE);

        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
            'bank_reconciliation_date' => ['required', 'date'],
            'bank_reference' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $this->accountingRuntimeService->reconcileVendorBillPayment(
                $payment,
                $validated,
                auth('automotive_admin')->id()
            );
        } catch (ValidationException $exception) {
            return redirect()
                ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
                ->withErrors($exception->errors())
                ->withInput();
        }

        return redirect()
            ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
            ->with('success', "Vendor payment {$payment->payment_number} reconciled successfully.");
    }

    public function voidAccountingPayment(Request $request, AccountingPayment $payment): RedirectResponse
    {
        $this->authorizeAccounting(AccountingPermissionService::CUSTOMER_PAYMENTS_RECORD);

        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $entry = $this->accountingRuntimeService->voidCustomerPayment(
                $payment,
                auth('automotive_admin')->id()
            );
        } catch (ValidationException $exception) {
            return redirect()
                ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('automotive.admin.modules.general-ledger.journal-entries.show', [
                'journalEntry' => $entry->id,
                'workspace_product' => $validated['workspace_product'] ?: 'accounting',
            ])
            ->with('success', "Payment void journal {$entry->journal_number} posted successfully.");
    }

    public function retryIntegrationHandoff(Request $request, WorkspaceIntegrationHandoff $handoff): RedirectResponse
    {
        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
        ]);

        if ($handoff->status === 'posted') {
            return redirect()
                ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
                ->with('error', 'Posted integration handoffs do not need retry.');
        }

        try {
            if ($handoff->integration_key === 'automotive-accounting' && $handoff->source_type === WorkOrder::class) {
                $workOrder = WorkOrder::query()->findOrFail($handoff->source_id);
                $this->workOrderAccountingHandoffService->postCompletedWorkOrder($workOrder, auth('automotive_admin')->id());
            } elseif ($handoff->integration_key === 'parts-accounting' && $handoff->source_type === StockMovement::class) {
                $movement = StockMovement::query()->findOrFail($handoff->source_id);
                $this->accountingRuntimeService->postInventoryMovement($movement, auth('automotive_admin')->id());
            } else {
                return redirect()
                    ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
                    ->with('error', 'No retry handler is registered for this integration handoff.');
            }
        } catch (\Throwable $exception) {
            return redirect()
                ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
                ->with('error', 'Integration handoff retry failed: ' . $exception->getMessage());
        }

        return redirect()
            ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
            ->with('success', 'Integration handoff retry executed successfully.');
    }

    public function storeManualJournalEntry(Request $request): RedirectResponse
    {
        $this->authorizeAccounting(AccountingPermissionService::MANUAL_JOURNALS_CREATE);

        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
            'entry_date' => ['required', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'memo' => ['nullable', 'string', 'max:2000'],
            'requires_approval' => ['nullable', 'boolean'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_code' => ['nullable', 'string', 'max:120'],
            'lines.*.account_name' => ['nullable', 'string', 'max:255'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.memo' => ['nullable', 'string', 'max:1000'],
        ]);

        $debitTotal = round((float) collect($validated['lines'] ?? [])->sum(fn (array $line): float => (float) ($line['debit'] ?? 0)), 2);
        $threshold = (float) config('accounting.manual_journal_approval_threshold', 5000);
        $willRequireApproval = ! empty($validated['requires_approval']) || ($threshold > 0 && $debitTotal >= $threshold);

        if (! $willRequireApproval) {
            $this->authorizeAccounting(AccountingPermissionService::MANUAL_JOURNALS_POST);
        }

        try {
            $entry = $this->accountingRuntimeService->createManualJournalEntry(
                $validated,
                auth('automotive_admin')->id()
            );
        } catch (ValidationException $exception) {
            return redirect()
                ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
                ->withErrors($exception->errors())
                ->withInput();
        }

        return redirect()
            ->route('automotive.admin.modules.general-ledger.journal-entries.show', [
                'journalEntry' => $entry->id,
                'workspace_product' => $validated['workspace_product'] ?: 'accounting',
            ])
            ->with('success', $entry->status === 'pending_approval'
                ? "Manual journal entry {$entry->journal_number} submitted for approval."
                : "Manual journal entry {$entry->journal_number} posted successfully.");
    }

    public function reverseJournalEntry(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorizeAccounting(AccountingPermissionService::JOURNALS_REVERSE);

        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $reversal = $this->accountingRuntimeService->reverseJournalEntry(
                $journalEntry,
                auth('automotive_admin')->id()
            );
        } catch (ValidationException $exception) {
            return redirect()
                ->route('automotive.admin.modules.general-ledger.journal-entries.show', [
                    'journalEntry' => $journalEntry->id,
                    'workspace_product' => $validated['workspace_product'] ?: 'accounting',
                ])
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('automotive.admin.modules.general-ledger.journal-entries.show', [
                'journalEntry' => $reversal->id,
                'workspace_product' => $validated['workspace_product'] ?: 'accounting',
            ])
            ->with('success', "Reversal journal entry {$reversal->journal_number} posted successfully.");
    }

    public function approveManualJournalEntry(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorizeAccounting(AccountingPermissionService::MANUAL_JOURNALS_APPROVE);

        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
            'approval_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $entry = $this->accountingRuntimeService->approveManualJournalEntry(
                $journalEntry,
                auth('automotive_admin')->id(),
                $validated['approval_notes'] ?? null
            );
        } catch (ValidationException $exception) {
            return redirect()
                ->route('automotive.admin.modules.general-ledger.journal-entries.show', [
                    'journalEntry' => $journalEntry->id,
                    'workspace_product' => $validated['workspace_product'] ?: 'accounting',
                ])
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('automotive.admin.modules.general-ledger.journal-entries.show', [
                'journalEntry' => $entry->id,
                'workspace_product' => $validated['workspace_product'] ?: 'accounting',
            ])
            ->with('success', "Manual journal entry {$entry->journal_number} approved.");
    }

    public function rejectManualJournalEntry(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorizeAccounting(AccountingPermissionService::MANUAL_JOURNALS_APPROVE);

        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
            'approval_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $entry = $this->accountingRuntimeService->rejectManualJournalEntry(
                $journalEntry,
                auth('automotive_admin')->id(),
                $validated['approval_notes'] ?? null
            );
        } catch (ValidationException $exception) {
            return redirect()
                ->route('automotive.admin.modules.general-ledger.journal-entries.show', [
                    'journalEntry' => $journalEntry->id,
                    'workspace_product' => $validated['workspace_product'] ?: 'accounting',
                ])
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('automotive.admin.modules.general-ledger.journal-entries.show', [
                'journalEntry' => $entry->id,
                'workspace_product' => $validated['workspace_product'] ?: 'accounting',
            ])
            ->with('success', "Manual journal entry {$entry->journal_number} rejected.");
    }

    public function postApprovedManualJournalEntry(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorizeAccounting(AccountingPermissionService::MANUAL_JOURNALS_POST);

        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $entry = $this->accountingRuntimeService->postApprovedManualJournalEntry(
                $journalEntry,
                auth('automotive_admin')->id()
            );
        } catch (ValidationException $exception) {
            return redirect()
                ->route('automotive.admin.modules.general-ledger.journal-entries.show', [
                    'journalEntry' => $journalEntry->id,
                    'workspace_product' => $validated['workspace_product'] ?: 'accounting',
                ])
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('automotive.admin.modules.general-ledger.journal-entries.show', [
                'journalEntry' => $entry->id,
                'workspace_product' => $validated['workspace_product'] ?: 'accounting',
            ])
            ->with('success', "Approved manual journal entry {$entry->journal_number} posted successfully.");
    }

    protected function showManifestModule(
        Request $request,
        string $page,
        ?callable $dataResolver = null
    ): View {
        $moduleDefinition = $this->workspaceManifestService->runtimeModule($page);

        abort_unless($moduleDefinition, 404);

        $tenant = tenant();
        $workspaceProducts = $this->tenantWorkspaceProductService->getWorkspaceProducts((string) $tenant->id);
        $focusedWorkspaceProduct = $this->tenantWorkspaceProductService->resolveFocusedProduct(
            $workspaceProducts,
            $request->query('workspace_product', (string) ($moduleDefinition['focus_code'] ?? $moduleDefinition['family'] ?? ''))
        );

        $workspaceQuery = $this->workspaceModuleCatalogService->workspaceQuery($focusedWorkspaceProduct);
        $workspaceIntegrations = $this->workspaceIntegrationCatalogService->getIntegrations($workspaceProducts, $focusedWorkspaceProduct);
        $moduleData = $dataResolver ? $dataResolver() : [];
        $title = (string) ($moduleDefinition['title'] ?? ucfirst(str_replace('-', ' ', $page)));
        $description = (string) ($moduleDefinition['description'] ?? '');
        $links = collect((array) ($moduleDefinition['links'] ?? []))
            ->map(fn (array $link): array => $link + ['params' => $workspaceQuery])
            ->values()
            ->all();

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

    protected function authorizeAccounting(string $permission): void
    {
        abort_unless($this->accountingPermissionService->can(auth('automotive_admin')->user(), $permission), 403);
    }

    protected function accountingPermissionMatrix(): array
    {
        $user = auth('automotive_admin')->user();

        return [
            'manual_journals_create' => $this->accountingPermissionService->can($user, AccountingPermissionService::MANUAL_JOURNALS_CREATE),
            'manual_journals_post' => $this->accountingPermissionService->can($user, AccountingPermissionService::MANUAL_JOURNALS_POST),
            'manual_journals_approve' => $this->accountingPermissionService->can($user, AccountingPermissionService::MANUAL_JOURNALS_APPROVE),
            'source_events_post' => $this->accountingPermissionService->can($user, AccountingPermissionService::SOURCE_EVENTS_POST),
            'ar_invoices_manage' => $this->accountingPermissionService->can($user, AccountingPermissionService::AR_INVOICES_MANAGE),
            'ar_invoices_post' => $this->accountingPermissionService->can($user, AccountingPermissionService::AR_INVOICES_POST),
            'inventory_movements_post' => $this->accountingPermissionService->can($user, AccountingPermissionService::INVENTORY_MOVEMENTS_POST),
            'vendor_bills_post' => $this->accountingPermissionService->can($user, AccountingPermissionService::VENDOR_BILLS_POST),
            'vendor_bills_adjust' => $this->accountingPermissionService->can($user, AccountingPermissionService::VENDOR_BILLS_ADJUST),
            'vendor_bill_payments_record' => $this->accountingPermissionService->can($user, AccountingPermissionService::VENDOR_BILL_PAYMENTS_RECORD),
            'customer_payments_record' => $this->accountingPermissionService->can($user, AccountingPermissionService::CUSTOMER_PAYMENTS_RECORD),
            'deposit_batches_create' => $this->accountingPermissionService->can($user, AccountingPermissionService::DEPOSIT_BATCHES_CREATE),
            'deposit_batches_correct' => $this->accountingPermissionService->can($user, AccountingPermissionService::DEPOSIT_BATCHES_CORRECT),
            'reconciliation_manage' => $this->accountingPermissionService->can($user, AccountingPermissionService::RECONCILIATION_MANAGE),
            'journals_reverse' => $this->accountingPermissionService->can($user, AccountingPermissionService::JOURNALS_REVERSE),
            'periods_lock' => $this->accountingPermissionService->can($user, AccountingPermissionService::PERIODS_LOCK),
            'accounts_manage' => $this->accountingPermissionService->can($user, AccountingPermissionService::ACCOUNTS_MANAGE),
            'tax_rates_manage' => $this->accountingPermissionService->can($user, AccountingPermissionService::TAX_RATES_MANAGE),
            'reports_export' => $this->accountingPermissionService->can($user, AccountingPermissionService::REPORTS_EXPORT),
        ];
    }
}
