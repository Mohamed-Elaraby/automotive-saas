<?php


use App\Http\Controllers\automotive\Admin\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('automotive')
    ->name('automotive.')
    ->group(function () {
        // <seven-scaffold-routes>
        Route::get('/register', [AuthController::class, 'register'])->name('register');
        Route::post('/register', [AuthController::class, 'doRegister'])->name('register.submit');
    });
