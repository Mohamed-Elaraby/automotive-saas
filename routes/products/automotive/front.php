<?php

use App\Http\Controllers\Automotive\Front\Auth\LoginController;
use App\Http\Controllers\Automotive\Front\Auth\ForgotPasswordController;
use App\Http\Controllers\Automotive\Front\Auth\RegisterController;
use App\Http\Controllers\Automotive\Front\Auth\ResetPasswordController;
use App\Http\Controllers\Automotive\Front\CustomerPortalController;
use App\Http\Controllers\Automotive\Front\CustomerPortalNotificationController;
use App\Http\Controllers\Automotive\Webhooks\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('automotive')
    ->name('automotive.')
    ->group(function () {
        Route::middleware('guest:web')->group(function () {
            Route::get('/login', [LoginController::class, 'show'])->name('login');
            Route::post('/login', [LoginController::class, 'submit'])->name('login.submit');

            Route::get('/password/reset', [ForgotPasswordController::class, 'show'])->name('password.request');
            Route::post('/password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
            Route::get('/password/reset/{token}', [ResetPasswordController::class, 'show'])->name('password.reset');
            Route::post('/password/reset', [ResetPasswordController::class, 'reset'])->name('password.update');

            Route::get('/register', [RegisterController::class, 'show'])->name('register');
            Route::post('/register', [RegisterController::class, 'submit'])->name('register.submit');
            Route::post('/register/coupon-preview', [RegisterController::class, 'previewCoupon'])->name('register.coupon-preview');
        });

        Route::middleware('auth:web')->group(function () {
            Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

            Route::get('/portal', [CustomerPortalController::class, 'index'])->name('portal');
            Route::post('/portal/start-trial', [CustomerPortalController::class, 'startTrial'])->name('portal.start-trial');
            Route::post('/portal/subscribe', [CustomerPortalController::class, 'startPaidCheckout'])->name('portal.subscribe');
            Route::post('/portal/products/request-enable', [CustomerPortalController::class, 'requestProductEnablement'])->name('portal.products.request-enable');
            Route::get('/portal/notifications/unread-summary', [CustomerPortalNotificationController::class, 'unreadSummary'])->name('portal.notifications.unread-summary');
            Route::get('/portal/notifications/stream', [CustomerPortalNotificationController::class, 'stream'])->name('portal.notifications.stream');
            Route::post('/portal/notifications/{notification}/mark-read', [CustomerPortalNotificationController::class, 'markRead'])->name('portal.notifications.mark-read');
            Route::get('/portal/checkout/success', [CustomerPortalController::class, 'checkoutSuccess'])->name('portal.checkout.success');
            Route::get('/portal/checkout/cancel', [CustomerPortalController::class, 'checkoutCancel'])->name('portal.checkout.cancel');
        });

        Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
            ->name('webhooks.stripe');
    });
