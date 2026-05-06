<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Automotive\Api\MaintenanceIntegrationApiController;
use App\Http\Controllers\Automotive\Customer\MaintenanceCustomerPortalController;
use App\Http\Controllers\Automotive\Customer\MaintenancePaymentRequestController;
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

    Route::get('/users', function () {
        return redirect()->route('automotive.admin.users.index');
    });

    Route::get('/maintenance/customer/track/{token}', [MaintenanceCustomerPortalController::class, 'tracking'])
        ->name('automotive.customer.maintenance.tracking');
    Route::get('/maintenance/customer/api/track/{token}', [MaintenanceCustomerPortalController::class, 'trackingJson'])
        ->name('automotive.customer.maintenance.tracking.api');
    Route::get('/maintenance/customer/estimates/{token}', [MaintenanceCustomerPortalController::class, 'estimate'])
        ->name('automotive.customer.maintenance.estimate');
    Route::get('/maintenance/customer/api/estimates/{token}', [MaintenanceCustomerPortalController::class, 'estimateJson'])
        ->name('automotive.customer.maintenance.estimate.api');
    Route::post('/maintenance/customer/estimates/{token}/decision', [MaintenanceCustomerPortalController::class, 'estimateDecision'])
        ->name('automotive.customer.maintenance.estimate.decision');
    Route::post('/maintenance/customer/track/{token}/complaints', [MaintenanceCustomerPortalController::class, 'submitComplaint'])
        ->name('automotive.customer.maintenance.complaints.store');
    Route::post('/maintenance/customer/track/{token}/feedback', [MaintenanceCustomerPortalController::class, 'submitFeedback'])
        ->name('automotive.customer.maintenance.feedback.store');
    Route::get('/maintenance/customer/payment-requests/{token}', [MaintenancePaymentRequestController::class, 'show'])
        ->name('automotive.customer.maintenance.payment-request');
    Route::get('/maintenance/customer/api/payment-requests/{token}', [MaintenancePaymentRequestController::class, 'json'])
        ->name('automotive.customer.maintenance.payment-request.api');

    Route::get('/maintenance/integrations/api/work-orders/{workOrder}', [MaintenanceIntegrationApiController::class, 'workOrder'])
        ->name('automotive.maintenance.integrations.api.work-orders.show');
    Route::get('/maintenance/integrations/api/invoices/{invoice}', [MaintenanceIntegrationApiController::class, 'invoice'])
        ->name('automotive.maintenance.integrations.api.invoices.show');

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
