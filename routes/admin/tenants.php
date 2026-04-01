<?php

use App\Http\Controllers\Admin\TenantController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->middleware(['auth:admin'])
    ->name('admin.')
    ->group(function () {
        Route::prefix('tenants')
            ->name('tenants.')
            ->group(function () {
                Route::get('/', [TenantController::class, 'index'])->name('index');
                Route::get('/{tenantId}', [TenantController::class, 'show'])->name('show');

                Route::post('/{tenantId}/suspend', [TenantController::class, 'suspend'])->name('suspend');
                Route::post('/{tenantId}/activate', [TenantController::class, 'activate'])->name('activate');
                Route::post('/{tenantId}/extend-trial', [TenantController::class, 'extendTrial'])->name('extend-trial');
                Route::post('/{tenantId}/change-plan', [TenantController::class, 'changePlan'])->name('change-plan');
                Route::delete('/{tenantId}', [TenantController::class, 'destroy'])->name('destroy');
            });
    });
