<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the TenantRouteServiceProvider.
| They should only be accessible from tenant domains/subdomains.
|
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {

    /**
     * Basic tenant home page
     */
    Route::get('/', function () {
        return 'This is your multi-tenant application. The id of the current tenant is ' . tenant('id');
    });

    /**
     * ✅ Tenant diagnostic endpoint
     * Visit: https://<tenant-domain>/__tenant_check
     */
    Route::get('/__tenant_check', function () {
        return response()->json([
            'tenant_id' => tenant('id'),
            'db' => DB::connection()->getDatabaseName(),
            'default_connection' => config('database.default'),
        ]);
    });
});
