<?php

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\DatabaseConfig;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    /**
     * Return database config for this tenant (multi-database).
     */
    public function database(): DatabaseConfig
    {
        // database name pattern: tenant_{tenantId}
        return new DatabaseConfig([
            'database' => 'tenant_' . $this->id,
        ]);
    }
}
