<?php

use App\Http\Controllers\Admin\CouponController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->middleware(['auth:web'])
    ->name('admin.')
    ->group(function () {
        Route::prefix('coupons')
            ->name('coupons.')
            ->group(function () {
                Route::get('/', [CouponController::class, 'index'])->name('index');
                Route::get('/create', [CouponController::class, 'create'])->name('create');
                Route::post('/', [CouponController::class, 'store'])->name('store');
                Route::get('/{coupon}/edit', [CouponController::class, 'edit'])->name('edit');
                Route::put('/{coupon}', [CouponController::class, 'update'])->name('update');
                Route::patch('/{coupon}/toggle-active', [CouponController::class, 'toggleActive'])->name('toggle-active');
                Route::delete('/{coupon}', [CouponController::class, 'destroy'])->name('destroy');
            });
    });
