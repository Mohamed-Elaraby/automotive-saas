<?php


use App\Http\Controllers\Automotive\Admin\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

Route::prefix('automotive/admin')
    ->name('automotive.admin.')
    ->group(function () {

        // Auth
        Route::middleware('guest:automotive_admin')->group(function () {
            Route::get('/login', [RegisterController::class, 'login'])->name('login');
            Route::post('/login', [RegisterController::class, 'doLogin'])->name('login.submit');
        });

        Route::post('/logout', [RegisterController::class, 'logout'])
            ->middleware('auth:automotive_admin')
            ->name('logout');

        // Protected
        Route::middleware('auth:automotive_admin')->group(function () {
            Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        });
    });
