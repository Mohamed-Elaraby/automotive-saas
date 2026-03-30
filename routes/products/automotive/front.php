<?php

use App\Http\Controllers\Automotive\Front\Auth\LoginController;
use App\Http\Controllers\Automotive\Front\Auth\RegisterController;
use App\Http\Controllers\Automotive\Front\CustomerPortalController;
use App\Http\Controllers\Automotive\Front\EntryController;
use App\Http\Controllers\Automotive\Webhooks\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('automotive')
    ->name('automotive.')
    ->group(function () {
        Route::get('/get-started', [EntryController::class, 'index'])->name('get-started');

        Route::middleware('guest:web')->group(function () {
            Route::get('/login', [LoginController::class, 'show'])->name('login');
            Route::post('/login', [LoginController::class, 'submit'])->name('login.submit');

            Route::get('/register', [RegisterController::class, 'show'])->name('register');
            Route::post('/register', [RegisterController::class, 'submit'])->name('register.submit');
            Route::post('/register/coupon-preview', [RegisterController::class, 'previewCoupon'])->name('register.coupon-preview');
        });

        Route::middleware('auth:web')->group(function () {
            Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

            Route::get('/portal', [CustomerPortalController::class, 'index'])->name('portal');
            Route::post('/portal/start-trial', [CustomerPortalController::class, 'startTrial'])->name('portal.start-trial');
            Route::post('/portal/subscribe', [CustomerPortalController::class, 'startPaidCheckout'])->name('portal.subscribe');
            Route::get('/portal/checkout/success', [CustomerPortalController::class, 'checkoutSuccess'])->name('portal.checkout.success');
            Route::get('/portal/checkout/cancel', [CustomerPortalController::class, 'checkoutCancel'])->name('portal.checkout.cancel');
        });

        Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
            ->name('webhooks.stripe');
    });
