<?php

use App\Http\Controllers\automotive\Admin\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('automotive/admin/login', [AuthController::class, 'login'])->name('automotive.admin.login');

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth'])
    ->group(function (){

        // <seven-scaffold-routes>
        Route::get('dashboard', [\App\Http\Controllers\automotive\Admin\DashboardController::class, 'index'])->name('dashboard');
    });
