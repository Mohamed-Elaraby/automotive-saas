<?php

use App\Http\Controllers\Automotive\Admin\Auth\AuthController;
use App\Http\Controllers\Automotive\Admin\DashboardController;
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
        });
    });
