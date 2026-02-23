<?php

declare(strict_types=1);

use Stancl\Tenancy\Database\Models\Domain;

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant Model
    |--------------------------------------------------------------------------
    */

    'tenant_model' => \App\Models\Tenant::class,
    'id_generator' => Stancl\Tenancy\UUIDGenerator::class,
    'domain_model' => Domain::class,

    /*
    |--------------------------------------------------------------------------
    | Central Domains
    |--------------------------------------------------------------------------
    */

    'central_domains' => [
        '127.0.0.1',
        'localhost',
        'automotive.seven-scapital.com',
        '216.128.148.123',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenancy Bootstrappers
    |--------------------------------------------------------------------------
    */

    'bootstrappers' => [
        Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Tenancy Configuration
    |--------------------------------------------------------------------------
    */

    'database' => [

        /**
         * Central DB connection (main Laravel database)
         */
        'central_connection' => env('DB_CONNECTION', 'mysql'),

        /**
         * ✅ Template connection (IMPORTANT)
         */
        'template_connection' => 'tenant_template',

        /**
         * Tenant DB naming
         */
        'prefix' => env('TENANCY_DB_PREFIX', 'tenant_'),
        'suffix' => env('TENANCY_DB_SUFFIX', ''),

        /**
         * Database managers (FIXED namespace)
         */
        'managers' => [
            'mysql'  => Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager::class,
            'pgsql'  => Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLDatabaseManager::class,
            'sqlite' => Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager::class,
        ],

        /**
         * Auto create tenant database
         */
        'create_database' => true,

        /**
         * Don't auto delete DB in production
         */
        'drop_database' => false,

        /**
         * Optional DB permissions
         */
        'permissions' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant Migrations Path
    |--------------------------------------------------------------------------
    */

    'migrations' => [
        'tenant' => [
            'path' => database_path('migrations/tenant'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Tenancy
    |--------------------------------------------------------------------------
    */

    'cache' => [
        'tag_base' => 'tenant',
    ],

    /*
    |--------------------------------------------------------------------------
    | Filesystem Tenancy
    |--------------------------------------------------------------------------
    */

    'filesystem' => [
        'suffix_base' => 'tenant',
        'disks' => [],
        'root_override' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Tenancy
    |--------------------------------------------------------------------------
    */

    'queue' => [
        'tenant_aware_by_default' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Features (Optional)
    |--------------------------------------------------------------------------
    */

    'features' => [
        // Stancl\Tenancy\Features\UserImpersonation::class,
        // Stancl\Tenancy\Features\UniversalRoutes::class,
    ],
];
