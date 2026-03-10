<?php

use App\Http\Controllers\Automotive\Admin\Auth\AuthController;
use App\Http\Controllers\Automotive\Admin\BranchController;
use App\Http\Controllers\Automotive\Admin\DashboardController;
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

            // Users Routs
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
            Route::post('/users', [UserController::class, 'store'])
                ->middleware('tenant.user.limit')
                ->name('users.store');
            Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

            // Products Routs
            Route::get('/branches', [BranchController::class, 'index'])->name('branches.index');
            Route::get('/branches/create', [BranchController::class, 'create'])->name('branches.create');
            Route::post('/branches', [BranchController::class, 'store'])->name('branches.store');
            Route::get('/branches/{branch}/edit', [BranchController::class, 'edit'])->name('branches.edit');
            Route::put('/branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
            Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy');

        });


    });
