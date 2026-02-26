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

    Route::get('/__tenant_check', function () {
        return response()->json([
            'tenant_id' => tenant('id'),
            'db' => DB::connection()->getDatabaseName(),
            'default_connection' => config('database.default'),
        ]);
    });

    Route::get('/__tenant', fn () => 'TENANT OK');
});
