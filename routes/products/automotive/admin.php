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
use Illuminate\Support\Facades\Route;

Route::prefix('automotive/admin')
    ->name('automotive.admin.')
    ->group(function () {
        Route::get('/subscription-expired', function () {
            return response()->view('automotive.admin.auth.subscription-expired');
        })->name('subscription.expired');

        Route::middleware('guest:automotive_admin')->group(function () {
            Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
            Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
        });

        Route::post('/logout', [AuthController::class, 'logout'])
            ->middleware('auth:automotive_admin')
            ->name('logout');

        Route::middleware(['auth:automotive_admin', 'tenant.subscription.active'])->group(function () {
            Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

            // Users Routes
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
            Route::post('/users', [UserController::class, 'store'])
                ->middleware('tenant.user.limit')
                ->name('users.store');
            Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

            // Branches Routes
            Route::get('/branches', [BranchController::class, 'index'])->name('branches.index');
            Route::get('/branches/create', [BranchController::class, 'create'])->name('branches.create');
            Route::post('/branches', [BranchController::class, 'store'])->name('branches.store');
            Route::get('/branches/{branch}/edit', [BranchController::class, 'edit'])->name('branches.edit');
            Route::put('/branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
            Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy');

            // Products Routes
            Route::get('/products', [ProductController::class, 'index'])->name('products.index');
            Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
            Route::post('/products', [ProductController::class, 'store'])->name('products.store');
            Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
            Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
            Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

            // Inventory Adjustments Routes
            Route::get('/inventory-adjustments', [InventoryAdjustmentController::class, 'index'])->name('inventory-adjustments.index');
            Route::get('/inventory-adjustments/create', [InventoryAdjustmentController::class, 'create'])->name('inventory-adjustments.create');
            Route::post('/inventory-adjustments', [InventoryAdjustmentController::class, 'store'])->name('inventory-adjustments.store');

            // Inventory report Routes
            Route::get('/inventory-report', [InventoryReportController::class, 'index'])->name('inventory-report.index');

            // Stock Transfers Routes
            Route::get('/stock-transfers', [StockTransferController::class, 'index'])->name('stock-transfers.index');
            Route::get('/stock-transfers/create', [StockTransferController::class, 'create'])->name('stock-transfers.create');
            Route::post('/stock-transfers', [StockTransferController::class, 'store'])->name('stock-transfers.store');
            Route::get('/stock-transfers/{stockTransfer}', [StockTransferController::class, 'show'])->name('stock-transfers.show');
            Route::post('/stock-transfers/{stockTransfer}/post', [StockTransferController::class, 'post'])->name('stock-transfers.post');


            Route::get('/stock-movements', [StockMovementReportController::class, 'index'])->name('stock-movements.index');

            Route::get('/billing', [BillingController::class, 'status'])->name('billing.status');
            Route::post('/billing/renew', [BillingController::class, 'renew'])->name('billing.renew');

            Route::get('/billing', [BillingController::class, 'status'])->name('billing.status');
            Route::post('/billing/renew', [BillingController::class, 'renew'])->name('billing.renew');
            Route::get('/billing/success', [BillingController::class, 'success'])->name('billing.success');
            Route::get('/billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');
        });


    });
