<?php

use App\Http\Controllers\Automotive\Admin\Auth\AuthController;
use App\Http\Controllers\Automotive\Admin\BillingController;
use App\Http\Controllers\Automotive\Admin\BranchController;
use App\Http\Controllers\Automotive\Admin\DashboardController;
use App\Http\Controllers\Automotive\Admin\InventoryAdjustmentController;
use App\Http\Controllers\Automotive\Admin\InventoryReportController;
use App\Http\Controllers\Automotive\Admin\ProductController;
use App\Http\Controllers\Automotive\Admin\StockMovementReportController;
use App\Http\Controllers\Automotive\Admin\StockTransferController;
use App\Http\Controllers\Automotive\Admin\UserController;
use App\Http\Controllers\Automotive\Admin\WorkspaceModuleController;
use Illuminate\Support\Facades\Route;

Route::prefix('automotive/admin')
    ->name('automotive.admin.')
    ->group(function () {
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
            });
        });
    });
