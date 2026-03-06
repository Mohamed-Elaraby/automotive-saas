<?php

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\DatabaseConfig;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    public function database(): DatabaseConfig
    {
        return new DatabaseConfig($this, [
            'database' => $this->getInternal('db_name') ?: ('tenant_' . $this->id),
        ]);
    }
}
