<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    'refresh.route.lookups',
])->group(function () {

    Route::get('/', function () {
        return 'TENANT HOME: ' . tenant('id');
    });

    /*
    |--------------------------------------------------------------------------
    | Product Routes (Tenant scoped)
    |--------------------------------------------------------------------------
    | كل product له ملفات routes منفصلة: admin + front
    */
    if (! app()->bound('routes.automotive.admin.loaded')) {
        app()->instance('routes.automotive.admin.loaded', true);

        require base_path('routes/products/automotive/admin.php');
    }

});
