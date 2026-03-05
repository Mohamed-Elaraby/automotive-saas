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
    require __DIR__ . '/products/automotive/front.php';
    require __DIR__ . '/products/automotive/admin.php';

    // لما تزود product جديد:
    // require __DIR__ . '/products/spareparts/front.php';
    // require __DIR__ . '/products/spareparts/admin.php';

});
