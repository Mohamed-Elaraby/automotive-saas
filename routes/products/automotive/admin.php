<?php

use App\Http\Controllers\Automotive\Admin\Auth\AuthController;
use App\Http\Controllers\Automotive\Admin\BillingController;
use App\Http\Controllers\Automotive\Admin\BranchController;
use App\Http\Controllers\Automotive\Admin\DashboardController;
use App\Http\Controllers\Automotive\Admin\InventoryAdjustmentController;
use App\Http\Controllers\Automotive\Admin\InventoryReportController;
use App\Http\Controllers\Automotive\Admin\Maintenance\MaintenanceAttachmentController;
use App\Http\Controllers\Automotive\Admin\Maintenance\MaintenanceController;
use App\Http\Controllers\Automotive\Admin\Maintenance\MaintenanceDocumentController;
use App\Http\Controllers\Automotive\Admin\Maintenance\MaintenanceIntegrationController;
use App\Http\Controllers\Automotive\Admin\Maintenance\MaintenanceLifecycleController;
use App\Http\Controllers\Automotive\Admin\Maintenance\MaintenanceReportsController;
use App\Http\Controllers\Automotive\Admin\Maintenance\MaintenanceWorkflowController;
use App\Http\Controllers\Core\DocumentController;
use App\Http\Controllers\Automotive\Admin\ProductController;
use App\Http\Controllers\Automotive\Admin\StockMovementReportController;
use App\Http\Controllers\Automotive\Admin\StockTransferController;
use App\Http\Controllers\Automotive\Admin\UserController;
use App\Http\Controllers\Automotive\Admin\WorkspaceModuleController;
use Illuminate\Support\Facades\Route;

