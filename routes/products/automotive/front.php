<?php

use App\Http\Controllers\Automotive\Front\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

Route::prefix('automotive')->name('automotive.')->group(function () {
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'submit'])->name('register.submit');
});
