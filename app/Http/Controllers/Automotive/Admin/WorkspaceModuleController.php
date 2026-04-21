<?php

namespace App\Http\Controllers\Automotive\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountingEvent;
use App\Models\AccountingPayment;
use App\Models\JournalEntry;
use App\Models\Customer;
use App\Models\StockMovement;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Models\WorkspaceIntegrationHandoff;
use App\Services\Automotive\AccountingRuntimeService;
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
        protected AccountingRuntimeService $accountingRuntimeService
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
            'status' => ['nullable', 'in:posted,reversed,void'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:120'],
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
                'accounting_accounts' => $this->accountingRuntimeService->getAccounts(),
                'accounting_period_locks' => $this->accountingRuntimeService->getPeriodLocks(),
                'accounting_policies' => $this->accountingRuntimeService->getPolicies(),
                'receivable_events' => $this->accountingRuntimeService->getReceivableEvents(25),
                'recent_accounting_payments' => $this->accountingRuntimeService->getPayments($filters, 15),
                'receivables_aging' => $this->accountingRuntimeService->receivablesAging(),
                'statement_customers' => $this->accountingRuntimeService->statementCustomerNames(),
                'journal_filters' => $filters,
                'recent_journal_entries' => $this->accountingRuntimeService->getJournalEntries($filters, 25),
                'trial_balance' => $this->accountingRuntimeService->trialBalance($filters),
                'revenue_summary' => $this->accountingRuntimeService->revenueSummary($filters),
                'accounting_audit_entries' => $this->accountingRuntimeService->getAuditEntries(30),
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

        return view('automotive.admin.modules.journal-entry-show', compact(
            'journalEntry',
            'workspaceProducts',
            'focusedWorkspaceProduct',
            'workspaceQuery',
            'workspaceIntegrations'
        ));
    }

    public function storeAccountingPostingGroup(Request $request): RedirectResponse
    {
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
        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:asset,liability,equity,revenue,expense'],
            'normal_balance' => ['required', 'in:debit,credit'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->accountingRuntimeService->createAccount($validated);

        return redirect()
            ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
            ->with('success', 'Accounting account saved successfully.');
    }

    public function storeAccountingPeriodLock(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date'],
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

    public function storeAccountingPolicy(Request $request): RedirectResponse
    {
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

        $this->accountingRuntimeService->createPolicy($validated);

        return redirect()
            ->route('automotive.admin.modules.general-ledger', ['workspace_product' => $validated['workspace_product'] ?: 'accounting'])
            ->with('success', 'Accounting policy saved successfully.');
    }

    public function exportAccountingReport(Request $request, string $report): View|StreamedResponse
    {
        abort_unless(in_array($report, ['journal-entries', 'trial-balance', 'revenue-summary', 'payments'], true), 404);

        $filters = $request->validate([
            'status' => ['nullable', 'in:posted,reversed,void'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:120'],
            'workspace_product' => ['nullable', 'string', 'max:255'],
            'format' => ['nullable', 'in:csv,print'],
        ]);
        $format = $filters['format'] ?? 'csv';

        $headers = [];
        $rows = [];

        if ($report === 'journal-entries') {
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
            $headers = ['Payment Number', 'Payment Date', 'Status', 'Payer', 'Method', 'Reference', 'Amount', 'Currency', 'Journal Entry'];
            $rows = $this->accountingRuntimeService->getPayments($filters, 500)
                ->map(fn (AccountingPayment $payment): array => [
                    $payment->payment_number,
                    optional($payment->payment_date)->format('Y-m-d'),
                    $payment->status,
                    $payment->payer_name,
                    $payment->method,
                    $payment->reference,
                    (string) $payment->amount,
                    $payment->currency,
                    $payment->journalEntry?->journal_number,
                ])
                ->all();
        }

        if ($format === 'print') {
            $title = ucfirst(str_replace('-', ' ', $report));

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

    public function postAccountingEvent(Request $request, AccountingEvent $accountingEvent): RedirectResponse
    {
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

    public function postInventoryMovement(Request $request, StockMovement $stockMovement): RedirectResponse
    {
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
        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
            'accounting_event_id' => ['required', 'integer', 'exists:accounting_events,id'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'in:cash,bank_transfer,card,check,other'],
            'payer_name' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:120'],
            'currency' => ['nullable', 'string', 'size:3'],
            'cash_account' => ['nullable', 'string', 'max:120'],
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

    public function voidAccountingPayment(Request $request, AccountingPayment $payment): RedirectResponse
    {
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
        $validated = $request->validate([
            'workspace_product' => ['nullable', 'string', 'max:255'],
            'entry_date' => ['required', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'memo' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_code' => ['nullable', 'string', 'max:120'],
            'lines.*.account_name' => ['nullable', 'string', 'max:255'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.memo' => ['nullable', 'string', 'max:1000'],
        ]);

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
            ->with('success', "Manual journal entry {$entry->journal_number} posted successfully.");
    }

    public function reverseJournalEntry(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
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
}
