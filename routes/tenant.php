<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Automotive\Customer\MaintenanceCustomerPortalController;
use App\Http\Controllers\Core\DocumentController;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

$localizedRouteMiddleware = ['localeSessionRedirect', 'localizationRedirect', 'localeViewPath'];
$registerTenantWorkspaceRoutes = function (): void {
    Route::get('/', function () {
        return 'TENANT HOME: ' . tenant('id');
    });

    Route::get('/documents/verify/{token}', [DocumentController::class, 'verify'])
        ->name('documents.verify');

    Route::get('/maintenance/customer/track/{token}', [MaintenanceCustomerPortalController::class, 'tracking'])
        ->name('automotive.customer.maintenance.tracking');
    Route::get('/maintenance/customer/estimates/{token}', [MaintenanceCustomerPortalController::class, 'estimate'])
        ->name('automotive.customer.maintenance.estimate');
    Route::post('/maintenance/customer/estimates/{token}/decision', [MaintenanceCustomerPortalController::class, 'estimateDecision'])
        ->name('automotive.customer.maintenance.estimate.decision');

    /*
    |--------------------------------------------------------------------------
    | Product Routes (Tenant scoped)
    |--------------------------------------------------------------------------
    | كل product له ملفات routes منفصلة: admin + front
    */
    require base_path('routes/products/automotive/admin.php');
};

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    'refresh.route.lookups',
])->group(function () use ($localizedRouteMiddleware, $registerTenantWorkspaceRoutes) {

    Route::prefix('ar')
        ->middleware($localizedRouteMiddleware)
        ->group($registerTenantWorkspaceRoutes);

    Route::group([
        'prefix' => LaravelLocalization::setLocale(),
        'middleware' => $localizedRouteMiddleware,
    ], $registerTenantWorkspaceRoutes);

});
