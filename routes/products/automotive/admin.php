<?php

use App\Http\Controllers\automotive\Admin\Auth\AuthController;
use App\Http\Controllers\automotive\Admin\DashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('automotive/admin')
    ->name('automotive.admin.')
    ->group(function () {

        Route::get('/login', [AuthController::class, 'login'])->name('login');
        Route::post('/login', [AuthController::class, 'doLogin'])->name('login.submit');

        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

        Route::get('/register', [AuthController::class, 'register'])->name('register');
        Route::post('/register', [AuthController::class, 'doRegister'])->name('register.submit');        Route::get('/forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
        Route::get('/reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');

        Route::middleware(['auth:automotive_admin'])->group(function () {
            // <seven-scaffold-routes>
            Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        });
    });
