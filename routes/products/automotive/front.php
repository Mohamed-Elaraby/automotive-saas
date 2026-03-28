<?php

use App\Http\Controllers\Automotive\Front\Auth\RegisterController;
use App\Http\Controllers\Automotive\Webhooks\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('automotive')->name('automotive.')->group(function () {
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'submit'])->name('register.submit');
    Route::post('/register/coupon-preview', [RegisterController::class, 'previewCoupon'])->name('register.coupon-preview');

    Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
        ->name('automotive.webhooks.stripe');
});
