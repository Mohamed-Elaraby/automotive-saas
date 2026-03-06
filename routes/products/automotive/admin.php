<?php

use App\Http\Controllers\automotive\Admin\Auth\AuthController;
use App\Http\Controllers\automotive\Admin\DashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('automotive/admin')
    ->name('automotive.admin.')
    ->group(function () {

        // Auth
        Route::middleware('guest:automotive_admin')->group(function () {
            Route::get('/login', [AuthController::class, 'login'])->name('login');
            Route::post('/login', [AuthController::class, 'doLogin'])->name('login.submit');
        });

        Route::post('/logout', [AuthController::class, 'logout'])
            ->middleware('auth:automotive_admin')
            ->name('logout');

        // Protected
        Route::middleware('auth:automotive_admin')->group(function () {
            Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        });
    });