$registerWorkspaceAdminRoutes = function (string $homePrefix, string $adminPrefix, bool $named): void {
    if ($named) {
        Route::get('/' . trim($homePrefix, '/'), [AuthController::class, 'landing'])->name('automotive.admin.home');
    } else {
        Route::get('/' . trim($homePrefix, '/'), [AuthController::class, 'landing']);
    }

    $group = Route::prefix(trim($adminPrefix, '/'));

    if ($named) {
        $group = $group->name('automotive.admin.');
    } else {
        $group = $group->name('legacy.automotive.admin.');
    }

    $group->group(function () {
        Route::get('/subscription-expired', function () {
            return response()->view('automotive.admin.auth.subscription-expired');
        })->name('subscription.expired');

        Route::get('/impersonate/{token}', [AuthController::class, 'impersonate'])->name('impersonate');

        Route::middleware('guest:automotive_admin')->group(function () {
            Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
            Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
        });

        Route::post('/logout', [AuthController::class, 'logout'])
            ->middleware('auth:automotive_admin')
            ->name('logout');

        Route::post('/stop-impersonation', [AuthController::class, 'stopImpersonation'])
            ->middleware('auth:automotive_admin')
            ->name('stop-impersonation');

        Route::middleware(['auth:automotive_admin', 'tenant.subscription.active'])->group(function () {
            Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
            Route::post('/users', [UserController::class, 'store'])
                ->middleware('tenant.user.limit')
                ->name('users.store');
            Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

            Route::get('/branches', [BranchController::class, 'index'])->name('branches.index');
            Route::get('/branches/create', [BranchController::class, 'create'])->name('branches.create');
            Route::post('/branches', [BranchController::class, 'store'])->name('branches.store');
            Route::get('/branches/{branch}/edit', [BranchController::class, 'edit'])->name('branches.edit');
            Route::put('/branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
            Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy');

            Route::get('/billing', [BillingController::class, 'status'])->name('billing.status');
            Route::post('/billing/renew', [BillingController::class, 'renew'])->name('billing.renew');
            Route::post('/billing/change-plan', [BillingController::class, 'changePlan'])->name('billing.change-plan');
            Route::post('/billing/payment-method/setup-intent', [BillingController::class, 'createSetupIntent'])->name('billing.payment-method.setup-intent');
            Route::post('/billing/payment-method/default', [BillingController::class, 'saveDefaultPaymentMethod'])->name('billing.payment-method.default');
            Route::get('/billing/success', [BillingController::class, 'success'])->name('billing.success');
            Route::get('/billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');
            Route::post('/billing/portal', [BillingController::class, 'portal'])->name('billing.portal');
            Route::post('/billing/cancel-subscription', [BillingController::class, 'cancelSubscription'])->name('billing.cancel-subscription');
            Route::post('/billing/resume-subscription', [BillingController::class, 'resumeSubscription'])->name('billing.resume-subscription');

            Route::middleware('tenant.workspace.product:workshop-operations')->group(function () {
                Route::get('/workshop-operations', [WorkspaceModuleController::class, 'workshopOperations'])
                    ->name('modules.workshop-operations');
                Route::get('/maintenance', [MaintenanceController::class, 'index'])
                    ->name('maintenance.index');
                Route::get('/maintenance/board', [MaintenanceWorkflowController::class, 'board'])
                    ->name('maintenance.board');
                Route::get('/maintenance/check-ins', [MaintenanceController::class, 'checkInsIndex'])
                    ->name('maintenance.check-ins.index');
                Route::get('/maintenance/check-ins/create', [MaintenanceController::class, 'checkInsCreate'])
                    ->name('maintenance.check-ins.create');
                Route::post('/maintenance/check-ins', [MaintenanceController::class, 'checkInsStore'])
                    ->name('maintenance.check-ins.store');
                Route::get('/maintenance/check-ins/{checkIn}', [MaintenanceController::class, 'checkInsShow'])
                    ->name('maintenance.check-ins.show');
                Route::post('/maintenance/check-ins/{checkIn}/verify-vin', [MaintenanceController::class, 'verifyVin'])
                    ->name('maintenance.check-ins.verify-vin');
                Route::post('/maintenance/attachments', [MaintenanceAttachmentController::class, 'store'])
                    ->name('maintenance.attachments.store');
                Route::get('/maintenance/service-catalog', [MaintenanceController::class, 'serviceCatalogIndex'])
                    ->name('maintenance.service-catalog.index');
                Route::post('/maintenance/service-catalog', [MaintenanceController::class, 'serviceCatalogStore'])
                    ->name('maintenance.service-catalog.store');
                Route::get('/maintenance/estimates', [MaintenanceController::class, 'estimatesIndex'])
                    ->name('maintenance.estimates.index');
                Route::get('/maintenance/estimates/create', [MaintenanceController::class, 'estimatesCreate'])
                    ->name('maintenance.estimates.create');
                Route::post('/maintenance/estimates', [MaintenanceController::class, 'estimatesStore'])
                    ->name('maintenance.estimates.store');
                Route::get('/maintenance/estimates/{estimate}', [MaintenanceController::class, 'estimatesShow'])
                    ->name('maintenance.estimates.show');
                Route::get('/maintenance/approvals', [MaintenanceLifecycleController::class, 'approvalsIndex'])
                    ->name('maintenance.approvals.index');
                Route::post('/maintenance/approvals/{estimate}/send', [MaintenanceLifecycleController::class, 'approvalsSend'])
                    ->name('maintenance.approvals.send');
                Route::post('/maintenance/approvals/{estimate}/approve', [MaintenanceLifecycleController::class, 'approvalsApprove'])
                    ->name('maintenance.approvals.approve');
                Route::get('/maintenance/deliveries', [MaintenanceLifecycleController::class, 'deliveriesIndex'])
                    ->name('maintenance.deliveries.index');
                Route::post('/maintenance/deliveries', [MaintenanceLifecycleController::class, 'deliveriesStore'])
                    ->name('maintenance.deliveries.store');
                Route::post('/maintenance/deliveries/{delivery}/release', [MaintenanceLifecycleController::class, 'deliveriesRelease'])
                    ->name('maintenance.deliveries.release');
                Route::get('/maintenance/warranties', [MaintenanceLifecycleController::class, 'warrantiesIndex'])
                    ->name('maintenance.warranties.index');
                Route::post('/maintenance/warranties', [MaintenanceLifecycleController::class, 'warrantiesStore'])
                    ->name('maintenance.warranties.store');
                Route::post('/maintenance/warranty-claims', [MaintenanceLifecycleController::class, 'warrantyClaimsStore'])
                    ->name('maintenance.warranty-claims.store');
                Route::get('/maintenance/complaints', [MaintenanceLifecycleController::class, 'complaintsIndex'])
                    ->name('maintenance.complaints.index');
                Route::post('/maintenance/complaints', [MaintenanceLifecycleController::class, 'complaintsStore'])
                    ->name('maintenance.complaints.store');
                Route::post('/maintenance/complaints/{complaint}/resolve', [MaintenanceLifecycleController::class, 'complaintsResolve'])
                    ->name('maintenance.complaints.resolve');
                Route::get('/maintenance/notifications', [MaintenanceLifecycleController::class, 'notificationsIndex'])
                    ->name('maintenance.notifications.index');
                Route::get('/maintenance/notifications/stream', [MaintenanceLifecycleController::class, 'notificationsStream'])
                    ->name('maintenance.notifications.stream');
                Route::get('/maintenance/documents', [MaintenanceDocumentController::class, 'index'])
                    ->name('maintenance.documents.index');
                Route::post('/maintenance/documents/generate', [MaintenanceDocumentController::class, 'generate'])
                    ->name('maintenance.documents.generate');
                Route::get('/maintenance/documents/{document}/download', [DocumentController::class, 'download'])
                    ->name('maintenance.documents.download');
                Route::get('/maintenance/documents/{document}/preview', [DocumentController::class, 'preview'])
                    ->name('maintenance.documents.preview');
                Route::get('/maintenance/reports', [MaintenanceReportsController::class, 'index'])
                    ->name('maintenance.reports.index');
                Route::get('/maintenance/reports/export/{report}', [MaintenanceReportsController::class, 'export'])
                    ->name('maintenance.reports.export');
                Route::get('/maintenance/advanced-operations', [MaintenanceReportsController::class, 'advanced'])
                    ->name('maintenance.advanced.index');
                Route::post('/maintenance/advanced-operations/preventive-rules', [MaintenanceReportsController::class, 'createPreventiveRule'])
                    ->name('maintenance.advanced.preventive-rules.store');
                Route::post('/maintenance/advanced-operations/refresh', [MaintenanceReportsController::class, 'refreshAdvanced'])
                    ->name('maintenance.advanced.refresh');
                Route::get('/maintenance/integrations', [MaintenanceIntegrationController::class, 'index'])
                    ->name('maintenance.integrations.index');
                Route::post('/maintenance/integrations/parts-requests', [MaintenanceIntegrationController::class, 'storePartsRequest'])
                    ->name('maintenance.integrations.parts-requests.store');
                Route::post('/maintenance/integrations/parts-requests/{partsRequest}/approve', [MaintenanceIntegrationController::class, 'approvePartsRequest'])
                    ->name('maintenance.integrations.parts-requests.approve');
                Route::post('/maintenance/integrations/parts-requests/{partsRequest}/issue', [MaintenanceIntegrationController::class, 'issuePartsRequest'])
                    ->name('maintenance.integrations.parts-requests.issue');
                Route::post('/maintenance/integrations/invoices/{invoice}/sync', [MaintenanceIntegrationController::class, 'syncInvoice'])
                    ->name('maintenance.integrations.invoices.sync');
                Route::get('/maintenance/inspection-templates', [MaintenanceWorkflowController::class, 'inspectionTemplatesIndex'])
                    ->name('maintenance.inspection-templates.index');
                Route::post('/maintenance/inspection-templates', [MaintenanceWorkflowController::class, 'inspectionTemplatesStore'])
                    ->name('maintenance.inspection-templates.store');
                Route::get('/maintenance/inspections', [MaintenanceWorkflowController::class, 'inspectionsIndex'])
                    ->name('maintenance.inspections.index');
                Route::post('/maintenance/inspections', [MaintenanceWorkflowController::class, 'inspectionsStore'])
                    ->name('maintenance.inspections.store');
                Route::get('/maintenance/inspections/{inspection}', [MaintenanceWorkflowController::class, 'inspectionsShow'])
                    ->name('maintenance.inspections.show');
                Route::post('/maintenance/inspections/{inspection}/items', [MaintenanceWorkflowController::class, 'inspectionItemsUpdate'])
                    ->name('maintenance.inspections.items.update');
                Route::post('/maintenance/inspections/{inspection}/complete', [MaintenanceWorkflowController::class, 'inspectionsComplete'])
                    ->name('maintenance.inspections.complete');
                Route::get('/maintenance/jobs', [MaintenanceWorkflowController::class, 'jobsIndex'])
                    ->name('maintenance.jobs.index');
                Route::post('/maintenance/jobs', [MaintenanceWorkflowController::class, 'jobsStore'])
                    ->name('maintenance.jobs.store');
                Route::get('/maintenance/jobs/{job}', [MaintenanceWorkflowController::class, 'jobsShow'])
                    ->name('maintenance.jobs.show');
                Route::post('/maintenance/jobs/{job}/start', [MaintenanceWorkflowController::class, 'jobsStart'])
                    ->name('maintenance.jobs.start');
                Route::post('/maintenance/jobs/{job}/pause', [MaintenanceWorkflowController::class, 'jobsPause'])
                    ->name('maintenance.jobs.pause');
                Route::post('/maintenance/jobs/{job}/resume', [MaintenanceWorkflowController::class, 'jobsResume'])
                    ->name('maintenance.jobs.resume');
                Route::post('/maintenance/jobs/{job}/complete', [MaintenanceWorkflowController::class, 'jobsComplete'])
                    ->name('maintenance.jobs.complete');
                Route::post('/maintenance/jobs/{job}/blocker', [MaintenanceWorkflowController::class, 'jobsBlocker'])
                    ->name('maintenance.jobs.blocker');
                Route::get('/maintenance/diagnosis', [MaintenanceWorkflowController::class, 'diagnosisIndex'])
                    ->name('maintenance.diagnosis.index');
                Route::post('/maintenance/diagnosis', [MaintenanceWorkflowController::class, 'diagnosisStore'])
                    ->name('maintenance.diagnosis.store');
                Route::get('/maintenance/qc', [MaintenanceWorkflowController::class, 'qcIndex'])
                    ->name('maintenance.qc.index');
                Route::post('/maintenance/qc', [MaintenanceWorkflowController::class, 'qcStore'])
                    ->name('maintenance.qc.store');
                Route::post('/maintenance/qc/{qcRecord}/complete', [MaintenanceWorkflowController::class, 'qcComplete'])
                    ->name('maintenance.qc.complete');
                Route::middleware('tenant.workspace.product:workshop-customers')->group(function () {
                    Route::get('/workshop-customers', [WorkspaceModuleController::class, 'workshopCustomers'])
                        ->name('modules.workshop-customers');
                });
                Route::middleware('tenant.workspace.product:workshop-vehicles')->group(function () {
                    Route::get('/workshop-vehicles', [WorkspaceModuleController::class, 'workshopVehicles'])
                        ->name('modules.workshop-vehicles');
                });
                Route::middleware('tenant.workspace.product:workshop-work-orders')->group(function () {
                    Route::get('/work-orders', [WorkspaceModuleController::class, 'workshopWorkOrders'])
                        ->name('modules.workshop-work-orders');
                });
                Route::post('/workshop-operations/customers', [WorkspaceModuleController::class, 'storeWorkshopCustomer'])
                    ->name('modules.workshop-operations.customers.store');
                Route::post('/workshop-operations/vehicles', [WorkspaceModuleController::class, 'storeWorkshopVehicle'])
                    ->name('modules.workshop-operations.vehicles.store');
                Route::post('/workshop-operations/work-orders', [WorkspaceModuleController::class, 'storeWorkOrder'])
                    ->name('modules.workshop-operations.work-orders.store');
                Route::get('/workshop-operations/work-orders/{workOrder}', [WorkspaceModuleController::class, 'showWorkOrder'])
                    ->name('modules.workshop-operations.work-orders.show');
                Route::post('/workshop-operations/work-orders/{workOrder}/labor-lines', [WorkspaceModuleController::class, 'storeWorkOrderLaborLine'])
                    ->name('modules.workshop-operations.work-orders.labor-lines.store');
                Route::post('/workshop-operations/work-orders/{workOrder}/status', [WorkspaceModuleController::class, 'updateWorkOrderStatus'])
                    ->name('modules.workshop-operations.work-orders.status');
                Route::post('/workshop-operations/consume-part', [WorkspaceModuleController::class, 'consumeWorkshopPart'])
                    ->name('modules.workshop-operations.consume-part');
            });

            Route::middleware('tenant.workspace.product:supplier-catalog')->group(function () {
                Route::get('/supplier-catalog', [WorkspaceModuleController::class, 'supplierCatalog'])
                    ->name('modules.supplier-catalog');
                Route::post('/supplier-catalog', [WorkspaceModuleController::class, 'storeSupplier'])
                    ->name('modules.supplier-catalog.store');
            });

            Route::middleware('tenant.workspace.product:parts_inventory')->group(function () {
                Route::get('/products', [ProductController::class, 'index'])->name('products.index');
                Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
                Route::post('/products', [ProductController::class, 'store'])->name('products.store');
                Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
                Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
                Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

                Route::get('/inventory-adjustments', [InventoryAdjustmentController::class, 'index'])->name('inventory-adjustments.index');
                Route::get('/inventory-adjustments/create', [InventoryAdjustmentController::class, 'create'])->name('inventory-adjustments.create');
                Route::post('/inventory-adjustments', [InventoryAdjustmentController::class, 'store'])->name('inventory-adjustments.store');

                Route::get('/inventory-report', [InventoryReportController::class, 'index'])->name('inventory-report.index');

                Route::get('/stock-transfers', [StockTransferController::class, 'index'])->name('stock-transfers.index');
                Route::get('/stock-transfers/create', [StockTransferController::class, 'create'])->name('stock-transfers.create');
                Route::post('/stock-transfers', [StockTransferController::class, 'store'])->name('stock-transfers.store');
                Route::get('/stock-transfers/{stockTransfer}', [StockTransferController::class, 'show'])->name('stock-transfers.show');
                Route::post('/stock-transfers/{stockTransfer}/post', [StockTransferController::class, 'post'])->name('stock-transfers.post');

                Route::get('/stock-movements', [StockMovementReportController::class, 'index'])->name('stock-movements.index');
            });

            Route::middleware('tenant.workspace.product:general-ledger')->group(function () {
                Route::get('/general-ledger', [WorkspaceModuleController::class, 'generalLedger'])
                    ->name('modules.general-ledger');
                Route::post('/general-ledger/first-time-setup', [WorkspaceModuleController::class, 'storeAccountingFirstTimeSetup'])
                    ->name('modules.general-ledger.first-time-setup.store');
                Route::post('/general-ledger/posting-groups', [WorkspaceModuleController::class, 'storeAccountingPostingGroup'])
                    ->name('modules.general-ledger.posting-groups.store');
                Route::post('/general-ledger/accounts', [WorkspaceModuleController::class, 'storeAccountingAccount'])
                    ->name('modules.general-ledger.accounts.store');
                Route::post('/general-ledger/statement-notes', [WorkspaceModuleController::class, 'storeAccountingStatementNote'])
                    ->name('modules.general-ledger.statement-notes.store');
                Route::post('/general-ledger/accounts/{account}/deactivate', [WorkspaceModuleController::class, 'deactivateAccountingAccount'])
                    ->name('modules.general-ledger.accounts.deactivate');
                Route::delete('/general-ledger/accounts/{account}', [WorkspaceModuleController::class, 'destroyAccountingAccount'])
                    ->name('modules.general-ledger.accounts.destroy');
                Route::post('/general-ledger/period-locks', [WorkspaceModuleController::class, 'storeAccountingPeriodLock'])
                    ->name('modules.general-ledger.period-locks.store');
                Route::post('/general-ledger/period-locks/closing', [WorkspaceModuleController::class, 'startAccountingPeriodClose'])
                    ->name('modules.general-ledger.period-locks.closing');
                Route::post('/general-ledger/period-locks/{period}/archive', [WorkspaceModuleController::class, 'archiveAccountingPeriod'])
                    ->name('modules.general-ledger.period-locks.archive');
                Route::post('/general-ledger/period-close-adjustments', [WorkspaceModuleController::class, 'storePeriodCloseAdjustment'])
                    ->name('modules.general-ledger.period-close-adjustments.store');
                Route::post('/general-ledger/period-close-adjustments/{adjustment}/review', [WorkspaceModuleController::class, 'reviewPeriodCloseAdjustment'])
                    ->name('modules.general-ledger.period-close-adjustments.review');
                Route::post('/general-ledger/policies', [WorkspaceModuleController::class, 'storeAccountingPolicy'])
                    ->name('modules.general-ledger.policies.store');
                Route::post('/general-ledger/tax-rates', [WorkspaceModuleController::class, 'storeAccountingTaxRate'])
                    ->name('modules.general-ledger.tax-rates.store');
                Route::post('/general-ledger/tax-filings', [WorkspaceModuleController::class, 'storeAccountingTaxFiling'])
                    ->name('modules.general-ledger.tax-filings.store');
                Route::post('/general-ledger/tax-filings/{filing}/approve', [WorkspaceModuleController::class, 'approveAccountingTaxFiling'])
                    ->name('modules.general-ledger.tax-filings.approve');
                Route::post('/general-ledger/exchange-rates', [WorkspaceModuleController::class, 'storeAccountingExchangeRate'])
                    ->name('modules.general-ledger.exchange-rates.store');
                Route::post('/general-ledger/fx-revaluations', [WorkspaceModuleController::class, 'storeAccountingFxRevaluation'])
                    ->name('modules.general-ledger.fx-revaluations.store');
                Route::post('/general-ledger/bank-accounts', [WorkspaceModuleController::class, 'storeAccountingBankAccount'])
                    ->name('modules.general-ledger.bank-accounts.store');
                Route::get('/general-ledger/exports/{report}', [WorkspaceModuleController::class, 'exportAccountingReport'])
                    ->name('modules.general-ledger.exports');
                Route::get('/general-ledger/import-templates/{template}', [WorkspaceModuleController::class, 'downloadAccountingImportTemplate'])
                    ->name('modules.general-ledger.import-templates');
                Route::post('/general-ledger/manual-journal-entries', [WorkspaceModuleController::class, 'storeManualJournalEntry'])
                    ->name('modules.general-ledger.manual-journal-entries.store');
                Route::get('/general-ledger/journal-entries/{journalEntry}', [WorkspaceModuleController::class, 'showJournalEntry'])
                    ->name('modules.general-ledger.journal-entries.show');
                Route::post('/general-ledger/journal-entries/{journalEntry}/reverse', [WorkspaceModuleController::class, 'reverseJournalEntry'])
                    ->name('modules.general-ledger.journal-entries.reverse');
                Route::post('/general-ledger/journal-entries/{journalEntry}/approve', [WorkspaceModuleController::class, 'approveManualJournalEntry'])
                    ->name('modules.general-ledger.journal-entries.approve');
                Route::post('/general-ledger/journal-entries/{journalEntry}/reject', [WorkspaceModuleController::class, 'rejectManualJournalEntry'])
                    ->name('modules.general-ledger.journal-entries.reject');
                Route::post('/general-ledger/journal-entries/{journalEntry}/post-approved', [WorkspaceModuleController::class, 'postApprovedManualJournalEntry'])
                    ->name('modules.general-ledger.journal-entries.post-approved');
                Route::post('/general-ledger/accounting-events/{accountingEvent}/post', [WorkspaceModuleController::class, 'postAccountingEvent'])
                    ->name('modules.general-ledger.accounting-events.post');
                Route::get('/general-ledger/accounting-events/{accountingEvent}/invoice', [WorkspaceModuleController::class, 'showAccountingInvoice'])
                    ->name('modules.general-ledger.accounting-events.invoice');
                Route::get('/general-ledger/customer-statement', [WorkspaceModuleController::class, 'showCustomerStatement'])
                    ->name('modules.general-ledger.customer-statement');
                Route::post('/general-ledger/invoices', [WorkspaceModuleController::class, 'storeAccountingInvoice'])
                    ->name('modules.general-ledger.invoices.store');
                Route::post('/general-ledger/invoices/{invoice}/post', [WorkspaceModuleController::class, 'postAccountingInvoice'])
                    ->name('modules.general-ledger.invoices.post');
                Route::post('/general-ledger/inventory-movements/{stockMovement}/post', [WorkspaceModuleController::class, 'postInventoryMovement'])
                    ->name('modules.general-ledger.inventory-movements.post');
                Route::post('/general-ledger/payments', [WorkspaceModuleController::class, 'storeAccountingPayment'])
                    ->name('modules.general-ledger.payments.store');
                Route::post('/general-ledger/deposit-batches', [WorkspaceModuleController::class, 'storeAccountingDepositBatch'])
                    ->name('modules.general-ledger.deposit-batches.store');
                Route::get('/general-ledger/deposit-batches/{depositBatch}', [WorkspaceModuleController::class, 'showAccountingDepositBatch'])
                    ->name('modules.general-ledger.deposit-batches.show');
                Route::post('/general-ledger/deposit-batches/{depositBatch}/correct', [WorkspaceModuleController::class, 'correctAccountingDepositBatch'])
                    ->name('modules.general-ledger.deposit-batches.correct');
                Route::post('/general-ledger/deposit-batches/{depositBatch}/reconcile', [WorkspaceModuleController::class, 'reconcileAccountingDepositBatch'])
                    ->name('modules.general-ledger.deposit-batches.reconcile');
                Route::post('/general-ledger/vendor-bills', [WorkspaceModuleController::class, 'storeAccountingVendorBill'])
                    ->name('modules.general-ledger.vendor-bills.store');
                Route::post('/general-ledger/vendor-bills/{vendorBill}/credit-notes', [WorkspaceModuleController::class, 'storeAccountingVendorBillCreditNote'])
                    ->name('modules.general-ledger.vendor-bills.credit-notes.store');
                Route::post('/general-ledger/vendor-bills/{vendorBill}/post', [WorkspaceModuleController::class, 'postAccountingVendorBill'])
                    ->name('modules.general-ledger.vendor-bills.post');
                Route::post('/general-ledger/vendor-bill-payments', [WorkspaceModuleController::class, 'storeAccountingVendorBillPayment'])
                    ->name('modules.general-ledger.vendor-bill-payments.store');
                Route::post('/general-ledger/vendor-bill-payments/{payment}/reconcile', [WorkspaceModuleController::class, 'reconcileAccountingVendorBillPayment'])
                    ->name('modules.general-ledger.vendor-bill-payments.reconcile');
                Route::post('/general-ledger/payments/{payment}/reconcile', [WorkspaceModuleController::class, 'reconcileAccountingPayment'])
                    ->name('modules.general-ledger.payments.reconcile');
                Route::post('/general-ledger/payments/{payment}/void', [WorkspaceModuleController::class, 'voidAccountingPayment'])
                    ->name('modules.general-ledger.payments.void');
                Route::post('/general-ledger/integration-handoffs/{handoff}/retry', [WorkspaceModuleController::class, 'retryIntegrationHandoff'])
                    ->name('modules.general-ledger.integration-handoffs.retry');
            });
        });
    });
};

$registerWorkspaceAdminRoutes('workspace', 'workspace/admin', true);
$registerWorkspaceAdminRoutes('automotive', 'automotive/admin', false);
