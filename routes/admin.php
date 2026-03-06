<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth'])
    ->group(function (){

        // <seven-scaffold-routes>
        Route::resource('branch2s', \App\Http\Controllers\Admin\Branch2Controller::class);

        Route::resource('branches', \App\Http\Controllers\Admin\BranchController::class);


    });
