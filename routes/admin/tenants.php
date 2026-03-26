<?php

use App\Http\Controllers\Admin\TenantController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->middleware(['auth:web'])
    ->name('admin.')
    ->group(function () {
        Route::prefix('tenants')
            ->name('tenants.')
            ->group(function () {
                Route::get('/', [TenantController::class, 'index'])->name('index');
                Route::get('/{tenantId}', [TenantController::class, 'show'])->name('show');
            });
    });
