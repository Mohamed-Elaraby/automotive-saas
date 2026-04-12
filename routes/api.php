<?php

use App\Http\Controllers\Automotive\TrialSignupController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

$registerTrialSignupRoute = static function (string $prefix, ?string $namePrefix = null): void {
    Route::post("{$prefix}/start-trial", TrialSignupController::class)
        ->name(ltrim(($namePrefix ? "{$namePrefix}." : '') . 'api.automotive.startTrial', '.'))
        ->middleware('throttle:10,1');
};

$registerTrialSignupRoute('/workspace');
$registerTrialSignupRoute('/automotive', 'legacy');
