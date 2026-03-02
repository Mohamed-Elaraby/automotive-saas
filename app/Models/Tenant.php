<?php

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\DatabaseConfig;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    public function database(): DatabaseConfig
    {
        $config = new DatabaseConfig($this);

        // customize database name
        $config->setName('tenant_' . $this->id);

        return $config;
    }
}
