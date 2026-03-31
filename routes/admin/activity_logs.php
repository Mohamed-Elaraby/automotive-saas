<?php

use App\Http\Controllers\Admin\AdminActivityLogController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->middleware(['auth:admin'])
    ->name('admin.')
    ->group(function () {
        Route::prefix('activity-logs')
            ->name('activity-logs.')
            ->group(function () {
                Route::get('/', [AdminActivityLogController::class, 'index'])->name('index');
                Route::get('/{activityLog}', [AdminActivityLogController::class, 'show'])->name('show');
            });
    });
